<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogInPageController extends AbstractController
{
    #[Route('/login-page', name: 'app_login_page_legacy')]
    public function index(): Response
    {
        return $this->render('ADMIN/Security/login.html.twig', [
            'controller_name' => 'LogInPageController',
        ]);
    }
}

