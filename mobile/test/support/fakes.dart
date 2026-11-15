import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hector_mobile/l10n/app_localizations.dart';
import 'package:hector_mobile/src/data/api.dart';
import 'package:hector_mobile/src/data/auth/token_storage.dart';
import 'package:hector_mobile/src/theme.dart';

/// Stockage en mémoire pour les tests (pas de Keychain en environnement de test).
class InMemoryTokenStorage implements TokenStorage {
  AuthTokens? tokens;
  String? userJson;

  @override
  Future<AuthTokens?> read() async => tokens;

  @override
  Future<void> write(AuthTokens newTokens) async => tokens = newTokens;

  @override
  Future<String?> readUserJson() async => userJson;

  @override
  Future<void> writeUserJson(String json) async => userJson = json;

  @override
  Future<void> clear() async {
    tokens = null;
    userJson = null;
  }
}

/// Adaptateur HTTP scripté : chaque requête est résolue par [handler] et
/// conservée dans [requests] pour les assertions.
class ScriptedAdapter implements HttpClientAdapter {
  ScriptedAdapter(this.handler);

  final Future<ResponseBody> Function(RequestOptions options) handler;
  final List<RequestOptions> requests = [];

  /// Chemins appelés, dans l'ordre (ex. « POST /api/v1/auth/login »).
  List<String> get calls =>
      requests.map((r) => '${r.method} ${r.path}').toList();

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) {
    requests.add(options);

    return handler(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody jsonResponse(int status, Map<String, dynamic> body) {
  return ResponseBody.fromString(
    jsonEncode(body),
    status,
    headers: {
      Headers.contentTypeHeader: ['application/json'],
    },
  );
}

ResponseBody emptyResponse(int status) => ResponseBody.fromString('', status);

DioException offline(RequestOptions options) {
  return DioException.connectionError(
    requestOptions: options,
    reason: 'hors ligne',
  );
}

const profileJson = <String, dynamic>{
  'uuid': 'u1',
  'email': 'jean@chantier.fr',
  'fullName': 'Jean Dupont',
  'roles': ['ROLE_USER'],
  'professional': true,
  'companyName': 'Élec Chantier',
  'siret': '12345678901234',
  'vatNumber': 'FR12345678901',
};

/// Conteneur Riverpod branché sur un stockage en mémoire et une API scriptée :
/// tout le câblage réel (Dio « nu », Dio authentifié, interceptor) est exercé.
ProviderContainer buildContainer(
  InMemoryTokenStorage storage,
  ScriptedAdapter adapter,
) {
  return ProviderContainer(
    overrides: [
      tokenStorageProvider.overrideWithValue(storage),
      httpClientAdapterProvider.overrideWithValue(adapter),
    ],
  );
}

/// Enveloppe un écran dans l'application de test (thème + traductions).
Widget testApp(ProviderContainer container, Widget screen) {
  return UncontrolledProviderScope(
    container: container,
    child: MaterialApp(
      theme: HectorTheme.light(),
      locale: const Locale('fr'),
      supportedLocales: const [Locale('fr')],
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: screen,
    ),
  );
}
