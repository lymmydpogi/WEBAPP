<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogInPageController extends AbstractController
{
    #[Route('/login', name: 'app_login_index')]
    public function index(): Response
    {
        return $this->render('ADMIN/Security/login.html.twig', [
            'controller_name' => 'LogInPageController',
        ]);
    }
}
