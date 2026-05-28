<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\ActivityLogRepository;
use App\Repository\OrderRepository;
use App\Repository\ServicesRepository;
use App\Repository\UserRepository;
use App\Service\UserRoleService;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRoleService $userRoleService,
    ) {
    }
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

            $role = $form->get('roles')->getData();
            if (is_string($role) && $role !== '') {
                $actor = $this->getUser();
                if ($actor instanceof User) {
                    $this->userRoleService->assertCanAssignRole($actor, $user, $role);
                    $this->userRoleService->applyRole($user, $role);
                }
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

            if (!$form->get('roles')->isDisabled()) {
                $role = $form->get('roles')->getData();
                if (is_string($role) && $role !== '') {
                    $actor = $this->getUser();
                    if ($actor instanceof User) {
                        try {
                            $this->userRoleService->assertCanAssignRole($actor, $user, $role);
                            $this->userRoleService->applyRole($user, $role);
                        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
                            $this->addFlash('error', $e->getMessage());

                            return $this->render('ADMIN/_TABLES/user/edit.html.twig', [
                                'form' => $form->createView(),
                                'user' => $user,
                            ]);
                        }
                    }
                }
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

    #[Route('/{id}/promote-staff', name: 'app_user_promote_staff', methods: ['POST'])]
    public function promoteToStaff(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_user_index');
        }

        if (!$this->isCsrfTokenValid('promote_staff' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('app_user_index');
        }

        if (!$this->userRoleService->canPromoteToStaff($user)) {
            $this->addFlash('error', 'Only client accounts can be promoted to staff.');
            return $this->redirectToRoute('app_user_index');
        }

        $this->userRoleService->applyRole($user, UserRoleService::ROLE_STAFF);
        $entityManager->flush();

        $this->addFlash('success', sprintf('%s is now a staff member. They can log in at /login and access orders and services.', $user->getEmail()));

        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        UserRepository $userRepository,
        ActivityLogRepository $activityLogRepository,
        OrderRepository $orderRepository,
        ServicesRepository $servicesRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $userRepository->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_user_index');
        }

        if (!$this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_user_index');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_user_index');
        }

        $orderCount = $orderRepository->count(['user' => $user]);
        $ordersCreatedCount = $orderRepository->count(['createdBy' => $user]);
        if ($orderCount > 0 || $ordersCreatedCount > 0) {
            $this->addFlash('error', sprintf(
                'Cannot delete this user: %d order(s) as client and %d as creator are still linked. Remove or reassign those orders first.',
                $orderCount,
                $ordersCreatedCount
            ));
            return $this->redirectToRoute('app_user_index');
        }

        $servicesCreated = $servicesRepository->count(['createdBy' => $user]);
        if ($servicesCreated > 0) {
            $this->addFlash('error', sprintf(
                'Cannot delete this user: they created %d service(s). Reassign or delete those services first.',
                $servicesCreated
            ));
            return $this->redirectToRoute('app_user_index');
        }

        try {
            $logsRemoved = $activityLogRepository->deleteByUser($user);
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', $logsRemoved > 0
                ? sprintf('User deleted successfully (%d activity log(s) removed).', $logsRemoved)
                : 'User deleted successfully!');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('error', 'Cannot delete this user: other records still reference this account (orders, services, or activity logs).');
        }

        return $this->redirectToRoute('app_user_index');
    }

    // ===============================
    // PROFILE PAGE
    // ===============================
   
}

