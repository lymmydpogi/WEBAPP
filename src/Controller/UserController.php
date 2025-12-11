<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/user')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index')]
    public function index(UserRepository $userRepository, OrderRepository $orderRepository): Response
    {
        $clients = $userRepository->findBy([], ['createdAt' => 'DESC']);

        $totalClients = $userRepository->countAllClients();
        $activeClients = $userRepository->countActiveClients();
        $suspendedClients = $userRepository->countSuspendedClients();
        $inactiveClients = $totalClients - $activeClients - $suspendedClients;

        $totalRevenue = $orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalPrice) as total')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('ADMIN/_TABLES/user/index.html.twig', [
            'clients' => $clients,
            'totalClients' => $totalClients,
            'activeClients' => $activeClients,
            'inactiveClients' => $inactiveClients,
            'suspendedClients' => $suspendedClients,
            'totalRevenue' => $totalRevenue ?? 0,
        ]);
    }

    #[Route('/new', name: 'app_user_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $user->setStatus('active');

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => false,
            'is_profile' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User created successfully!');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('ADMIN/_TABLES/user/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

        #[Route('/{id<\d+>}', name: 'app_user_show')]
    public function show(int $id, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        return $this->render('ADMIN/_TABLES/user/show.html.twig', [
            'user' => $user
        ]);
    }


    #[Route('/{id}/edit', name: 'app_user_edit')]
    public function edit(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
            'is_profile' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('new_password')->getData();
            if ($newPassword) {
                $hashed = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashed);
                $this->addFlash('success', 'Password updated successfully!');
            }

            $entityManager->flush();
            $this->addFlash('success', 'User updated successfully!');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('ADMIN/_TABLES/user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_user_index');
        }

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'User deleted successfully!');
        }

        return $this->redirectToRoute('app_user_index');
    }

    // ===============================
    // PROFILE PAGE
    // ===============================
   
}
