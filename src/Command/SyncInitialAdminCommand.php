<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\InitialAdminBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Sync Railway/local INITIAL_ADMIN_* (or ADMIN_*) into the database.
 * Use when fixtures logged "admin already exists" but login fails after a password change.
 */
#[AsCommand(
    name: 'app:sync-initial-admin',
    description: 'Create or update the bootstrap admin from INITIAL_ADMIN_* / ADMIN_* env vars',
)]
class SyncInitialAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('promote', null, InputOption::VALUE_NONE, 'Promote an existing non-admin user with the same email to ROLE_ADMIN')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without writing to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = InitialAdminBootstrap::normalizeEmail(
            (string) ($this->env('INITIAL_ADMIN_EMAIL') ?? $this->env('ADMIN_EMAIL') ?? '')
        );
        $password = trim((string) ($this->env('INITIAL_ADMIN_PASSWORD') ?? $this->env('ADMIN_PASSWORD') ?? ''));
        $name = trim((string) ($this->env('INITIAL_ADMIN_NAME') ?? $this->env('ADMIN_NAME') ?? 'Admin'));

        if ($email === '' || $password === '') {
            $io->error('Set INITIAL_ADMIN_EMAIL + INITIAL_ADMIN_PASSWORD (or ADMIN_EMAIL + ADMIN_PASSWORD) in the environment first.');

            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $promote = (bool) $input->getOption('promote')
            || ($this->env('PROMOTE_INITIAL_ADMIN') ?? '') === '1';

        $existing = $this->userRepository->findOneByEmail($email);

        if ($existing instanceof User) {
            if (!$existing->isAdmin()) {
                if (!$promote) {
                    $io->error(sprintf(
                        'User "%s" exists but is not an admin (roles: %s). Re-run with --promote to upgrade, or use another email.',
                        $email,
                        implode(', ', $existing->getRoles())
                    ));

                    return Command::FAILURE;
                }

                if ($dryRun) {
                    $io->note(sprintf('Would promote "%s" to ROLE_ADMIN and set a new password.', $email));

                    return Command::SUCCESS;
                }

                $this->bootstrapAdmin($existing, $email, $password, $name);
                $this->userRepository->getEntityManager()->flush();
                $this->assertStoredPasswordWorks($existing, $password, $io);
                $io->success(sprintf('Promoted "%s" to admin and updated password.', $email));

                return Command::SUCCESS;
            }

            if ($dryRun) {
                $io->note(sprintf('Would update password for existing admin "%s".', $email));

                return Command::SUCCESS;
            }

            $this->bootstrapAdmin($existing, $email, $password, $name);
            $this->userRepository->getEntityManager()->flush();
            $this->assertStoredPasswordWorks($existing, $password, $io);
            $io->success(sprintf('Updated password for admin "%s".', $email));

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note(sprintf('Would create admin "%s".', $email));

            return Command::SUCCESS;
        }

        $admin = new User();
        $admin->setEmail($email);
        $admin->setName($name !== '' ? $name : 'Admin');
        $this->bootstrapAdmin($admin, $email, $password, $name);

        $em = $this->userRepository->getEntityManager();
        $em->persist($admin);
        $em->flush();
        $this->assertStoredPasswordWorks($admin, $password, $io);
        $io->success(sprintf('Created admin "%s".', $email));

        return Command::SUCCESS;
    }

    private function bootstrapAdmin(User $user, string $email, string $plainPassword, string $name): void
    {
        $user->setEmail($email);
        if ($name !== '') {
            $user->setName($name);
        }
        InitialAdminBootstrap::applyAdminState($user);
        InitialAdminBootstrap::setPasswordFromPlain($user, $plainPassword, $this->passwordHasher);
    }

    private function assertStoredPasswordWorks(User $user, string $plainPassword, SymfonyStyle $io): void
    {
        $this->userRepository->getEntityManager()->refresh($user);

        if (!InitialAdminBootstrap::assertPasswordValid($user, $plainPassword, $this->passwordHasher)) {
            $io->warning('Password was saved but post-save validation failed. Check APP_SECRET / hasher config.');

            return;
        }

        $io->writeln('  Password hash verified against the same hasher used by /login.');
    }

    private function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return $value !== false && $value !== null ? (string) $value : null;
    }
}
