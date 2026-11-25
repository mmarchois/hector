import 'package:flutter/cupertino.dart' show CupertinoPageTransitionsBuilder;
import 'package:flutter/material.dart';

/// Couleurs de marque Hector : noir, blanc, et un jaune électrique pour seul
/// accent (l'éclair de l'emblème, l'envoi d'un message, une recommandation).
abstract final class HectorColors {
  static const black = Color(0xFF0B0B0F);
  static const white = Color(0xFFFFFFFF);

  /// Jaune électrique, toujours avec un contenu noir.
  static const volt = Color(0xFFFFD60A);
}

/// Police embarquée (assets/fonts, licence OFL).
abstract final class HectorFonts {
  static const sans = 'Plus Jakarta Sans';
}

/// Teinte d'une pastille ou d'un encart : ce qu'elle dit de l'énoncé.
enum Tone { neutral, positive, caution, info, danger, accent }

/// Valeurs propres à Hector, que le thème Material ne porte pas.
@immutable
class HectorTokens extends ThemeExtension<HectorTokens> {
  const HectorTokens({
    required this.soft,
    required this.softer,
    required this.muted,
    required this.hairline,
    required this.accent,
    required this.onAccent,
    required this.tones,
  });

  /// Surface douce : champ, carte, liste groupée, message de l'utilisateur.
  final Color soft;

  /// Un cran plus marqué : élément inactif posé sur une surface douce.
  final Color softer;

  /// Texte secondaire.
  final Color muted;

  /// Séparateur.
  final Color hairline;

  /// Accent et son contenu.
  final Color accent;
  final Color onAccent;

  /// Fond et texte de chaque teinte.
  final Map<Tone, (Color background, Color foreground)> tones;

  Color background(Tone tone) => tones[tone]!.$1;

  Color foreground(Tone tone) => tones[tone]!.$2;

  static const light = HectorTokens(
    soft: Color(0xFFF4F4F6),
    softer: Color(0xFFE9E9ED),
    muted: Color(0xFF6B6B76),
    hairline: Color(0xFFE6E6EA),
    accent: HectorColors.volt,
    onAccent: HectorColors.black,
    tones: {
      Tone.neutral: (Color(0xFFF0F0F3), Color(0xFF55555F)),
      Tone.positive: (Color(0xFFE4F6EB), Color(0xFF17723D)),
      Tone.caution: (Color(0xFFFFF0CC), Color(0xFF8A5A00)),
      Tone.info: (Color(0xFFE6EFFF), Color(0xFF1F5FD1)),
      Tone.danger: (Color(0xFFFFE8E4), Color(0xFFC0291B)),
      Tone.accent: (Color(0xFFFFF7D1), HectorColors.black),
    },
  );

  static const dark = HectorTokens(
    soft: Color(0xFF1B1B20),
    softer: Color(0xFF2A2A31),
    muted: Color(0xFFA0A0AB),
    hairline: Color(0xFF2A2A30),
    accent: HectorColors.volt,
    onAccent: HectorColors.black,
    tones: {
      Tone.neutral: (Color(0xFF26262C), Color(0xFFB9B9C4)),
      Tone.positive: (Color(0xFF10301D), Color(0xFF6FD99A)),
      Tone.caution: (Color(0xFF3A2A05), Color(0xFFFFCB5C)),
      Tone.info: (Color(0xFF0F2547), Color(0xFF8DB8FF)),
      Tone.danger: (Color(0xFF3F1410), Color(0xFFFF9A8D)),
      Tone.accent: (Color(0xFF2B2608), Color(0xFFF5F5F7)),
    },
  );

  static HectorTokens of(BuildContext context) {
    final theme = Theme.of(context);

    return theme.extension<HectorTokens>() ??
        (theme.brightness == Brightness.dark ? dark : light);
  }

  @override
  HectorTokens copyWith() => this;

  @override
  HectorTokens lerp(HectorTokens? other, double t) =>
      t < 0.5 || other == null ? this : other;
}

