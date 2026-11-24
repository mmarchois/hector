import 'package:dio/dio.dart';

import '../../domain/conversation.dart';
import '../api_errors.dart';

/// Conversations, messages et tours. Dio authentifié (Bearer + refresh).
class ConversationRepository {
  ConversationRepository(this._dio);

  final Dio _dio;

  /// Un tour exécute l'appel au modèle de langage dans la requête : délai de
  /// réception propre à cet envoi (l'API tolère jusqu'à 240 s).
  static const sendTimeout = Duration(minutes: 4);

  Future<Conversation> create() async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/api/v1/conversations',
    );

    return Conversation.fromJson(response.data!);
  }

  Future<ConversationPage> list({int page = 1}) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/api/v1/conversations',
      queryParameters: {'page': page},
    );

    return ConversationPage.fromJson(response.data!);
  }

  /// Les messages les plus récents, ou ceux qui précèdent [before].
  /// Lève [ConversationNotFoundException] (404).
  Future<MessagePage> messages(
    String conversationUuid, {
    String? before,
    int limit = 30,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        '/api/v1/conversations/$conversationUuid/messages',
        queryParameters: {'limit': limit, 'before': ?before},
      );

      return MessagePage.fromJson(response.data!);
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        throw ConversationNotFoundException();
      }
      rethrow;
    }
  }

  /// Envoie un message et renvoie le tour, terminé ou en cours.
  /// Lève [SignupRequiredException], [FreeAllowanceExhaustedException],
  /// [TooManyMessagesException] (429), [ApiValidationException] (422),
  /// [ConversationNotFoundException] (404) ou DioException (réseau).
  Future<Turn> send(String conversationUuid, String text) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        '/api/v1/conversations/$conversationUuid/messages',
        data: {'text': text},
        options: Options(receiveTimeout: sendTimeout),
      );

      return Turn.fromJson(response.data!);
    } on DioException catch (e) {
      throw _sendErrorFrom(e);
    }
  }

  Future<Turn> turn(String turnUuid) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/api/v1/turns/$turnUuid',
    );

    return Turn.fromJson(response.data!);
  }

  Exception _sendErrorFrom(DioException e) {
    final status = e.response?.statusCode;
    final data = e.response?.data;
    final message = data is Map<String, dynamic>
        ? data['message'] as String? ?? ''
        : '';

    if (status == 429) {
      return switch (apiErrorReason(e)) {
        'signup_required' => SignupRequiredException(message),
        'free_allowance_exhausted' => FreeAllowanceExhaustedException(message),
        _ => TooManyMessagesException(message),
      };
    }
    if (status == 404) {
      return ConversationNotFoundException();
    }

    return ApiValidationException.fromDio(e) ?? e;
  }
}
