<?php

namespace App\Controller\Client;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ProfileController extends AbstractController
{
    #[Route('/client/profile', name: 'client_profile')]
    public function profile(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('client_landing');
        }

        return $this->render('client/profile.html.twig', [
            'user' => $user,
        ]);
    }
}

