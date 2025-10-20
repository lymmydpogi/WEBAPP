<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/client')]
class ClientController extends AbstractController
{
    #[Route('/', name: 'app_client_index')]
    public function index(ClientRepository $clientRepository, OrderRepository $orderRepository): Response
    {
        // Fetch all clients ordered by creation date
        $clients = $clientRepository->findAllOrderedByCreatedAt();

        // Dashboard stats using repository helper methods
        $totalClients = $clientRepository->countAllClients();
        $activeClients = $clientRepository->countActiveClients();
        $suspendedClients = $clientRepository->countSuspendedClients();
        $inactiveClients = $totalClients - $activeClients - $suspendedClients;

        // Total revenue from all orders
        $totalRevenue = $orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalPrice) as total')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('client/index.html.twig', [
            'clients' => $clients,
            'totalClients' => $totalClients,
            'activeClients' => $activeClients,
            'inactiveClients' => $inactiveClients,
            'suspendedClients' => $suspendedClients,
            'totalRevenue' => $totalRevenue ?? 0,
        ]);
    }

    #[Route('/new', name: 'app_client_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($client);
            $entityManager->flush();

            $this->addFlash('success', 'Client created successfully!');
            return $this->redirectToRoute('app_client_index');
        }

        return $this->render('client/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_client_show')]
    public function show(Client $client): Response
    {
        return $this->render('client/show.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_client_edit')]
    public function edit(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Client updated successfully!');
            return $this->redirectToRoute('app_client_index');
        }

        return $this->render('client/edit.html.twig', [
            'form' => $form->createView(),
            'client' => $client,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_client_delete', methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$client->getId(), $request->request->get('_token'))) {
            $entityManager->remove($client);
            $entityManager->flush();
            $this->addFlash('success', 'Client deleted successfully!');
        }

        return $this->redirectToRoute('app_client_index');
    }
}
