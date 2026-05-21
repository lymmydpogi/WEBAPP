<?php

namespace App\DataFixtures;

use App\Entity\Services;
use App\Entity\User;
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
        $email = trim((string) ($this->env('INITIAL_ADMIN_EMAIL') ?? $this->env('ADMIN_EMAIL') ?? ''));
        $password = (string) ($this->env('INITIAL_ADMIN_PASSWORD') ?? $this->env('ADMIN_PASSWORD') ?? '');
        $name = trim((string) ($this->env('INITIAL_ADMIN_NAME') ?? $this->env('ADMIN_NAME') ?? 'Admin'));

        if ($email === '' || $password === '') {
            $this->log('Skipping admin seed: INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD must both be set.');

            return null;
        }

        $repo = $manager->getRepository(User::class);
        $existing = $repo->findOneBy(['email' => $email]);

        if ($existing instanceof User) {
            if ($existing->isAdmin()) {
                $this->log(sprintf('Admin already exists, skipping: %s', $email));
            } else {
                $this->log(sprintf('User with email %s already exists (not admin); admin not created.', $email));
            }

            return $existing->isAdmin() ? $existing : null;
        }

        $admin = new User();
        $admin->setEmail($email);
        $admin->setName($name !== '' ? $name : 'Admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setStatus('active');
        $admin->markEmailAsVerified();
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));

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
