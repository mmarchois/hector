<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Company\Company;

/**
 * Compte invité ou complet (docs/DOMAIN.md, § 4.9, règles ID1 à ID4) :
 * un invité, ouvert sans inscription, n'a ni nom, ni email, ni mot de passe.
 */
class User
{
    public const string ROLE_USER = 'ROLE_USER';

    private ?string $fullName = null;
    private ?string $email = null;
    /** @var string[] */
    private array $roles = [];
    private ?string $password = null;
    private bool $professional = false;
    private ?Company $company = null;
    private \DateTimeImmutable $registrationDate;
    private ?string $termsOfUseVersion = null;
    private ?\DateTimeImmutable $termsOfUseAcceptedAt = null;

    public function __construct(
        private string $uuid,
    ) {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    /** ID1 : seul un compte complet a un email (et donc des identifiants de connexion). */
    public function isGuest(): bool
    {
        return $this->email === null;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): self
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /** @return string[] */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /** @param string[] $roles */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function isProfessional(): bool
    {
        return $this->professional;
    }

    public function setProfessional(bool $professional): self
    {
        $this->professional = $professional;

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getRegistrationDate(): \DateTimeImmutable
    {
        return $this->registrationDate;
    }

    public function setRegistrationDate(\DateTimeImmutable $date): self
    {
        $this->registrationDate = $date;

        return $this;
    }

    public function getTermsOfUseVersion(): ?string
    {
        return $this->termsOfUseVersion;
    }

    public function getTermsOfUseAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->termsOfUseAcceptedAt;
    }

    public function acceptTermsOfUse(string $version, \DateTimeImmutable $acceptedAt): self
    {
        $this->termsOfUseVersion = $version;
        $this->termsOfUseAcceptedAt = $acceptedAt;

        return $this;
    }

    public function __toString(): string
    {
        return $this->isGuest()
            ? \sprintf('Invité %s', $this->uuid)
            : \sprintf('%s (%s)', $this->fullName, $this->email);
    }
}
