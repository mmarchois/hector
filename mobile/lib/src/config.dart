/// Configuration d'environnement, injectée au build :
///   flutter run --dart-define=API_BASE_URL=http://localhost:8000
/// Sans define : API locale (aucun domaine de production pour l'instant).
/// Émulateur Android : l'hôte de la machine est 10.0.2.2, pas localhost.
abstract final class AppConfig {
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://localhost:8000',
  );
}
