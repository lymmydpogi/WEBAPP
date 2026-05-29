<?php

namespace App\Controller\Client;

use App\Entity\Order;
use App\Entity\User;
use App\Exception\ClientOrderException;
use App\Form\ClientOrderType;
use App\Repository\OrderRepository;
use App\Service\ClientOrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client/orders')]
#[IsGranted('ROLE_USER')]
final class OrderController extends AbstractController
{
    #[Route('', name: 'client_orders', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        $client = $this->requireClientUser();
        $orders = $orderRepository->findForClientNewestFirst($client);

        return $this->render('client/order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/{id}', name: 'client_order_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ClientOrderService $clientOrderService): Response
    {
        $client = $this->requireClientUser();

        try {
            $order = $clientOrderService->getOrderForClient($client, $id);
        } catch (ClientOrderException $e) {
            throw $this->createNotFoundException($e->getMessage());
        }

        return $this->render('client/order/show.html.twig', [
            'order' => $order,
            'canModify' => $order->canBeModifiedByClient(),
        ]);
    }

    #[Route('/{id}/edit', name: 'client_order_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        ClientOrderService $clientOrderService,
    ): Response {
        $client = $this->requireClientUser();

        try {
            $order = $clientOrderService->getOrderForClient($client, $id);
            $clientOrderService->assertClientCanEdit($client, $order);
        } catch (ClientOrderException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('client_order_show', ['id' => $id]);
        }

        $form = $this->createForm(ClientOrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Please correct the errors below.');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $clientOrderService->updatePendingOrder($client, $id, [
                    'serviceId' => $order->getService()?->getId(),
                    'notes' => $order->getNotes() ?? '',
                ]);
                $this->addFlash('success', 'Order updated successfully.');

                return $this->redirectToRoute('client_order_show', ['id' => $id]);
            } catch (ClientOrderException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('client/order/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/cancel', name: 'client_order_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(
        int $id,
        Request $request,
        ClientOrderService $clientOrderService,
    ): Response {
        $client = $this->requireClientUser();

        try {
            $order = $clientOrderService->getOrderForClient($client, $id);
        } catch (ClientOrderException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('client_orders');
        }

        if (!$this->isCsrfTokenValid('cancel_order_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('client_order_show', ['id' => $id]);
        }

        try {
            $clientOrderService->assertClientCanCancel($client, $order);
            $clientOrderService->cancelPendingOrder($client, $id);
            $this->addFlash('success', 'Order cancelled successfully.');
        } catch (ClientOrderException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('client_order_show', ['id' => $id]);
    }

    private function requireClientUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isMobileAppUser()) {
            throw $this->createAccessDeniedException('Client account required.');
        }

        return $user;
    }
}
