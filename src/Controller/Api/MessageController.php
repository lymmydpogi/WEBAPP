<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\ChatMessageException;
use App\Service\ChatMessageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/messages')]
#[IsGranted('ROLE_CLIENT')]
final class MessageController extends AbstractController
{
    public function __construct(
        private readonly ChatMessageService $chatMessageService,
    ) {
    }

    private function apiSuccess(string $message, array $data = [], int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => [],
        ], $status);
    }

    private function apiError(string $message, int $status, array $errors = []): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }

    #[Route('', name: 'api_messages_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $client */
        $client = $this->getUser();
        $messages = $this->chatMessageService->getClientConversation($client);

        return $this->apiSuccess('Messages loaded.', [
            'messages' => array_map(
                ChatMessageService::serialize(...),
                $messages
            ),
        ]);
    }

    #[Route('', name: 'api_messages_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->apiError('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        $rawMessage = (string) ($data['message'] ?? '');

        try {
            /** @var User $client */
            $client = $this->getUser();
            $chatMessage = $this->chatMessageService->sendUserMessage($client, $rawMessage);

            return $this->apiSuccess('Message sent.', [
                'message' => ChatMessageService::serialize($chatMessage),
            ], Response::HTTP_CREATED);
        } catch (ChatMessageException $e) {
            return $this->apiError($e->getMessage(), $e->getHttpStatus(), [
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->apiError(ChatMessageService::MSG_SEND_FAILED, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
