/// Conditions d'utilisation en vigueur (GET /api/v1/terms-of-use), à accepter
/// avant d'utiliser l'application, avec ou sans compte.
class TermsOfUse {
  const TermsOfUse({required this.version, required this.content});

  factory TermsOfUse.fromJson(Map<String, dynamic> json) {
    return TermsOfUse(
      version: json['version'] as String,
      content: json['content'] as String,
    );
  }

  /// Version renvoyée à l'API pour dire laquelle a été acceptée.
  final String version;

  /// Texte en Markdown simple (titres, listes, italique).
  final String content;
}
