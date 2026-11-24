<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Client HTTP PSR-18 qui rejoue une réponse enregistrée et garde les requêtes envoyées :
 * le SDK du fournisseur est exercé de bout en bout, sans réseau (tests de contrat).
 */
final class RecordedHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    private function __construct(
        private readonly ?ResponseInterface $response,
        private readonly bool $networkFailure,
    ) {
    }

    public static function replying(int $status, string $jsonFile): self
    {
        return new self(new Response($status, ['Content-Type' => 'application/json'], (string) file_get_contents($jsonFile)), false);
    }

    public static function offline(): self
    {
        return new self(null, true);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->networkFailure || $this->response === null) {
            throw new class('Connexion refusée', $request) extends \RuntimeException implements NetworkExceptionInterface {
                public function __construct(string $message, private readonly RequestInterface $request)
                {
                    parent::__construct($message);
                }

                public function getRequest(): RequestInterface
                {
                    return $this->request;
                }
            };
        }

        return $this->response;
    }
}
