import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/src/data/auth/auth_interceptor.dart';
import 'package:hector_mobile/src/data/auth/auth_repository.dart';
import 'package:hector_mobile/src/data/auth/token_storage.dart';

import 'support/fakes.dart';

void main() {
  const expired = AuthTokens(accessToken: 'expired', refreshToken: 'refresh-1');

  /// Dio authentifié dont toutes les requêtes (API, refresh, rejeu) passent
  /// par le même adaptateur scripté.
  ({Dio dio, List<String> expirations}) buildDio(
    InMemoryTokenStorage storage,
    ScriptedAdapter adapter,
  ) {
    final bare = Dio(BaseOptions(baseUrl: 'https://example.test'))
      ..httpClientAdapter = adapter;
    final expirations = <String>[];
    final dio = Dio(BaseOptions(baseUrl: 'https://example.test'))
      ..httpClientAdapter = adapter
      ..interceptors.add(
        AuthInterceptor(
          storage,
          AuthRepository(bare),
          bare,
          onSessionExpired: () => expirations.add('expired'),
        ),
      );

    return (dio: dio, expirations: expirations);
  }

  /// API qui n'accepte que le token « fresh » et sait le délivrer par refresh.
  Future<ResponseBody> apiAcceptingFreshToken(RequestOptions options) async {
    if (options.path.contains('/token/refresh')) {
      return jsonResponse(200, {
        'token': 'fresh',
        'refresh_token': 'refresh-2',
      });
    }

    return options.headers['Authorization'] == 'Bearer fresh'
        ? jsonResponse(200, profileJson)
        : jsonResponse(401, {'code': 401, 'message': 'Expired JWT Token'});
  }

  test('ajoute le Bearer aux requêtes', () async {
    final storage = InMemoryTokenStorage()
      ..tokens = const AuthTokens(accessToken: 'fresh', refreshToken: 'r');
    final adapter = ScriptedAdapter(apiAcceptingFreshToken);
    final (:dio, :expirations) = buildDio(storage, adapter);

    final response = await dio.get<Map<String, dynamic>>('/api/v1/auth/me');

    expect(response.statusCode, 200);
    expect(adapter.requests.single.headers['Authorization'], 'Bearer fresh');
    expect(expirations, isEmpty);
  });

  test('401 : refresh puis rejeu de la requête, une seule fois', () async {
    final storage = InMemoryTokenStorage()..tokens = expired;
    final adapter = ScriptedAdapter(apiAcceptingFreshToken);
    final (:dio, :expirations) = buildDio(storage, adapter);

    final response = await dio.get<Map<String, dynamic>>('/api/v1/auth/me');

    expect(response.data?['email'], 'jean@chantier.fr');
    expect(adapter.calls, [
      'GET /api/v1/auth/me',
      'POST /api/v1/auth/token/refresh',
      'GET /api/v1/auth/me',
    ]);
    expect(storage.tokens?.accessToken, 'fresh');
    expect(storage.tokens?.refreshToken, 'refresh-2');
    expect(expirations, isEmpty);
  });

  test('401 simultanés : un seul refresh (rotation à usage unique)', () async {
    final storage = InMemoryTokenStorage()..tokens = expired;
    final adapter = ScriptedAdapter(apiAcceptingFreshToken);
    final (:dio, :expirations) = buildDio(storage, adapter);

    final responses = await Future.wait([
      dio.get<Map<String, dynamic>>('/api/v1/auth/me'),
      dio.get<Map<String, dynamic>>('/api/v1/auth/me'),
      dio.get<Map<String, dynamic>>('/api/v1/auth/me'),
    ]);

    expect(responses.map((r) => r.statusCode), everyElement(200));
    final refreshes = adapter.calls.where((c) => c.contains('/token/refresh'));
    expect(refreshes, hasLength(1));
    expect(expirations, isEmpty);
  });

  test('refresh rejeté : tokens purgés et session expirée signalée', () async {
    final storage = InMemoryTokenStorage()..tokens = expired;
    final adapter = ScriptedAdapter(
      (options) async => jsonResponse(401, {'code': 401, 'message': 'Invalid'}),
    );
    final (:dio, :expirations) = buildDio(storage, adapter);

    await expectLater(
      dio.get<Map<String, dynamic>>('/api/v1/auth/me'),
      throwsA(
        isA<DioException>().having(
          (e) => e.response?.statusCode,
          'status',
          401,
        ),
      ),
    );

    expect(storage.tokens, isNull);
    expect(expirations, ['expired']);
  });

  test('panne réseau pendant le refresh : tokens conservés', () async {
    final storage = InMemoryTokenStorage()..tokens = expired;
    final adapter = ScriptedAdapter((options) async {
      if (options.path.contains('/token/refresh')) {
        throw offline(options);
      }

      return jsonResponse(401, {'code': 401, 'message': 'Expired JWT Token'});
    });
    final (:dio, :expirations) = buildDio(storage, adapter);

    await expectLater(
      dio.get<Map<String, dynamic>>('/api/v1/auth/me'),
      throwsA(isA<DioException>()),
    );

    expect(storage.tokens?.refreshToken, 'refresh-1');
    expect(expirations, isEmpty);
  });

  test('401 persistant après refresh : pas de boucle', () async {
    final storage = InMemoryTokenStorage()..tokens = expired;
    final adapter = ScriptedAdapter((options) async {
      if (options.path.contains('/token/refresh')) {
        return jsonResponse(200, {
          'token': 'fresh',
          'refresh_token': 'refresh-2',
        });
      }

      return jsonResponse(401, {'code': 401, 'message': 'Unauthorized'});
    });
    final (:dio, :expirations) = buildDio(storage, adapter);

    await expectLater(
      dio.get<Map<String, dynamic>>('/api/v1/auth/me'),
      throwsA(isA<DioException>()),
    );

    expect(adapter.calls, [
      'GET /api/v1/auth/me',
      'POST /api/v1/auth/token/refresh',
      'GET /api/v1/auth/me',
    ]);
    expect(expirations, isEmpty);
  });
}
