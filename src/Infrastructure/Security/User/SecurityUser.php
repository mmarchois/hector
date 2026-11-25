<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\User;

use App\Domain\User\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Représentation « sécurité » d'un utilisateur du domaine : le domaine ne dépend
 * pas du composant Security de Symfony.
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    private string $uuid;
    private ?string $email;
    /** @var string[] */
    private array $roles;
    private ?string $password;

    public function __construct(User $user)
    {
        $this->uuid = $user->getUuid();
        $this->email = $user->getEmail();
        $this->roles = $user->getRoles();
        $this->password = $user->getPassword();
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    /** Null pour un invité, qui ne peut donc pas se connecter par identifiants. */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /** @return string[] */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * L'uuid (et non l'email) identifie l'utilisateur dans les JWT et les refresh
     * tokens : la session mobile survit ainsi à un changement d'email.
     */
    public function getUserIdentifier(): string
    {
        return $this->uuid;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
