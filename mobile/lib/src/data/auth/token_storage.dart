import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Couple de tokens renvoyé par /api/v1/auth/login et /token/refresh.
class AuthTokens {
  const AuthTokens({required this.accessToken, required this.refreshToken});

  final String accessToken;
  final String refreshToken;
}

/// Abstraction du stockage des tokens (fake en mémoire dans les tests).
/// Conserve aussi le dernier profil connu (JSON) pour restaurer la session
/// au démarrage hors ligne, sans appel réseau.
abstract interface class TokenStorage {
  Future<AuthTokens?> read();
  Future<void> write(AuthTokens tokens);
  Future<String?> readUserJson();
  Future<void> writeUserJson(String json);
  Future<void> clear();
}

/// Stockage sécurisé natif : Keychain (iOS) / Keystore (Android).
class SecureTokenStorage implements TokenStorage {
  SecureTokenStorage([FlutterSecureStorage? storage])
    : _storage = storage ?? const FlutterSecureStorage();

  static const _accessKey = 'hector_access_token';
  static const _refreshKey = 'hector_refresh_token';
  static const _userKey = 'hector_user_profile';

  final FlutterSecureStorage _storage;

  @override
  Future<AuthTokens?> read() async {
    final access = await _storage.read(key: _accessKey);
    final refresh = await _storage.read(key: _refreshKey);
    if (access == null || refresh == null) {
      return null;
    }

    return AuthTokens(accessToken: access, refreshToken: refresh);
  }

  @override
  Future<void> write(AuthTokens tokens) async {
    await _storage.write(key: _accessKey, value: tokens.accessToken);
    await _storage.write(key: _refreshKey, value: tokens.refreshToken);
  }

  @override
  Future<String?> readUserJson() {
    return _storage.read(key: _userKey);
  }

  @override
  Future<void> writeUserJson(String json) {
    return _storage.write(key: _userKey, value: json);
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: _accessKey);
    await _storage.delete(key: _refreshKey);
    await _storage.delete(key: _userKey);
  }
}
