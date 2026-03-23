<?php

namespace App\Controller\Client;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ServicesController extends AbstractController
{
    #[Route('/client/services', name: 'client_services')]
    public function index(): Response
    {
        $services = [
            ['slug' => 'logo-making', 'name' => 'Logo Making'],
            ['slug' => 'photo-editing', 'name' => 'Photo Editing'],
            ['slug' => 'video-editing', 'name' => 'Video Editing'],
            ['slug' => 'web-app-development', 'name' => 'Web/App Development'],
            ['slug' => 'graphic-design', 'name' => 'Graphic Design'],
        ];

        return $this->render('client/services.html.twig', [
            'services' => $services,
        ]);
    }

    #[Route('/client/services/{slug}', name: 'client_service_show')]
    public function show(string $slug): Response
    {
        // Placeholder mapping; replace with real DB lookup later
        $serviceNames = [
            'logo-making' => 'Logo Making',
            'photo-editing' => 'Photo Editing',
            'video-editing' => 'Video Editing',
            'web-app-development' => 'Web/App Development',
            'graphic-design' => 'Graphic Design',
        ];

        $name = $serviceNames[$slug] ?? 'Service';

        return $this->render('client/service_show.html.twig', [
            'slug' => $slug,
            'name' => $name,
        ]);
    }
}

