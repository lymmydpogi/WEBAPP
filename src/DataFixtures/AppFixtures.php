<?php

namespace App\DataFixtures;

use App\Entity\Services;
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
        $admin->setEmail('ying@gmail.com'); // Change to your desired admin email
        $admin ->setName('ngolai');
        $admin->setRoles(['ROLE_ADMIN']);      // Admin role
        $admin->setStatus('active');           // optional
        $admin->setPassword(
            $this->passwordHasher->hashPassword(
                $admin,
                'admin123' // Change to a secure password
            )
        );

        $manager->persist($admin);
        
        $websiteServices = [
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

        foreach ($websiteServices as $item) {
            $service = new Services();
            $service->setName($item['name']);
            $service->setDescription($item['description']);
            $service->setPrice($item['price']);
            $service->setStatus('active');
            $service->setPricingModel('fixed');
            $service->setPricingUnit('project');
            $service->setDeliveryTime(7);
            $service->setCategory($item['category']);
            $service->setToolsUsed('Adobe Creative Suite');
            $service->setRevisionLimit('2 revisions');
            $service->setIsActive(true);
            $service->setCreatedBy($admin);
            $manager->persist($service);
        }

        $manager->flush();

        // Optional: Add a reference to use in other fixtures
        $this->addReference('admin-user', $admin);
    }
}
