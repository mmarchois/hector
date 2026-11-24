/// Conversations, messages et tours (réponses de /api/v1/conversations et
/// /api/v1/turns).
library;

/// Élément typé et versionné d'un message. Un type inconnu de cette version de
/// l'application s'affiche par son texte de repli (règle CV5).
class MessageBlock {
  const MessageBlock({
    required this.type,
    required this.version,
    required this.fallbackText,
    this.data = const {},
  });

  factory MessageBlock.fromJson(Map<String, dynamic> json) {
    return MessageBlock(
      type: json['type'] as String? ?? '',
      version: json['version'] as int? ?? 1,
      fallbackText: json['fallbackText'] as String? ?? '',
      data: json,
    );
  }

  static const textType = 'text';

  final String type;
  final int version;
  final String fallbackText;
  final Map<String, dynamic> data;

  /// Texte à afficher : celui du bloc s'il est connu, sinon le texte de repli.
  String get displayText {
    if (type == textType && version == 1) {
      return data['text'] as String? ?? fallbackText;
    }

    return fallbackText;
  }
}

enum MessageRole { user, assistant }

class ChatMessage {
  const ChatMessage({
    required this.uuid,
    required this.role,
    required this.blocks,
    required this.createdAt,
  });

  factory ChatMessage.fromJson(Map<String, dynamic> json) {
    return ChatMessage(
      uuid: json['uuid'] as String,
      role: json['role'] == 'assistant'
          ? MessageRole.assistant
          : MessageRole.user,
      blocks: (json['blocks'] as List<dynamic>)
          .map((b) => MessageBlock.fromJson(b as Map<String, dynamic>))
          .toList(),
      createdAt: DateTime.parse(json['createdAt'] as String),
    );
  }

  final String uuid;
  final MessageRole role;
  final List<MessageBlock> blocks;
  final DateTime createdAt;

  /// Texte du message, blocs mis bout à bout.
  String get text => blocks.map((b) => b.displayText).join('\n\n');
}

class Conversation {
  const Conversation({
    required this.uuid,
    required this.title,
    required this.updatedAt,
  });

  factory Conversation.fromJson(Map<String, dynamic> json) {
    return Conversation(
      uuid: json['uuid'] as String,
      title: json['title'] as String?,
      updatedAt: DateTime.parse(json['updatedAt'] as String),
    );
  }

  final String uuid;

  /// Tiré du premier message ; null tant qu'aucun message n'a été envoyé.
  final String? title;
  final DateTime updatedAt;
}

class ConversationPage {
  const ConversationPage({required this.items, required this.total});

  factory ConversationPage.fromJson(Map<String, dynamic> json) {
    return ConversationPage(
      items: (json['items'] as List<dynamic>)
          .map((c) => Conversation.fromJson(c as Map<String, dynamic>))
          .toList(),
      total: json['total'] as int,
    );
  }

  final List<Conversation> items;
  final int total;
}

class MessagePage {
  const MessagePage({required this.items, required this.hasMore});

  factory MessagePage.fromJson(Map<String, dynamic> json) {
    return MessagePage(
      items: (json['items'] as List<dynamic>)
          .map((m) => ChatMessage.fromJson(m as Map<String, dynamic>))
          .toList(),
      hasMore: json['hasMore'] as bool,
    );
  }

  /// Du plus ancien au plus récent.
  final List<ChatMessage> items;
  final bool hasMore;
}

enum TurnStatus { inProgress, completed, degraded, failed }

/// Un message envoyé et la réponse qu'il déclenche.
class Turn {
  const Turn({
    required this.uuid,
    required this.status,
    required this.userMessage,
    this.assistantMessage,
    this.failureMessage,
  });

  factory Turn.fromJson(Map<String, dynamic> json) {
    final answer = json['assistantMessage'];
    final failure = json['failure'];

    return Turn(
      uuid: json['uuid'] as String,
      status: switch (json['status']) {
        'completed' => TurnStatus.completed,
        'degraded' => TurnStatus.degraded,
        'failed' => TurnStatus.failed,
        _ => TurnStatus.inProgress,
      },
      userMessage: ChatMessage.fromJson(
        json['userMessage'] as Map<String, dynamic>,
      ),
      assistantMessage: answer is Map<String, dynamic>
          ? ChatMessage.fromJson(answer)
          : null,
      failureMessage: failure is Map<String, dynamic>
          ? failure['message'] as String?
          : null,
    );
  }

  final String uuid;
  final TurnStatus status;
  final ChatMessage userMessage;
  final ChatMessage? assistantMessage;

  /// Message à afficher quand le tour a échoué (rédigé par l'API).
  final String? failureMessage;

  bool get finished => status != TurnStatus.inProgress;
}
