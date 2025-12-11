<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create Admin User
        $admin = new User();
        $admin->setEmail('lymwelangelhocampana@gmail.com'); // Change to your desired admin email
        $admin ->setName('Lymwel Angelho Campama');
        $admin->setRoles(['ROLE_ADMIN']);      // Admin role
        $admin->setStatus('active');           // optional
        $admin->setPassword(
            $this->passwordHasher->hashPassword(
                $admin,
                'password11' // Change to a secure password
            )
        );

        $manager->persist($admin);
        $manager->flush();

        // Optional: Add a reference to use in other fixtures
        $this->addReference('admin-user', $admin);
    }
}
