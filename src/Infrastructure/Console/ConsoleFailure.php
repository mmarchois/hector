<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\ValidationFailedException;

/** Rend une erreur de saisie ou une règle du domaine à l'opérateur d'une commande console. */
final class ConsoleFailure
{
    public static function report(SymfonyStyle $io, \Throwable $e): int
    {
        if ($e instanceof ValidationFailedException) {
            foreach ($e->getViolations() as $violation) {
                $io->error(\sprintf('%s : %s', $violation->getPropertyPath(), $violation->getMessage()));
            }

            return Command::INVALID;
        }

        // Une exception du domaine porte un message lisible ; toute autre erreur remonte telle quelle
        if (str_starts_with($e::class, 'App\\Domain\\')) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        throw $e;
    }
}
