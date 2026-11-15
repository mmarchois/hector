import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'presentation/home_screen.dart';
import 'presentation/login_screen.dart';
import 'presentation/profile_screen.dart';
import 'presentation/register_screen.dart';
import 'state/auth_controller.dart';
import 'theme.dart';

/// Routes accessibles sans session.
const _publicPaths = {'/connexion', '/inscription'};

const _splashPath = '/demarrage';

/// Destination imposée par l'état de session, ou null si [location] convient.
String? redirectFor(AuthState auth, String location) {
  switch (auth) {
    case AuthUnknown():
      // Session en cours de restauration : écran d'attente
      return location == _splashPath ? null : _splashPath;
    case AuthSignedOut():
      return _publicPaths.contains(location) ? null : '/connexion';
    case AuthSignedIn():
      final awayFromApp =
          _publicPaths.contains(location) || location == _splashPath;

      return awayFromApp ? '/' : null;
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
