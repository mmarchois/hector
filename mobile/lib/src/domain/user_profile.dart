/// Profil de l'utilisateur authentifié (réponse de GET/PUT /api/v1/auth/me).
class UserProfile {
  const UserProfile({
    required this.uuid,
    required this.email,
    required this.fullName,
    required this.roles,
    required this.professional,
    this.guest = false,
    this.companyName,
    this.siret,
    this.vatNumber,
  });

  factory UserProfile.fromJson(Map<String, dynamic> json) {
    return UserProfile(
      uuid: json['uuid'] as String,
      // Un invité n'a ni email ni nom : chaînes vides côté application
      email: json['email'] as String? ?? '',
      fullName: json['fullName'] as String? ?? '',
      roles: (json['roles'] as List<dynamic>).cast<String>(),
      professional: json['professional'] as bool? ?? false,
      guest: json['guest'] as bool? ?? false,
      companyName: json['companyName'] as String?,
      siret: json['siret'] as String?,
      vatNumber: json['vatNumber'] as String?,
    );
  }

  /// Profil minimal utilisé quand le cache local est absent ou illisible.
  static const empty = UserProfile(
    uuid: '',
    email: '',
    fullName: '',
    roles: [],
    professional: false,
  );

  final String uuid;
  final String email;
  final String fullName;
  final List<String> roles;
  final bool professional;

  /// Compte ouvert sans inscription (essai sans compte) : il peut créer un
  /// compte sans rien perdre, mais n'a pas de profil à modifier.
  final bool guest;
  final String? companyName;
  final String? siret;
  final String? vatNumber;

  /// Sérialisation miroir de [UserProfile.fromJson] : sert à mettre le profil
  /// en cache local (restauration de session au démarrage hors ligne).
  Map<String, dynamic> toJson() {
    return {
      'uuid': uuid,
      'guest': guest,
      'email': email,
      'fullName': fullName,
      'roles': roles,
      'professional': professional,
      'companyName': companyName,
      'siret': siret,
      'vatNumber': vatNumber,
    };
  }
}
