<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
        EntityManagerInterface $entityManager
    ): Response
    {
        // Redirect already logged-in users
       if ($this->getUser() && in_array('ROLE_ADMIN', $this->getUser()->getRoles(), true)) {
            return $this->redirectToRoute('app_home_index');
    }

        $user = new User();

        // Assign default role for new users
        $user->setRoles(['ROLE_CLIENT']);

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Hash password
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword(
                $passwordHasher->hashPassword($user, $plainPassword)
            );

            // Optional custom fields
            if ($form->has('name')) {
                $user->setName($form->get('name')->getData());
            }
            if ($form->has('phone')) {
                $user->setPhone($form->get('phone')->getData());
            }
            if ($form->has('address')) {
                $user->setAddress($form->get('address')->getData());
            }

            // Persist user
            $entityManager->persist($user);
            $entityManager->flush();

            // Redirect to login (NO auto login)
            $this->addFlash('success', 'Account created successfully! Please log in.');
            return $this->redirectToRoute('app_login_index');
        }

        return $this->render('ADMIN/registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
