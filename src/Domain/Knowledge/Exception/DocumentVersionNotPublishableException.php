<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Exception;

/** KN1 : il manque de quoi publier la version. */
final class DocumentVersionNotPublishableException extends \Exception
{
    /**
     * @param list<string> $missing ce qui manque, en clair
     */
    public function __construct(
        public readonly array $missing,
    ) {
        parent::__construct(\sprintf('Publication impossible, il manque : %s.', implode(', ', $missing)));
    }
}