/// Thème dans les codes des applications actuelles : fond net, formes très
/// arrondies, surfaces douces plutôt que contours, boutons en pilule, un seul
/// accent. Champs et boutons restent hauts (chantier, doigts gantés), clair ou
/// sombre selon le réglage du téléphone.
abstract final class HectorTheme {
  /// Rayon des champs.
  static const fieldRadius = BorderRadius.all(Radius.circular(16));

  /// Rayon des cartes et des encarts.
  static const cardRadius = BorderRadius.all(Radius.circular(20));

  static ThemeData light() => _build(Brightness.light);

  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isLight = brightness == Brightness.light;
    final tokens = isLight ? HectorTokens.light : HectorTokens.dark;
    final scheme = isLight ? _lightScheme : _darkScheme;
    final text = _textTheme(scheme.onSurface);
    final disabled = tokens.muted.withValues(alpha: 0.6);
    const pill = StadiumBorder();

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      extensions: [tokens],
      fontFamily: HectorFonts.sans,
      textTheme: text,
      scaffoldBackgroundColor: scheme.surface,
      // Pas d'onde au toucher : un simple voile, comme sur iOS
      splashFactory: NoSplash.splashFactory,
      highlightColor: scheme.onSurface.withValues(alpha: 0.06),
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: CupertinoPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
        },
      ),
      dividerTheme: DividerThemeData(
        color: tokens.hairline,
        thickness: 1,
        space: 1,
      ),
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: scheme.surface,
        foregroundColor: scheme.onSurface,
        surfaceTintColor: Colors.transparent,
        titleTextStyle: text.titleLarge,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: tokens.soft,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 18,
        ),
        labelStyle: TextStyle(color: tokens.muted),
        floatingLabelStyle: TextStyle(
          color: tokens.muted,
          fontWeight: FontWeight.w600,
        ),
        hintStyle: TextStyle(color: tokens.muted),
        border: const OutlineInputBorder(
          borderRadius: fieldRadius,
          borderSide: BorderSide.none,
        ),
        enabledBorder: const OutlineInputBorder(
          borderRadius: fieldRadius,
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: fieldRadius,
          borderSide: BorderSide(color: scheme.onSurface, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: fieldRadius,
          borderSide: BorderSide(color: scheme.error, width: 1.5),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: fieldRadius,
          borderSide: BorderSide(color: scheme.error, width: 1.5),
        ),
      ),
      // Action principale : pilule pleine, noire en clair et blanche en sombre
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(56),
          backgroundColor: scheme.primary,
          foregroundColor: scheme.onPrimary,
          disabledBackgroundColor: tokens.soft,
          disabledForegroundColor: disabled,
          textStyle: text.labelLarge,
          shape: pill,
        ),
      ),
      // Action secondaire : pilule douce, sans contour
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(56),
          backgroundColor: tokens.soft,
          foregroundColor: scheme.onSurface,
          disabledForegroundColor: disabled,
          textStyle: text.labelLarge,
          side: BorderSide.none,
          shape: pill,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          minimumSize: const Size(48, 48),
          foregroundColor: scheme.onSurface,
          textStyle: text.labelLarge?.copyWith(fontSize: 15),
          shape: pill,
        ),
      ),
      segmentedButtonTheme: SegmentedButtonThemeData(
        style: ButtonStyle(
          minimumSize: const WidgetStatePropertyAll(Size.fromHeight(48)),
          textStyle: WidgetStatePropertyAll(
            text.labelLarge?.copyWith(fontSize: 15),
          ),
          shape: const WidgetStatePropertyAll(pill),
          side: const WidgetStatePropertyAll(BorderSide.none),
          backgroundColor: WidgetStateProperty.resolveWith(
            (states) => states.contains(WidgetState.selected)
                ? scheme.primary
                : tokens.soft,
          ),
          foregroundColor: WidgetStateProperty.resolveWith(
            (states) => states.contains(WidgetState.selected)
                ? scheme.onPrimary
                : scheme.onSurface,
          ),
        ),
      ),
      checkboxTheme: CheckboxThemeData(
        shape: const CircleBorder(),
        side: BorderSide(color: tokens.muted, width: 1.5),
        checkColor: WidgetStatePropertyAll(scheme.onPrimary),
      ),
      listTileTheme: ListTileThemeData(
        iconColor: scheme.onSurface,
        titleTextStyle: text.titleMedium,
        subtitleTextStyle: text.bodyMedium?.copyWith(color: tokens.muted),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        margin: EdgeInsets.zero,
        color: tokens.soft,
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(borderRadius: cardRadius),
      ),
      dialogTheme: DialogThemeData(
        elevation: 0,
        backgroundColor: isLight ? scheme.surface : tokens.soft,
        surfaceTintColor: Colors.transparent,
        titleTextStyle: text.headlineSmall,
        contentTextStyle: text.bodyLarge,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(28)),
        ),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        elevation: 0,
        backgroundColor: isLight ? scheme.surface : tokens.soft,
        surfaceTintColor: Colors.transparent,
        dragHandleColor: tokens.softer,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: scheme.inverseSurface,
        contentTextStyle: text.bodyMedium?.copyWith(
          color: scheme.onInverseSurface,
        ),
        shape: const RoundedRectangleBorder(borderRadius: fieldRadius),
      ),
      textSelectionTheme: TextSelectionThemeData(
        cursorColor: scheme.onSurface,
        selectionColor: tokens.accent.withValues(alpha: 0.45),
        selectionHandleColor: scheme.onSurface,
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: scheme.onSurface,
      ),
    );
  }

  static const _lightScheme = ColorScheme(
    brightness: Brightness.light,
    primary: HectorColors.black,
    onPrimary: HectorColors.white,
    primaryContainer: Color(0xFFF4F4F6),
    onPrimaryContainer: HectorColors.black,
    secondary: HectorColors.volt,
    onSecondary: HectorColors.black,
    secondaryContainer: Color(0xFFFFF7D1),
    onSecondaryContainer: HectorColors.black,
    error: Color(0xFFC0291B),
    onError: HectorColors.white,
    errorContainer: Color(0xFFFFE8E4),
    onErrorContainer: Color(0xFF5E130E),
    surface: HectorColors.white,
    onSurface: HectorColors.black,
    onSurfaceVariant: Color(0xFF6B6B76),
    surfaceContainerLowest: HectorColors.white,
    surfaceContainerLow: Color(0xFFF8F8FA),
    surfaceContainer: Color(0xFFF4F4F6),
    surfaceContainerHigh: Color(0xFFEDEDF0),
    surfaceContainerHighest: Color(0xFFE9E9ED),
    outline: Color(0xFFB4B4BD),
    outlineVariant: Color(0xFFE6E6EA),
    surfaceTint: Colors.transparent,
    inverseSurface: HectorColors.black,
    onInverseSurface: HectorColors.white,
    inversePrimary: HectorColors.volt,
  );

  static const _darkScheme = ColorScheme(
    brightness: Brightness.dark,
    primary: Color(0xFFF5F5F7),
    onPrimary: HectorColors.black,
    primaryContainer: Color(0xFF1B1B20),
    onPrimaryContainer: Color(0xFFF5F5F7),
    secondary: HectorColors.volt,
    onSecondary: HectorColors.black,
    secondaryContainer: Color(0xFF2B2608),
    onSecondaryContainer: Color(0xFFF5F5F7),
    error: Color(0xFFFF9A8D),
    onError: Color(0xFF3B0A05),
    errorContainer: Color(0xFF3F1410),
    onErrorContainer: Color(0xFFFFDAD3),
    surface: Color(0xFF0E0E11),
    onSurface: Color(0xFFF5F5F7),
    onSurfaceVariant: Color(0xFFA0A0AB),
    surfaceContainerLowest: Color(0xFF09090B),
    surfaceContainerLow: Color(0xFF151518),
    surfaceContainer: Color(0xFF1B1B20),
    surfaceContainerHigh: Color(0xFF232329),
    surfaceContainerHighest: Color(0xFF2A2A31),
    outline: Color(0xFF55555F),
    outlineVariant: Color(0xFF2A2A30),
    surfaceTint: Colors.transparent,
    inverseSurface: Color(0xFFF5F5F7),
    onInverseSurface: HectorColors.black,
    inversePrimary: HectorColors.black,
  );

  static TextTheme _textTheme(Color color) {
    TextStyle style(
      double size,
      FontWeight weight, {
      double height = 1.4,
      double spacing = 0,
    }) => TextStyle(
      fontFamily: HectorFonts.sans,
      fontSize: size,
      fontWeight: weight,
      height: height,
      letterSpacing: spacing,
      // L'espace de cette police est étroite : on l'ouvre un peu
      wordSpacing: size * 0.07,
      color: color,
    );

    // Titres gras et serrés, texte courant aéré
    return TextTheme(
      displayLarge: style(56, FontWeight.w800, height: 1.02, spacing: -2),
      displayMedium: style(48, FontWeight.w800, height: 1.04, spacing: -1.6),
      displaySmall: style(40, FontWeight.w800, height: 1.06, spacing: -1.3),
      headlineLarge: style(32, FontWeight.w800, height: 1.12, spacing: -0.9),
      headlineMedium: style(28, FontWeight.w800, height: 1.15, spacing: -0.7),
      headlineSmall: style(22, FontWeight.w700, height: 1.22, spacing: -0.4),
      titleLarge: style(19, FontWeight.w700, height: 1.25, spacing: -0.3),
      titleMedium: style(16, FontWeight.w600, height: 1.35, spacing: -0.1),
      titleSmall: style(14, FontWeight.w600, height: 1.35),
      bodyLarge: style(16, FontWeight.w400, height: 1.5),
      bodyMedium: style(14.5, FontWeight.w400, height: 1.45),
      bodySmall: style(12.5, FontWeight.w500),
      labelLarge: style(16, FontWeight.w700, height: 1.2, spacing: -0.1),
      labelMedium: style(12.5, FontWeight.w600, height: 1.2),
      labelSmall: style(11.5, FontWeight.w500, height: 1.35),
    );
  }
}

