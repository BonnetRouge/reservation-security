<?php

namespace App\DataFixtures;

use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('user@test.com');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $manager->persist($user);

        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password123'));
        $manager->persist($admin);

        $reservation1 = new Reservation();
        $reservation1->setResourceName('Salle de coworking A');
        $reservation1->setStartAt(new \DateTimeImmutable('2026-07-15 14:00:00'));
        $reservation1->setStatus('confirmed');
        $reservation1->setNote('Réservation pour réunion client');
        $reservation1->setOwner($user);
        $manager->persist($reservation1);

        $reservation2 = new Reservation();
        $reservation2->setResourceName('Salle de coworking B');
        $reservation2->setStartAt(new \DateTimeImmutable('2026-07-16 10:00:00'));
        $reservation2->setStatus('pending');
        $reservation2->setNote('Réservation admin - test');
        $reservation2->setOwner($admin);
        $manager->persist($reservation2);

        $manager->flush();
    }
}