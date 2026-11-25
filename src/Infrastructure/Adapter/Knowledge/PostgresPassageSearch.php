<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Knowledge;

use App\Application\Knowledge\Port\PassageHit;
use App\Application\Knowledge\Port\PassageSearchInterface;
use App\Domain\Knowledge\Corpus;
use App\Domain\Knowledge\DocumentStatus;
use App\Domain\Knowledge\Passage;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Recherche plein texte de PostgreSQL, en français (décision D4).
 *
 * - Les mots de la question sont cherchés un à un (« ou ») et le classement fait le tri : une
 *   question en langage courant ne contient jamais tous les mots d'un passage.
 * - Un identifiant exact de la question (référence de produit, numéro) fait remonter en tête
 *   les passages qui le contiennent tel quel : c'est ce que la recherche par le sens retrouve mal.
 * - Le vecteur de texte est calculé à la volée, sans index : suffisant pour un corpus de
 *   quelques milliers de passages. Un index GIN sur une table dédiée viendra avec le volume
 *   (docs/SECURITY.md, risque T10).
 */
final readonly class PostgresPassageSearch implements PassageSearchInterface
{
    private const int MAX_TERMS = 24;

    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function search(string $query, array $corpora, int $limit = 6, bool $transferableOnly = false): array
    {
        $terms = $this->terms($query);
        if ($terms === [] || $corpora === []) {
            return [];
        }

        $parameters = [
            'terms' => implode(' | ', $terms),
            'status' => DocumentStatus::Published->value,
            'corpora' => array_map(static fn (Corpus $corpus): string => $corpus->value, $corpora),
            'limit' => max(1, $limit),
        ];
        $types = ['corpora' => ArrayParameterType::STRING];

        // Identifiants exacts : chacun compte pour un point de classement
        $exact = [];
        foreach ($this->identifiers($query) as $index => $identifier) {
            $exact[] = \sprintf('(CASE WHEN p.text ILIKE :like%1$d OR p.article = :id%1$d THEN 1 ELSE 0 END)', $index);
            $parameters['like' . $index] = '%' . addcslashes($identifier, '%_\\') . '%';
            $parameters['id' . $index] = $identifier;
        }
        $exactScore = $exact === [] ? '0' : implode(' + ', $exact);

        $rows = $this->connection->fetchAllAssociative(
            <<<SQL
                SELECT uuid, score, exact FROM (
                    SELECT p.uuid, p.ordinal,
                           ts_rank_cd(to_tsvector('french', p.text || ' ' || COALESCE(p.section, '')), query) AS score,
                           $exactScore AS exact
                    FROM knowledge_passage p
                    JOIN knowledge_document_version v ON v.uuid = p.version_uuid
                    JOIN knowledge_source s ON s.uuid = v.source_uuid
                    CROSS JOIN to_tsquery('french', :terms) query
                    WHERE v.status = :status
                      AND s.corpus IN (:corpora)
                      {$this->transferFilter($transferableOnly)}
                ) ranked
                WHERE score > 0 OR exact > 0
                ORDER BY exact DESC, score DESC, ordinal ASC
                LIMIT :limit
                SQL,
            $parameters,
            $types,
        );
        if ($rows === []) {
            return [];
        }

        /** @var array<string, Passage> $passages */
        $passages = [];
        foreach ($this->entityManager->getRepository(Passage::class)->findBy(['uuid' => array_column($rows, 'uuid')]) as $passage) {
            $passages[$passage->getUuid()] = $passage;
        }

        $hits = [];
        foreach ($rows as $row) {
            if (isset($passages[$row['uuid']])) {
                $hits[] = new PassageHit($passages[$row['uuid']], (float) $row['score'], (int) $row['exact'] > 0);
            }
        }

        return $hits;
    }

    private function transferFilter(bool $transferableOnly): string
    {
        return $transferableOnly ? 'AND s.license_third_party_transfer_allowed = true' : '';
    }

    /**
     * Mots de la question, sans ponctuation : rien de ce que l'utilisateur écrit n'arrive tel
     * quel dans la syntaxe de recherche.
     *
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_unique(array_filter($words, static fn (string $word): bool => mb_strlen($word) >= 2)));

        return \array_slice($words, 0, self::MAX_TERMS);
    }

    /**
     * Identifiants de la question : au moins quatre caractères, au moins un chiffre, des
     * lettres et des chiffres éventuellement reliés par des points ou des tirets
     * (A9F74216, REF-HX-4821, 15-100).
     *
     * @return list<string>
     */
    private function identifiers(string $query): array
    {
        preg_match_all('/(?<![\p{L}\p{N}])[\p{L}\p{N}]+(?:[.\-][\p{L}\p{N}]+)*(?![\p{L}\p{N}])/u', $query, $matches);
        $identifiers = array_filter(
            $matches[0],
            static fn (string $token): bool => mb_strlen($token) >= 4 && preg_match('/\d/', $token) === 1,
        );

        return \array_slice(array_values(array_unique($identifiers)), 0, 5);
    }
}
