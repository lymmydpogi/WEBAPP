<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/order')]
final class OrderController extends AbstractController
{
    #[Route(name: 'app_order_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        $orders = $orderRepository->findAll();

        return $this->render('ADMIN/_TABLES/order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $order = new Order();
        $order->setStatus(Order::STATUS_PENDING);
        $order->setCreatedBy($this->getUser());

        $form = $this->createForm(OrderType::class, $order, [
            'show_status' => false,
            'show_payment_method' => false,
        ]);

        $form->handleRequest($request);

        // ❌ Invalid form submission
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Please fill out all required fields correctly.');
        }

        // ✅ Valid
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($order);
            $entityManager->flush();

            $this->updateUserStatus($order->getUser(), $entityManager);

            $this->addFlash('success', 'Order created successfully.');
            return $this->redirectToRoute('app_order_index');
        }

        return $this->render('ADMIN/_TABLES/order/new.html.twig', [
            'order' => $order,
            'form'  => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('ADMIN/_TABLES/order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Order $order,
        
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $auth
    ): Response {

        // ❌ Staff cannot edit Admin orders
        if (!$auth->isGranted('ORDER_EDIT', $order)) {
            $this->addFlash('error', 'You cannot edit this order because it was created by an Admin.');
            return $this->redirectToRoute('app_order_index');
        }

        $form = $this->createForm(OrderType::class, $order, [
            'show_status' => true,
            'show_payment_method' => true,
        ]);

        $form->handleRequest($request);

        // ❌ Invalid form
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Please correct the form errors before submitting.');
        }

        // ✅ Valid
        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager->flush();

            $this->updateUserStatus($order->getUser(), $entityManager);

            $this->addFlash('success', 'Order updated successfully.');
            return $this->redirectToRoute('app_order_index');
        }

        return $this->render('ADMIN/_TABLES/order/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_order_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Order $order,
        EntityManagerInterface $entityManager,
        AuthorizationCheckerInterface $auth
    ): Response {

        // ❌ Staff cannot delete Admin-created orders
        if (!$auth->isGranted('ORDER_DELETE', $order)) {
            $this->addFlash('error', 'You cannot delete this order because it was created by an Admin.');
            return $this->redirectToRoute('app_order_index');
        }

        if ($this->isCsrfTokenValid('delete' . $order->getId(), $request->request->get('_token'))) {

            $user = $order->getUser();

            $entityManager->remove($order);
            $entityManager->flush();

            $this->updateUserStatus($user, $entityManager);

            $this->addFlash('success', 'Order deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token. Order not deleted.');
        }

        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/{id}/receipt', name: 'app_order_receipt', methods: ['GET'])]
    public function receiptPdf(Order $order, Pdf $knpSnappyPdf): PdfResponse
    {
        $html = $this->renderView('ADMIN/_TABLES/order/receipt.html.twig', [
            'order' => $order,
        ]);

        return new PdfResponse(
            $knpSnappyPdf->getOutputFromHtml($html),
            'receipt_' . $order->getId() . '.pdf'
        );
    }

    private function updateUserStatus(?object $user, EntityManagerInterface $entityManager): void
    {
        if (!$user) return;

        $hasActiveOrder = false;

        foreach ($user->getOrders() as $order) {
            if (in_array($order->getStatus(), [Order::STATUS_PENDING, Order::STATUS_COMPLETED], true)) {
                $hasActiveOrder = true;
                break;
            }
        }

        $user->setStatus($hasActiveOrder ? 'active' : 'suspended');
        $entityManager->flush();
    }
}
