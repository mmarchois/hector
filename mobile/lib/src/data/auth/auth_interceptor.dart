import 'dart:async';

import 'package:dio/dio.dart';

import 'auth_repository.dart';
import 'token_storage.dart';

/// Ajoute le Bearer sur chaque requête et rejoue UNE fois la requête après
/// un refresh si l'API répond 401 (access token de 15 min expiré).
/// Si le refresh est rejeté par le serveur (401/403) : tokens purgés +
/// [onSessionExpired] (déconnexion). Une panne réseau pendant le refresh
/// conserve les tokens (hors ligne = cas nominal sur chantier).
class AuthInterceptor extends Interceptor {
  AuthInterceptor(
    this._storage,
    this._repository,
    this._retryDio, {
    this.onSessionExpired,
  });

  final TokenStorage _storage;
  final AuthRepository _repository;

  /// Dio utilisé pour rejouer la requête d'origine (sans repasser par cet
  /// interceptor, pour ne jamais boucler).
  final Dio _retryDio;

  final void Function()? onSessionExpired;

  /// Un seul refresh en vol à la fois : les 401 concurrents attendent le même
  /// futur (la rotation à usage unique invaliderait les refresh parallèles).
  Future<AuthTokens?>? _pendingRefresh;

  static const _retriedKey = 'hector_retried';

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final tokens = await _storage.read();
    if (tokens != null) {
      options.headers['Authorization'] = 'Bearer ${tokens.accessToken}';
    }
    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final alreadyRetried = err.requestOptions.extra[_retriedKey] == true;
    if (err.response?.statusCode != 401 || alreadyRetried) {
      handler.next(err);
      return;
    }

    final newTokens = await _refreshOnce();
    if (newTokens == null) {
      handler.next(err);
      return;
    }

    try {
      final options = err.requestOptions
        ..headers['Authorization'] = 'Bearer ${newTokens.accessToken}'
        ..extra[_retriedKey] = true;
      final response = await _retryDio.fetch<dynamic>(options);
      handler.resolve(response);
    } on DioException catch (retryError) {
      handler.next(retryError);
    }
  }

  Future<AuthTokens?> _refreshOnce() {
    return _pendingRefresh ??= _doRefresh().whenComplete(() {
      _pendingRefresh = null;
    });
  }

  Future<AuthTokens?> _doRefresh() async {
    final tokens = await _storage.read();
    if (tokens == null) {
      return null;
    }

    try {
      final newTokens = await _repository.refresh(tokens.refreshToken);
      await _storage.write(newTokens);

      return newTokens;
    } on DioException catch (e) {
      // Purge uniquement sur rejet explicite du serveur (session révoquée).
      // Une panne réseau (timeout, socket) conserve les tokens : ils restent
      // valides pour une nouvelle tentative au retour du réseau.
      final status = e.response?.statusCode;
      if (status == 401 || status == 403) {
        await _storage.clear();
        onSessionExpired?.call();
      }

      return null;
    }
  }
}
