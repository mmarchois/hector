import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'presentation/home_screen.dart';
import 'presentation/login_screen.dart';
import 'presentation/profile_screen.dart';
import 'presentation/register_screen.dart';
import 'presentation/welcome_screen.dart';
import 'state/auth_controller.dart';
import 'theme.dart';

const _welcomePath = '/bienvenue';

/// Écrans de compte : accessibles sans session une fois les conditions
/// acceptées, et à un invité qui crée son compte ou rejoint le sien.
const _accountPaths = {'/connexion', '/inscription'};

const _splashPath = '/demarrage';

/// Destination imposée par l'état de session, ou null si [location] convient.
String? redirectFor(AuthState auth, String location) {
  switch (auth) {
    case AuthUnknown():
      // Session en cours de restauration : écran d'attente
      return location == _splashPath ? null : _splashPath;
    case AuthSignedOut(:final acceptedTermsOfUseVersion):
      if (location == _welcomePath) {
        return null;
      }
      // Conditions d'utilisation d'abord, quel que soit le chemin choisi ensuite
      final accepted = acceptedTermsOfUseVersion != null;

      return accepted && _accountPaths.contains(location) ? null : _welcomePath;
    case AuthSignedIn(:final user):
      if (location == _splashPath || location == _welcomePath) {
        return '/';
      }
      if (_accountPaths.contains(location)) {
        return user.guest ? null : '/';
      }

      return null;
  }
}

final routerProvider = Provider<GoRouter>((ref) {
  // Le redirect est réévalué à chaque changement d'état d'auth.
  final refresh = ValueNotifier(0);
  ref.listen(authControllerProvider, (_, _) => refresh.value++);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: refresh,
    redirect: (context, state) =>
        redirectFor(ref.read(authControllerProvider), state.matchedLocation),
    routes: [
      GoRoute(path: '/', builder: (context, state) => const HomeScreen()),
      GoRoute(
        path: _splashPath,
        builder: (context, state) => const _SplashScreen(),
      ),
      GoRoute(
        path: _welcomePath,
        builder: (context, state) => const WelcomeScreen(),
      ),
      GoRoute(
        path: '/connexion',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/inscription',
        builder: (context, state) => const RegisterScreen(),
      ),
      GoRoute(
        path: '/profil',
        builder: (context, state) => const ProfileScreen(),
      ),
    ],
  );
});

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            HectorMark(),
            SizedBox(height: 32),
            SizedBox(
              width: 24,
              height: 24,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          ],
        ),
      ),
    );
  }
}
