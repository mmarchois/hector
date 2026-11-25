<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

enum ChatRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
