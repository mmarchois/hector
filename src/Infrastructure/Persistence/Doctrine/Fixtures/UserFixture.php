<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Fixtures;

use App\Domain\Company\Company;
use App\Domain\Port\PasswordHasherInterface;
use App\Domain\User\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class UserFixture extends Fixture
{
    public const string USER_UUID = '0b507871-8b5e-4575-b297-a630310fc06e';
    public const string USER_EMAIL = 'electricien@hector.test';
    public const string USER_PASSWORD = 'password123';
    public const string COMPANY_SIRET = '12345678901234';
    public const string INDIVIDUAL_UUID = '1a2b3c4d-5e6f-4890-abcd-ef1234567890';
    public const string INDIVIDUAL_EMAIL = 'particulier@hector.test';

    public function __construct(
        private readonly PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $company = (new Company('7d9f2f0e-2c1b-4f6a-9d3e-5b8a1c2d3e4f'))
            ->setName('Élec Chantier')
            ->setSiret(self::COMPANY_SIRET)
            ->setVatNumber('FR12345678901');

        $professional = (new User(self::USER_UUID))
            ->setFullName('Jean ÉLECTRICIEN')
            ->setEmail(self::USER_EMAIL)
            ->setRoles([User::ROLE_USER])
            ->setProfessional(true)
            ->setCompany($company)
            ->setRegistrationDate(new \DateTimeImmutable('2026-01-01'));
        $professional->setPassword($this->passwordHasher->hash($professional, self::USER_PASSWORD));

        $individual = (new User(self::INDIVIDUAL_UUID))
            ->setFullName('Paul PARTICULIER')
            ->setEmail(self::INDIVIDUAL_EMAIL)
            ->setRoles([User::ROLE_USER])
            ->setRegistrationDate(new \DateTimeImmutable('2026-02-01'));
        $individual->setPassword($this->passwordHasher->hash($individual, self::USER_PASSWORD));

        $manager->persist($company);
        $manager->persist($professional);
        $manager->persist($individual);
        $manager->flush();
    }
}