/// Emblème Hector : éclair jaune sur pastille noire, aux coins très arrondis
/// comme une icône d'application.
class HectorMark extends StatelessWidget {
  const HectorMark({super.key, this.size = 72});

  final double size;

  @override
  Widget build(BuildContext context) {
    // En sombre, la pastille s'éclaircit pour rester visible sur le fond
    final tile = Theme.of(context).brightness == Brightness.dark
        ? const Color(0xFF2A2A31)
        : HectorColors.black;

    return SizedBox.square(
      dimension: size,
      child: CustomPaint(painter: _MarkPainter(tile)),
    );
  }
}

class _MarkPainter extends CustomPainter {
  const _MarkPainter(this.tile);

  final Color tile;

  @override
  void paint(Canvas canvas, Size size) {
    final side = size.shortestSide;
    canvas.drawRRect(
      RRect.fromRectAndRadius(Offset.zero & size, Radius.circular(side * 0.3)),
      Paint()..color = tile,
    );

    // Éclair dessiné, plutôt que tiré d'une police d'icônes
    final bolt = Path()
      ..moveTo(0.60 * side, 0.16 * side)
      ..lineTo(0.26 * side, 0.56 * side)
      ..lineTo(0.47 * side, 0.56 * side)
      ..lineTo(0.40 * side, 0.84 * side)
      ..lineTo(0.74 * side, 0.44 * side)
      ..lineTo(0.53 * side, 0.44 * side)
      ..close();
    canvas.drawPath(
      bolt,
      Paint()
        ..color = HectorColors.volt
        ..style = PaintingStyle.fill
        ..strokeJoin = StrokeJoin.round,
    );
  }

  @override
  bool shouldRepaint(_MarkPainter oldDelegate) => oldDelegate.tile != tile;
}
