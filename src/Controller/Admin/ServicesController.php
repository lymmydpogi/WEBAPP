<?php

namespace App\Controller\Admin;

use App\Entity\Services;
use App\Entity\User;
use App\Form\ServicesType;
use App\Repository\ServicesRepository;
use App\Service\ServiceCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/services')]
#[IsGranted('ROLE_STAFF')]
final class ServicesController extends AbstractController
{
    public function __construct(
        private readonly ServiceCatalogService $catalogService,
    ) {
    }
    #[Route(name: 'app_services_index', methods: ['GET'])]
    public function index(ServicesRepository $servicesRepository): Response
    {
        return $this->render('ADMIN/_TABLES/services/index.html.twig', [
            'services' => $servicesRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_services_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in to create a service.');
        }

        $service = new Services();
        $this->catalogService->applyDefaultsForNew($service, $user);

        $form = $this->createForm(ServicesType::class, $service, [
            'is_edit' => false,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->catalogService->publish($service);
            $this->catalogService->ensurePersistableFields($service);

            $entityManager->persist($service);
            $entityManager->flush();

            $this->addFlash('success', 'Service created successfully. It is now visible in the mobile app catalog.');

            return $this->redirectToRoute('app_services_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Could not create service. Fix the highlighted fields (pricing model and unit are required).');
        }

        return $this->render('ADMIN/_TABLES/services/new.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

   #[Route('/{id}/edit', name: 'app_services_edit', methods: ['GET', 'POST'])]
public function edit(
    Request $request,
    Services $service,
    EntityManagerInterface $entityManager,
    AuthorizationCheckerInterface $auth
): Response {

        // Staff restriction (flash instead of AccessDeniedException)
        if (!$auth->isGranted('SERVICE_EDIT', $service)) {
            $this->addFlash('error', 'You cannot edit this service created by an Admin.');
            return $this->redirectToRoute('app_services_index');
        }

        $form = $this->createForm(ServicesType::class, $service, [
            'is_edit' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($service->getStatus() === Services::STATUS_ACTIVE) {
                $this->catalogService->publish($service);
            } else {
                $service->setIsActive(false);
            }
            $this->catalogService->ensurePersistableFields($service);

            $entityManager->flush();

            $this->addFlash('success', 'Service updated successfully.');

            return $this->redirectToRoute('app_services_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Could not update service. Please check the form for errors.');
        }

        return $this->render('ADMIN/_TABLES/services/edit.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }


    #[Route('/{id}', name: 'app_services_show', methods: ['GET'])]
    public function show(Services $service): Response
    {
        return $this->render('ADMIN/_TABLES/services/show.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_service_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Services $service,
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $auth
    ): Response {

        // Voter check
          if (!$auth->isGranted('SERVICE_DELETE', $service)) {
            // Instead of throwing exception, show flash message
            $this->addFlash('error', 'You cannot delete a service created by Admin.');
            return $this->redirectToRoute('app_services_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->request->get('_token'))) {
            try {
                $entityManager->remove($service);
                $entityManager->flush();

                $this->addFlash('success', 'Service deleted successfully.');
            } catch (ForeignKeyConstraintViolationException $e) {
                $this->addFlash('error', 'Cannot delete this service because it is associated with existing orders.');
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_services_index', [], Response::HTTP_SEE_OTHER);
    }
}

