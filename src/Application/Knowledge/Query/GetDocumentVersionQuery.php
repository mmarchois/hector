<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Query;

use App\Application\QueryInterface;

/** Une version, ses passages et sa dernière ingestion : de quoi la relire avant publication. */
final readonly class GetDocumentVersionQuery implements QueryInterface
{
    public function __construct(
        public string $versionUuid,
    ) {
    }
}
