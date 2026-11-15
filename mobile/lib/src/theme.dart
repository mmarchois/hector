import 'package:flutter/material.dart';

/// Couleurs de marque Hector : bleu de travail et ambre de signalisation.
abstract final class HectorColors {
  static const blue = Color(0xFF1B55B0);
  static const amber = Color(0xFFFFB020);
  static const ink = Color(0xFF0F1B2D);
}

/// Thème pensé pour le chantier : contrastes francs (plein soleil), champs et
/// boutons hauts (doigts gantés), clair ou sombre selon le réglage du téléphone.
abstract final class HectorTheme {
  static ThemeData light() => _build(Brightness.light);

  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isLight = brightness == Brightness.light;
    final scheme =
        ColorScheme.fromSeed(
          seedColor: HectorColors.blue,
          brightness: brightness,
        ).copyWith(
          primary: isLight ? HectorColors.blue : const Color(0xFF9DBDFF),
          secondary: HectorColors.amber,
          onSecondary: HectorColors.ink,
        );
    const radius = BorderRadius.all(Radius.circular(12));

    return ThemeData(
      colorScheme: scheme,
      useMaterial3: true,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        backgroundColor: scheme.surface,
        surfaceTintColor: Colors.transparent,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: scheme.surfaceContainerHighest.withValues(alpha: 0.5),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 18,
        ),
        border: const OutlineInputBorder(borderRadius: radius),
        enabledBorder: OutlineInputBorder(
          borderRadius: radius,
          borderSide: BorderSide(color: scheme.outlineVariant),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(54),
          shape: const RoundedRectangleBorder(borderRadius: radius),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(54),
          shape: const RoundedRectangleBorder(borderRadius: radius),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        margin: EdgeInsets.zero,
        color: scheme.surfaceContainerHighest.withValues(alpha: 0.5),
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(16)),
        ),
      ),
    );
  }
}

/// Emblème Hector : éclair ambre sur pastille bleu nuit.
class HectorMark extends StatelessWidget {
  const HectorMark({super.key, this.size = 72});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: HectorColors.ink,
        borderRadius: BorderRadius.circular(size * 0.28),
      ),
      child: Icon(Icons.bolt, color: HectorColors.amber, size: size * 0.62),
    );
  }
}
