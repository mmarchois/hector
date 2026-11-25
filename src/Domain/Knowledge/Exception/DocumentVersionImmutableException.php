<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Exception;

/** KN2 : une version publiée ou retirée ne change plus. */
final class DocumentVersionImmutableException extends \Exception
{
}
