<?php
// change_password.php

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Kernel;

require __DIR__ . '/vendor/autoload.php';

// Boot Symfony Kernel
$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

/** @var EntityManagerInterface $em */
$em = $container->get(EntityManagerInterface::class);

/** @var UserPasswordHasherInterface $hasher */
$hasher = $container->get(UserPasswordHasherInterface::class);

// --- EDIT THESE ---
$email = 'lymwelangelhocampana@example.com';          // User email to update
$newPassword = '0625Password';  // New password
// -----------------

$user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

if (!$user) {
    echo "User with email '$email' not found.\n";
    exit(1);
}

// Hash the password
$hashedPassword = $hasher->hashPassword($user, $newPassword);
$user->setPassword($hashedPassword);

// Save changes
$em->flush();

echo "Password for '$email' has been updated successfully!\n";
