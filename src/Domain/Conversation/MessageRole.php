<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
