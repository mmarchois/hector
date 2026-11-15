import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config.dart';
import '../state/auth_controller.dart';
import 'auth/auth_interceptor.dart';
import 'auth/auth_repository.dart';
import 'auth/token_storage.dart';
import 'profile/profile_repository.dart';

/// Transport HTTP des clients Dio : null = transport natif de la plateforme.
/// Les tests le remplacent par un adaptateur scripté, ce qui exerce tout le
/// câblage réel (Bearer, refresh, rejeu) sans réseau.
final httpClientAdapterProvider = Provider<HttpClientAdapter?>((ref) => null);

Dio _newDio(Ref ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 30),
      headers: {'Accept': 'application/json'},
    ),
  );
  final adapter = ref.watch(httpClientAdapterProvider);
  if (adapter != null) {
    dio.httpClientAdapter = adapter;
  }

  return dio;
}

final tokenStorageProvider = Provider<TokenStorage>(
  (ref) => SecureTokenStorage(),
);

/// Dio « nu » : routes publiques d'auth et rejeu post-refresh.
final bareDioProvider = Provider<Dio>(_newDio);

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(ref.watch(bareDioProvider)),
);

/// Dio authentifié : Bearer automatique + refresh-retry sur 401.
final apiDioProvider = Provider<Dio>((ref) {
  final dio = _newDio(ref);
  dio.interceptors.add(
    AuthInterceptor(
      ref.watch(tokenStorageProvider),
      ref.watch(authRepositoryProvider),
      ref.watch(bareDioProvider),
      onSessionExpired: () =>
          ref.read(authControllerProvider.notifier).onSessionExpired(),
    ),
  );

  return dio;
});

final profileRepositoryProvider = Provider<ProfileRepository>(
  (ref) => ProfileRepository(ref.watch(apiDioProvider)),
);
