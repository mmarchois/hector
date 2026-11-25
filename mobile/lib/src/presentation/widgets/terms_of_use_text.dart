import 'package:flutter/material.dart';

/// Affiche le Markdown simple des conditions d'utilisation : titres (`#`,
/// `##`), listes (`- `), paragraphes. L'italique (`*…*`) est rendu en clair.
/// Volontairement minimal : le texte est rédigé pour ce rendu.
class TermsOfUseText extends StatelessWidget {
  const TermsOfUseText(this.markdown, {super.key});

  final String markdown;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final children = <Widget>[];

    for (final rawLine in markdown.split('\n')) {
      final line = rawLine.trim();
      if (line.isEmpty) {
        continue;
      }
      if (line.startsWith('## ')) {
        children.add(
          _padded(
            Text(line.substring(3), style: theme.textTheme.titleMedium),
            top: 16,
          ),
        );
      } else if (line.startsWith('# ')) {
        children.add(
          _padded(
            Text(line.substring(2), style: theme.textTheme.titleLarge),
            top: 0,
          ),
        );
      } else if (line.startsWith('- ')) {
        children.add(
          _padded(
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('•  '),
                Expanded(child: Text(_plain(line.substring(2)))),
              ],
            ),
            top: 4,
          ),
        );
      } else {
        children.add(_padded(Text(_plain(line)), top: 8));
      }
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: children,
    );
  }

  static Widget _padded(Widget child, {required double top}) {
    return Padding(
      padding: EdgeInsets.only(top: top),
      child: child,
    );
  }

  static String _plain(String text) => text.replaceAll('*', '');
}
