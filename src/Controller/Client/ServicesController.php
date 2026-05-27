<?php

namespace App\Controller\Client;

use App\Entity\User;
use App\Exception\ClientOrderException;
use App\Repository\ServicesRepository;
use App\Service\ClientOrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ServicesController extends AbstractController
{
    #[Route('/client/services', name: 'client_services')]
    public function index(ServicesRepository $servicesRepository): Response
    {
        $services = array_map(
            ServicesRepository::serializeForClient(...),
            $servicesRepository->findAllOrderedByName()
        );

        return $this->render('client/services.html.twig', [
            'services' => $services,
        ]);
    }

    #[Route('/client/services/{slug}', name: 'client_service_show', methods: ['GET', 'POST'])]
    public function show(
        string $slug,
        Request $request,
        ServicesRepository $servicesRepository,
        ClientOrderService $clientOrderService,
    ): Response {
        $service = $servicesRepository->findOneBySlug($slug);

        if (!$service) {
            throw $this->createNotFoundException('Service not found.');
        }

        $isOrderable = $service->isOrderable();
        $projectBrief = trim((string) $request->request->get('project_brief', ''));
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('client_service_order_' . $slug, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token. Please refresh the page and try again.');

                return $this->redirectToRoute('client_service_show', ['slug' => $slug]);
            }

            if (!$isOrderable) {
                $this->addFlash('error', ClientOrderService::MSG_SERVICE_INACTIVE);
            } else {
                $client = $this->getUser();

                if (!$client instanceof User) {
                    $this->addFlash('error', 'Please log in with a client account to submit a project brief.');

                    return $this->redirectToRoute('app_login_index', [
                        'next' => $this->generateUrl('client_service_show', ['slug' => $slug]),
                    ]);
                }

                if (!$client->isMobileAppUser()) {
                    $this->addFlash('error', 'Admin and staff accounts cannot place client orders here. Log out and sign in with a client account, or register at /register.');

                    return $this->redirectToRoute('client_service_show', ['slug' => $slug]);
                }

                if ($projectBrief === '') {
                    $errors['project_brief'] = 'Please describe your project brief.';
                } elseif (strlen($projectBrief) < 20) {
                    $errors['project_brief'] = 'Please provide at least 20 characters so we understand your goals.';
                }

                if (!$errors) {
                    try {
                        $order = $clientOrderService->createFromServiceBrief($service, $client, $projectBrief);

                        $this->addFlash(
                            'success',
                            sprintf(
                                'Your project brief for "%s" was submitted. We created your order and will follow up soon.',
                                $service->getName()
                            )
                        );

                        return $this->redirectToRoute('client_order_show', ['id' => $order->getId()]);
                    } catch (ClientOrderException $e) {
                        $this->addFlash('error', $e->getMessage());
                    } catch (\Throwable $e) {
                        $this->addFlash('error', 'We could not save your order. Please try again or contact support.');
                    }
                }
            }
        }

        return $this->render('client/service_show.html.twig', [
            'slug' => $slug,
            'service' => $service,
            'name' => $service->getName(),
            'is_orderable' => $isOrderable,
            'status_label' => $service->getStatusLabel(),
            'project_brief' => $projectBrief,
            'errors' => $errors,
        ]);
    }
}
