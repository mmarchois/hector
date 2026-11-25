import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/api.dart';
import '../data/api_errors.dart';
import '../data/auth/token_storage.dart';
import '../domain/user_profile.dart';

/// États de session : inconnu (démarrage) → connecté ou déconnecté.
sealed class AuthState {
  const AuthState();
}

class AuthUnknown extends AuthState {
  const AuthUnknown();
}

/// Sans session. Les conditions d'utilisation s'acceptent sur l'écran de
/// bienvenue, avant d'ouvrir un compte invité, de se connecter ou de s'inscrire.
class AuthSignedOut extends AuthState {
  const AuthSignedOut({this.acceptedTermsOfUseVersion});

  /// Version des conditions acceptée pendant cette visite, sinon null.
  final String? acceptedTermsOfUseVersion;
}

/// Session ouverte, avec un compte complet ou un compte invité ([UserProfile.guest]).
class AuthSignedIn extends AuthState {
  const AuthSignedIn(this.user);

  final UserProfile user;
}

final authControllerProvider = NotifierProvider<AuthController, AuthState>(
  AuthController.new,
);

class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() {
    return const AuthUnknown();
  }

  /// Au démarrage : session restaurée si des tokens existent. Hors ligne
  /// (cas nominal sur chantier), la session est restaurée depuis le profil
  /// en cache, SANS appel réseau abouti : l'interceptor rafraîchira les tokens
  /// au retour du réseau. Seul un rejet explicite du serveur (401/403 sur le
  /// refresh) déconnecte.
  Future<void> restoreSession() async {
    final storage = ref.read(tokenStorageProvider);
    final tokens = await storage.read();
    if (tokens == null) {
      state = const AuthSignedOut();
      return;
    }

    final repository = ref.read(authRepositoryProvider);
    try {
      state = AuthSignedIn(await _fetchAndCacheUser(tokens.accessToken));
    } on Exception catch (error) {
      // Panne réseau : on ne consomme pas le refresh token (usage unique)
      // sur un lien instable, la session est restaurée du cache.
      if (_isNetworkError(error)) {
        state = AuthSignedIn(await _cachedUser(storage));
        return;
      }
      // Access token expiré : on tente un refresh complet avant de trancher.
      try {
        final newTokens = await repository.refresh(tokens.refreshToken);
        await storage.write(newTokens);
        state = AuthSignedIn(await _fetchAndCacheUser(newTokens.accessToken));
      } on Exception catch (refreshError) {
        if (_isSessionRevoked(refreshError)) {
          // Le serveur rejette explicitement la session : déconnexion.
          await storage.clear();
          state = const AuthSignedOut();
          return;
        }
        // Panne réseau ou erreur serveur transitoire : tokens conservés,
        // session restaurée du cache, nouvel essai à la prochaine requête.
        state = AuthSignedIn(await _cachedUser(storage));
      }
    }
  }

  /// Vraie panne réseau (timeout, socket…) : le serveur n'a pas répondu.
  bool _isNetworkError(Object error) {
    return error is DioException && error.response == null;
  }

  /// Rejet explicite de la session par le serveur (refresh token invalide).
  bool _isSessionRevoked(Object error) {
    final status = error is DioException ? error.response?.statusCode : null;

    return status == 401 || status == 403;
  }

  /// /auth/me + mise en cache du profil pour les démarrages hors ligne.
  Future<UserProfile> _fetchAndCacheUser(String accessToken) async {
    final user = await ref.read(authRepositoryProvider).me(accessToken);
    await _cacheUser(user);

    return user;
  }

  Future<void> _cacheUser(UserProfile user) {
    return ref
        .read(tokenStorageProvider)
        .writeUserJson(jsonEncode(user.toJson()));
  }

  /// Profil depuis le cache local ; profil minimal si le cache est absent ou
  /// illisible, complété au prochain démarrage en ligne.
  Future<UserProfile> _cachedUser(TokenStorage storage) async {
    final json = await storage.readUserJson();
    if (json != null) {
      try {
        return UserProfile.fromJson(jsonDecode(json) as Map<String, dynamic>);
      } on Object {
        // Cache illisible : on retombe sur le profil minimal.
      }
    }

    return UserProfile.empty;
  }

  /// Lève InvalidCredentialsException (401) ou DioException (réseau).
  /// Un invité qui se connecte à son compte y retrouve ses conversations :
  /// son jeton d'invité sert à demander le rattachement (question P11).
  Future<void> signIn({required String email, required String password}) async {
    final current = state;
    final guestTokens = current is AuthSignedIn && current.user.guest
        ? await ref.read(tokenStorageProvider).read()
        : null;

    final repository = ref.read(authRepositoryProvider);
    final tokens = await repository.login(email: email, password: password);
    await ref.read(tokenStorageProvider).write(tokens);

    if (guestTokens != null) {
      try {
        await repository.mergeGuest(
          accessToken: tokens.accessToken,
          guestRefreshToken: guestTokens.refreshToken,
        );
      } on DioException {
        // Rattachement impossible (réseau, session d'invité expirée) : la
        // connexion reste valable ; l'essai sera purgé avec le compte invité.
      }
    }

    state = AuthSignedIn(await _fetchAndCacheUser(tokens.accessToken));
  }

  /// Conditions d'utilisation acceptées sur l'écran de bienvenue.
  void acceptTermsOfUse(String version) {
    state = AuthSignedOut(acceptedTermsOfUseVersion: version);
  }

  /// Retire une acceptation devenue caduque (conditions modifiées entre-temps).
  void withdrawTermsOfUse() {
    state = const AuthSignedOut();
  }

  /// Essai sans compte : ouvre un compte invité, conditions déjà acceptées.
  /// Lève [TermsOfUseOutdatedException], [TooManyGuestAccountsException] ou
  /// DioException (réseau).
  Future<void> startAsGuest() async {
    final current = state;
    final version = current is AuthSignedOut
        ? current.acceptedTermsOfUseVersion
        : null;
    if (version == null) {
      throw StateError('Conditions d\'utilisation non acceptées.');
    }

    final tokens = await ref
        .read(authRepositoryProvider)
        .createGuest(acceptedTermsOfUseVersion: version);
    await ref.read(tokenStorageProvider).write(tokens);
    state = AuthSignedIn(await _fetchAndCacheUser(tokens.accessToken));
  }

  /// Crée le compte puis connecte directement l'utilisateur. Pour un invité,
  /// convertit son compte : il garde ses données et sa session.
  Future<void> register({
    required String fullName,
    required String email,
    required String password,
    required bool professional,
    String? companyName,
    String? siret,
    String? vatNumber,
  }) async {
    final current = state;
    if (current is AuthSignedIn && current.user.guest) {
      final user = await ref
          .read(profileRepositoryProvider)
          .convertGuest(
            fullName: fullName,
            email: email,
            password: password,
            professional: professional,
            companyName: companyName,
            siret: siret,
            vatNumber: vatNumber,
          );
      await _cacheUser(user);
      state = AuthSignedIn(user);

      return;
    }

    await ref
        .read(authRepositoryProvider)
        .register(
          fullName: fullName,
          email: email,
          password: password,
          professional: professional,
          companyName: companyName,
          siret: siret,
          vatNumber: vatNumber,
          acceptedTermsOfUseVersion: current is AuthSignedOut
              ? current.acceptedTermsOfUseVersion
              : null,
        );
    await signIn(email: email, password: password);
  }

  /// Met à jour le profil et rafraîchit l'état de session.
  Future<void> updateProfile({
    required String fullName,
    required String email,
    required bool professional,
    String? companyName,
    String? siret,
    String? vatNumber,
    String? currentPassword,
    String? newPassword,
  }) async {
    final user = await ref
        .read(profileRepositoryProvider)
        .update(
          fullName: fullName,
          email: email,
          professional: professional,
          companyName: companyName,
          siret: siret,
          vatNumber: vatNumber,
          currentPassword: currentPassword,
          newPassword: newPassword,
        );
    await _cacheUser(user);
    state = AuthSignedIn(user);

    // Un changement de mot de passe révoque tous les refresh tokens côté
    // serveur : on rouvre aussitôt une session pour ne pas être déconnecté à
    // l'expiration de l'access token.
    if (newPassword != null) {
      await _renewTokens(email: user.email, password: newPassword);
    }
  }

  Future<void> _renewTokens({
    required String email,
    required String password,
  }) async {
    try {
      final tokens = await ref
          .read(authRepositoryProvider)
          .login(email: email, password: password);
      await ref.read(tokenStorageProvider).write(tokens);
    } on Exception {
      // Sans réseau à cet instant, la session en cours reste utilisable
      // jusqu'à l'expiration de l'access token ; l'utilisateur se reconnectera.
    }
  }

  /// Suppression RGPD du compte, puis déconnexion locale.
  Future<void> deleteAccount() async {
    await ref.read(profileRepositoryProvider).delete();
    await ref.read(tokenStorageProvider).clear();
    state = const AuthSignedOut();
  }

  /// Déconnexion : le refresh token est révoqué côté serveur quand le réseau
  /// le permet, et la session locale est toujours effacée.
  Future<void> signOut() async {
    final storage = ref.read(tokenStorageProvider);
    final tokens = await storage.read();
    if (tokens != null) {
      try {
        await ref.read(authRepositoryProvider).invalidate(tokens.refreshToken);
      } on Exception {
        // Hors ligne : le token expirera de lui-même côté serveur.
      }
    }
    await storage.clear();
    state = const AuthSignedOut();
  }

  /// Appelé par l'AuthInterceptor quand le refresh échoue (session révoquée).
  void onSessionExpired() {
    state = const AuthSignedOut();
  }
}
