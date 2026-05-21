<?php

namespace App\Controller\Admin;

use App\Exception\ChatMessageException;
use App\Service\ChatMessageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/messages')]
#[IsGranted('ROLE_ADMIN')]
final class MessageController extends AbstractController
{
    public function __construct(
        private readonly ChatMessageService $chatMessageService,
    ) {
    }

    #[Route('', name: 'admin_messages_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('ADMIN/messages/index.html.twig', [
            'conversations' => $this->chatMessageService->getAdminConversationSummaries(),
        ]);
    }

    #[Route('/{userId}', name: 'admin_messages_show', requirements: ['userId' => '\d+'], methods: ['GET'])]
    public function show(int $userId): Response
    {
        try {
            $client = $this->chatMessageService->getClientForAdmin($userId);
            $messages = $this->chatMessageService->getAdminConversation($client, true);

            return $this->render('ADMIN/messages/show.html.twig', [
                'client' => $client,
                'clientName' => ChatMessageService::displayName($client),
                'messages' => $messages,
            ]);
        } catch (ChatMessageException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('admin_messages_index');
        }
    }

    #[Route('/{userId}/reply', name: 'admin_messages_reply', requirements: ['userId' => '\d+'], methods: ['POST'])]
    public function reply(int $userId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_message_reply_' . $userId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', ChatMessageService::MSG_SEND_FAILED);

            return $this->redirectToRoute('admin_messages_show', ['userId' => $userId]);
        }

        try {
            $client = $this->chatMessageService->getClientForAdmin($userId);
            $this->chatMessageService->sendAdminReply($client, (string) $request->request->get('message', ''));
            $this->addFlash('success', 'Reply sent.');
        } catch (ChatMessageException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable) {
            $this->addFlash('error', ChatMessageService::MSG_SEND_FAILED);
        }

        return $this->redirectToRoute('admin_messages_show', ['userId' => $userId]);
    }
}
