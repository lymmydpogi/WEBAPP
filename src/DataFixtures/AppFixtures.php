<?php

namespace App\DataFixtures;

use App\Entity\Services;
use App\Entity\User;
use App\Service\InitialAdminBootstrap;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Idempotent seed data for local dev and optional Railway bootstrap (RUN_FIXTURES=1).
 * Never purges data — use doctrine:fixtures:load --append only in production.
 */
class AppFixtures extends Fixture
{
    private const DEFAULT_SERVICES = [
        [
            'name' => 'Logo Making',
            'description' => 'Custom logo design package for business branding and identity assets.',
            'price' => 1500.00,
            'category' => 'Branding',
        ],
        [
            'name' => 'Photo Editing',
            'description' => 'Professional photo enhancement, retouching, and color correction services.',
            'price' => 800.00,
            'category' => 'Photography',
        ],
        [
            'name' => 'Video Editing',
            'description' => 'End-to-end video editing for social media, ads, and promo content.',
            'price' => 2500.00,
            'category' => 'Video Production',
        ],
        [
            'name' => 'Web/App Development',
            'description' => 'Responsive web and app development focused on performance and usability.',
            'price' => 5000.00,
            'category' => 'Development',
        ],
        [
            'name' => 'Graphic Design',
            'description' => 'Creative graphic design for marketing materials and digital campaigns.',
            'price' => 1200.00,
            'category' => 'Design',
        ],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->seedAdmin($manager);
        $owner = $admin ?? $this->findExistingAdmin($manager);

        if (!$owner instanceof User) {
            $this->log('Skipping default services: no admin user available for createdBy.');

            return;
        }

        $this->seedServices($manager, $owner);
        $manager->flush();

        if ($admin instanceof User) {
            $this->addReference('admin-user', $admin);
        }
    }

    private function seedAdmin(ObjectManager $manager): ?User
    {
        // INITIAL_ADMIN_* (documented); ADMIN_* accepted for Railway convenience
        $email = InitialAdminBootstrap::normalizeEmail(
            (string) ($this->env('INITIAL_ADMIN_EMAIL') ?? $this->env('ADMIN_EMAIL') ?? '')
        );
        $password = trim((string) ($this->env('INITIAL_ADMIN_PASSWORD') ?? $this->env('ADMIN_PASSWORD') ?? ''));
        $name = trim((string) ($this->env('INITIAL_ADMIN_NAME') ?? $this->env('ADMIN_NAME') ?? 'Admin'));
        $syncPassword = ($this->env('SYNC_INITIAL_ADMIN_PASSWORD') ?? '') === '1';
        $promote = ($this->env('PROMOTE_INITIAL_ADMIN') ?? '') === '1';

        if ($email === '' || $password === '') {
            $this->log('Skipping admin seed: INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD must both be set.');

            return null;
        }

        /** @var \App\Repository\UserRepository $repo */
        $repo = $manager->getRepository(User::class);
        $existing = $repo->findOneByEmail($email);

        if ($existing instanceof User) {
            if ($existing->isAdmin()) {
                if ($syncPassword) {
                    InitialAdminBootstrap::applyAdminState($existing);
                    InitialAdminBootstrap::setPasswordFromPlain($existing, $password, $this->passwordHasher);
                    $this->log(sprintf('Admin already exists; password synced from env: %s', $email));
                } else {
                    $this->log(sprintf('Admin already exists, skipping (set SYNC_INITIAL_ADMIN_PASSWORD=1 to update password): %s', $email));
                }
            } elseif ($promote) {
                $existing->setEmail($email);
                InitialAdminBootstrap::applyAdminState($existing);
                InitialAdminBootstrap::setPasswordFromPlain($existing, $password, $this->passwordHasher);
                $this->log(sprintf('Promoted existing user to admin and set password: %s', $email));
            } else {
                $this->log(sprintf(
                    'User with email %s already exists (not admin). Login uses that account\'s password, not Railway ADMIN_PASSWORD. Set PROMOTE_INITIAL_ADMIN=1 or run: php bin/console app:sync-initial-admin --promote',
                    $email
                ));
            }

            return $existing->isAdmin() ? $existing : null;
        }

        $admin = new User();
        $admin->setEmail($email);
        $admin->setName($name !== '' ? $name : 'Admin');
        InitialAdminBootstrap::applyAdminState($admin);
        InitialAdminBootstrap::setPasswordFromPlain($admin, $password, $this->passwordHasher);

        $manager->persist($admin);
        $this->log(sprintf('Created admin account: %s', $email));

        return $admin;
    }

    private function seedServices(ObjectManager $manager, User $owner): void
    {
        $repo = $manager->getRepository(Services::class);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach (self::DEFAULT_SERVICES as $item) {
            $existing = $repo->findOneBy(['name' => $item['name']]);

            if ($existing instanceof Services) {
                if ($this->updateExistingService($existing, $item)) {
                    ++$updated;
                } else {
                    ++$skipped;
                }
                continue;
            }

            $service = new Services();
            $this->applyServiceFields($service, $item, true);
            $service->setCreatedBy($owner);
            $manager->persist($service);
            ++$created;
        }

        $this->log(sprintf(
            'Services seed: %d created, %d updated, %d unchanged (inactive preserved).',
            $created,
            $updated,
            $skipped
        ));
    }

    private function applyServiceFields(Services $service, array $item, bool $asActive): void
    {
        $service->setName($item['name']);
        $service->setDescription($item['description']);
        $service->setPrice($item['price']);
        $service->setPricingModel('fixed');
        $service->setPricingUnit('project');
        $service->setDeliveryTime(7);
        $service->setCategory($item['category']);
        $service->setToolsUsed('Adobe Creative Suite');
        $service->setRevisionLimit('2 revisions');

        if ($asActive) {
            $service->setStatus(Services::STATUS_ACTIVE);
            $service->setIsActive(true);
        }
    }

    /**
     * Update catalog fields; never change inactive services to active.
     */
    private function updateExistingService(Services $service, array $item): bool
    {
        if ($service->getStatus() === Services::STATUS_INACTIVE) {
            return false;
        }

        $this->applyServiceFields($service, $item, true);

        return true;
    }

    private function findExistingAdmin(ObjectManager $manager): ?User
    {
        foreach ($manager->getRepository(User::class)->findAll() as $user) {
            if ($user instanceof User && $user->isAdmin()) {
                return $user;
            }
        }

        return null;
    }

    private function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return $value !== false && $value !== null ? (string) $value : null;
    }

    private function log(string $message): void
    {
        fwrite(STDERR, '[AppFixtures] ' . $message . PHP_EOL);
    }
}
