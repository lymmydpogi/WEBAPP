<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings_index')]
    public function index(): Response
    {
        return $this->render('ADMIN/settings/index.html.twig', [
            'controller_name' => 'SettingsController',
        ]);
    }
}
