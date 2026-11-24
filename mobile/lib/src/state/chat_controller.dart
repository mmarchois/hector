import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/api.dart';
import '../data/api_errors.dart';
import '../domain/conversation.dart';

/// Conversations de l'utilisateur (première page), pour l'accueil.
final conversationsProvider = FutureProvider.autoDispose<ConversationPage>(
  (ref) => ref.watch(conversationRepositoryProvider).list(),
);

/// Brouillons de saisie par conversation. Gardés hors de l'écran : un message
/// refusé faute d'allocation survit au détour par l'inscription.
final chatDraftsProvider = NotifierProvider<ChatDrafts, Map<String, String>>(
  ChatDrafts.new,
);

class ChatDrafts extends Notifier<Map<String, String>> {
  @override
  Map<String, String> build() => const {};

  void save(String conversationUuid, String text) {
    state = {...state, conversationUuid: text};
  }

  void clear(String conversationUuid) {
    state = {...state}..remove(conversationUuid);
  }
}

/// Message en cours d'envoi, ou dont l'issue n'est pas connue.
enum PendingStatus {
  /// Envoyé, réponse attendue
  sending,

  /// Réseau coupé pendant l'attente : le serveur a pu recevoir le message et
  /// répondre. On relit la conversation au lieu de renvoyer (pas de doublon).
  connectionLost,

  /// Refusé avant enregistrement (limite, saisie) : rien n'est parti
  rejected,
}

class PendingMessage {
  const PendingMessage(this.text, this.status, {this.error});

  final String text;
  final PendingStatus status;
  final String? error;
}

/// Ce qui débloque la suite quand un message est refusé faute d'allocation.
enum LimitNotice {
  /// Essai sans compte épuisé : créer un compte ou se connecter
  signupRequired,

  /// Allocation du compte épuisée, ou trop de messages à la suite : attendre
  waitRequired,
}

class ChatState {
  const ChatState({
    this.messages = const [],
    this.loading = true,
    this.loadingOlder = false,
    this.hasMore = false,
    this.loadError = false,
    this.pending,
    this.failures = const {},
    this.limit,
    this.limitMessage,
  });

  /// Du plus ancien au plus récent.
  final List<ChatMessage> messages;
  final bool loading;
  final bool loadingOlder;
  final bool hasMore;
  final bool loadError;
  final PendingMessage? pending;

  /// Tours échoués : uuid du message de l'utilisateur → message d'échec.
  final Map<String, String> failures;
  final LimitNotice? limit;
  final String? limitMessage;

  bool get sending => pending?.status == PendingStatus.sending;

  ChatState copyWith({
    List<ChatMessage>? messages,
    bool? loading,
    bool? loadingOlder,
    bool? hasMore,
    bool? loadError,
    PendingMessage? Function()? pending,
    Map<String, String>? failures,
    LimitNotice? Function()? limit,
    String? Function()? limitMessage,
  }) {
    return ChatState(
      messages: messages ?? this.messages,
      loading: loading ?? this.loading,
      loadingOlder: loadingOlder ?? this.loadingOlder,
      hasMore: hasMore ?? this.hasMore,
      loadError: loadError ?? this.loadError,
      pending: pending != null ? pending() : this.pending,
      failures: failures ?? this.failures,
      limit: limit != null ? limit() : this.limit,
      limitMessage: limitMessage != null ? limitMessage() : this.limitMessage,
    );
  }
}

final chatControllerProvider = NotifierProvider.autoDispose
    .family<ChatController, ChatState, String>(ChatController.new);

/// Une conversation : lecture par pages, envoi d'un message, suivi du tour.
class ChatController extends Notifier<ChatState> {
  ChatController(this.conversationUuid);

  final String conversationUuid;

  /// Suivi d'un tour encore en cours côté serveur (décision D8).
  static const pollInterval = Duration(seconds: 2);
  static const pollAttempts = 90;

  @override
  ChatState build() {
    Future.microtask(load);

    return const ChatState();
  }

