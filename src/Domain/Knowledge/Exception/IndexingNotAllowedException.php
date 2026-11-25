<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Exception;

/** KN5 : la licence de la source n'en permet pas l'indexation. */
final class IndexingNotAllowedException extends \Exception
{
    public function __construct(string $sourceTitle)
    {
        parent::__construct(\sprintf('La licence de « %s » n\'en permet pas l\'indexation : la source ne peut pas être ingérée.', $sourceTitle));
    }
}
