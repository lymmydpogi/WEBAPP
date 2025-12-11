<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class StaffController extends AbstractController
{
    #[Route('/staff', name: 'app_staff_dashboard')]
    public function index(): Response
    {
        
        return $this->redirectToRoute('app_services_index'); // make sure this route exists
    }
}
