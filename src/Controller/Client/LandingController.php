<?php

namespace App\Controller\Client;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LandingController extends AbstractController
{
    #[Route('/client', name: 'client_landing')]
    #[Route('/client/dashboard', name: 'app_client_dashboard')]
    public function index(): Response
    {
        $services = [
            'Logo Making',
            'Photo Editing',
            'Video Editing',
            'Web/App Development',
            'Graphic Design',
        ];

        return $this->render('client/landing.html.twig', [
            'services' => $services,
        ]);
    }
}