  /// Charge (ou recharge) les messages les plus récents.
  Future<void> load() async {
    state = state.copyWith(loading: true, loadError: false);
    try {
      final page = await ref
          .read(conversationRepositoryProvider)
          .messages(conversationUuid);
      state = state.copyWith(
        messages: page.items,
        hasMore: page.hasMore,
        loading: false,
        pending: () => _pendingAfterReload(page.items),
      );
    } on DioException {
      state = state.copyWith(loading: false, loadError: true);
    } on ConversationNotFoundException {
      state = state.copyWith(loading: false, loadError: true);
    }
  }

  Future<void> loadOlder() async {
    if (!state.hasMore || state.loadingOlder || state.messages.isEmpty) {
      return;
    }
    state = state.copyWith(loadingOlder: true);
    try {
      final page = await ref
          .read(conversationRepositoryProvider)
          .messages(conversationUuid, before: state.messages.first.uuid);
      state = state.copyWith(
        messages: [...page.items, ...state.messages],
        hasMore: page.hasMore,
        loadingOlder: false,
      );
    } on DioException {
      state = state.copyWith(loadingOlder: false);
    }
  }

  /// Envoie [text]. Le brouillon n'est effacé qu'une fois le message reçu par
  /// le serveur.
  Future<void> send(String text) async {
    final message = text.trim();
    if (message.isEmpty || state.sending) {
      return;
    }
    state = state.copyWith(
      pending: () => PendingMessage(message, PendingStatus.sending),
      limit: () => null,
      limitMessage: () => null,
    );

    try {
      var turn = await ref
          .read(conversationRepositoryProvider)
          .send(conversationUuid, message);
      ref.read(chatDraftsProvider.notifier).clear(conversationUuid);
      turn = await _waitForEnd(turn);
      _record(turn);
    } on SignupRequiredException catch (e) {
      _rejected(message, LimitNotice.signupRequired, e.message);
    } on MessageLimitException catch (e) {
      _rejected(message, LimitNotice.waitRequired, e.message);
    } on ApiValidationException catch (e) {
      state = state.copyWith(
        pending: () => PendingMessage(
          message,
          PendingStatus.rejected,
          error: e.fieldErrors['text'],
        ),
      );
    } on DioException catch (e) {
      // Sans réponse du serveur, le message a pu partir : on ne le renvoie pas
      // d'office, on relira la conversation.
      state = state.copyWith(
        pending: () => PendingMessage(
          message,
          e.response == null
              ? PendingStatus.connectionLost
              : PendingStatus.rejected,
        ),
      );
    }
  }

  /// Renvoie le message d'un tour échoué (nouveau tour).
  Future<void> retry(ChatMessage failed) => send(failed.text);

  /// Abandonne un message qui n'est pas parti.
  void dismissPending() {
    state = state.copyWith(pending: () => null);
  }

  void _rejected(String message, LimitNotice notice, String limitMessage) {
    // Rien n'a été enregistré : le texte reste dans la zone de saisie
    ref.read(chatDraftsProvider.notifier).save(conversationUuid, message);
    state = state.copyWith(
      pending: () => null,
      limit: () => notice,
      limitMessage: () => limitMessage,
    );
  }

  Future<Turn> _waitForEnd(Turn turn) async {
    var current = turn;
    for (var i = 0; !current.finished && i < pollAttempts; i++) {
      await Future<void>.delayed(pollInterval);
      current = await ref.read(conversationRepositoryProvider).turn(turn.uuid);
    }

    return current;
  }

  void _record(Turn turn) {
    final answer = turn.assistantMessage;
    final failure = turn.failureMessage;
    state = state.copyWith(
      messages: [...state.messages, turn.userMessage, ?answer],
      pending: () => null,
      failures: turn.status == TurnStatus.failed && failure != null
          ? {...state.failures, turn.userMessage.uuid: failure}
          : null,
    );
  }

  /// Après une coupure, le message est-il arrivé ? Oui si la conversation
  /// relue se termine par lui : on retire alors le message en attente.
  PendingMessage? _pendingAfterReload(List<ChatMessage> messages) {
    final pending = state.pending;
    if (pending == null || pending.status != PendingStatus.connectionLost) {
      return pending;
    }
    final userMessages = messages.where((m) => m.role == MessageRole.user);
    final arrived =
        userMessages.isNotEmpty && userMessages.last.text == pending.text;

    return arrived ? null : pending;
  }
}
