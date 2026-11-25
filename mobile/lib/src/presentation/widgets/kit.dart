import 'package:flutter/material.dart';

import '../../theme.dart';

/// Pastille : une icône et un mot sur un fond teinté. Dit la nature d'un
/// énoncé (fait, hypothèse, recommandation…) d'un coup d'œil.
class KindChip extends StatelessWidget {
  const KindChip({
    super.key,
    required this.icon,
    required this.label,
    this.tone = Tone.neutral,
    this.solid = false,
  });

  final IconData icon;
  final String label;
  final Tone tone;

  /// Fond plein, pour ce qui doit ressortir (une recommandation).
  final bool solid;

  @override
  Widget build(BuildContext context) {
    final tokens = HectorTokens.of(context);
    final background = solid ? tokens.accent : tokens.background(tone);
    final foreground = solid ? tokens.onAccent : tokens.foreground(tone);

    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.fromLTRB(8, 5, 11, 5),
        decoration: BoxDecoration(
          color: background,
          borderRadius: const BorderRadius.all(Radius.circular(99)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 15, color: foreground),
            const SizedBox(width: 5),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(
                  context,
                ).textTheme.labelMedium?.copyWith(color: foreground),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Encart aux coins arrondis, sans contour ni ombre.
class SoftCard extends StatelessWidget {
  const SoftCard({
    super.key,
    required this.child,
    this.color,
    this.padding = const EdgeInsets.all(16),
    this.radius = HectorTheme.cardRadius,
  });

  final Widget child;
  final Color? color;
  final EdgeInsetsGeometry padding;
  final BorderRadius radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: padding,
      decoration: BoxDecoration(
        color: color ?? HectorTokens.of(context).soft,
        borderRadius: radius,
      ),
      child: child,
    );
  }
}

/// Liste groupée : des lignes dans un même encart, séparées par un filet.
class GroupedList extends StatelessWidget {
  const GroupedList({super.key, required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final tokens = HectorTokens.of(context);

    return ClipRRect(
      borderRadius: HectorTheme.cardRadius,
      child: ColoredBox(
        color: tokens.soft,
        child: Column(
          children: [
            for (final (index, child) in children.indexed) ...[
              if (index > 0)
                Divider(indent: 18, endIndent: 18, color: tokens.softer),
              child,
            ],
          ],
        ),
      ),
    );
  }
}

/// Ligne d'une liste groupée.
class GroupedRow extends StatelessWidget {
  const GroupedRow({
    super.key,
    required this.title,
    required this.onTap,
    this.detail,
    this.leading,
  });

  final String title;
  final String? detail;
  final Widget? leading;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 14, 12, 14),
        child: Row(
          children: [
            if (leading != null) ...[leading!, const SizedBox(width: 12)],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleMedium,
                  ),
                  if (detail != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      detail!,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: tokens.muted,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 8),
            Icon(Icons.chevron_right_rounded, color: tokens.muted),
          ],
        ),
      ),
    );
  }
}

/// Pastille ronde d'un compte : l'initiale, ou une silhouette.
class Avatar extends StatelessWidget {
  const Avatar({
    super.key,
    this.name = '',
    this.size = 40,
    this.onSoft = false,
  });

  final String name;
  final double size;

  /// Posé sur une surface douce : fond un cran plus marqué.
  final bool onSoft;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final initial = name.trim().isEmpty ? null : name.trim()[0].toUpperCase();

    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: onSoft
            ? HectorTokens.of(context).softer
            : HectorTokens.of(context).soft,
        shape: BoxShape.circle,
      ),
      child: initial == null
          ? Icon(Icons.person_rounded, size: size * 0.55)
          : Text(
              initial,
              style: theme.textTheme.titleMedium?.copyWith(
                fontSize: size * 0.42,
                fontWeight: FontWeight.w700,
                height: 1,
              ),
            ),
    );
  }
}

/// Attente : trois points qui pulsent tour à tour.
class TypingDots extends StatefulWidget {
  const TypingDots({super.key});

  @override
  State<TypingDots> createState() => _TypingDotsState();
}

class _TypingDotsState extends State<TypingDots>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final color = HectorTokens.of(context).muted;

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (var i = 0; i < 3; i++)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 2.5),
              child: Opacity(
                opacity: _strength(i),
                child: Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: color,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  /// Chaque point s'allume un tiers de cycle après le précédent.
  double _strength(int index) {
    final phase = (_controller.value - index / 3) % 1;
    final wave = phase < 0.5 ? phase * 2 : (1 - phase) * 2;

    return 0.3 + 0.7 * Curves.easeInOut.transform(wave);
  }
}
