<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Company\Company;

class User
{
    public const string ROLE_USER = 'ROLE_USER';

    private string $fullName;
    private string $email;
    /** @var string[] */
    private array $roles = [];
    private ?string $password = null;
    private bool $professional = false;
    private ?Company $company = null;
    private \DateTimeImmutable $registrationDate;

    public function __construct(
        private string $uuid,
    ) {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
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

    public function __toString(): string
    {
        return \sprintf('%s (%s)', $this->fullName, $this->email);
    }
}
