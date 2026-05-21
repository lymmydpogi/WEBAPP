<?php

namespace App\Controller\Api;

use App\Entity\Client\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

final class ClientContactController extends AbstractController
{
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

    #[Route('/api/client/contact', name: 'api_client_contact', methods: ['POST'])]
    public function contact(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->apiError('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Please enter your name.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if ($subject === '') {
            $errors['subject'] = 'Please enter a subject.';
        }
        if ($message === '') {
            $errors['message'] = 'Please enter a message.';
        }

        if ($errors !== []) {
            return $this->apiError('Validation failed.', Response::HTTP_BAD_REQUEST, $errors);
        }

        $contact = (new ContactMessage())
            ->setName($name)
            ->setEmail($email)
            ->setSubject($subject)
            ->setMessage($message);

        $em->persist($contact);
        $em->flush();

        try {
            $receiver = $_ENV['MAILER_FROM_ADDRESS'] ?? 'lcampana.student@asiancollege.edu.ph';
            $mailer->send(
                (new Email())
                    ->from($receiver)
                    ->to($receiver)
                    ->replyTo($email)
                    ->subject('[Client Contact] ' . $subject)
                    ->text(
                        "New contact message\n\n" .
                        "Name: {$name}\n" .
                        "Email: {$email}\n" .
                        "Subject: {$subject}\n\n" .
                        "Message:\n{$message}\n"
                    )
            );
        } catch (\Throwable) {
            // Same as web: saved to DB even if email fails
        }

        return $this->apiSuccess('Thanks! Your message was sent successfully.');
    }
}
