<?php

namespace App\Controller\Client;

use App\Entity\Client\ContactMessage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/client/contact', name: 'client_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $data = [
            'name' => trim((string) $request->request->get('name', '')),
            'email' => trim((string) $request->request->get('email', '')),
            'subject' => trim((string) $request->request->get('subject', '')),
            'message' => trim((string) $request->request->get('message', '')),
        ];

        $errors = [];

        if (!$request->isMethod('POST')) {
            $user = $this->getUser();
            if ($user instanceof User) {
                if ($data['email'] === '') {
                    $data['email'] = (string) $user->getEmail();
                }
                if ($data['name'] === '') {
                    $fullName = trim(sprintf(
                        '%s %s',
                        $user->getFirstName() ?? '',
                        $user->getLastName() ?? ''
                    ));
                    $data['name'] = $fullName !== '' ? $fullName : (string) ($user->getName() ?? '');
                }
            }
        }

        if ($request->isMethod('POST')) {
            if ($data['name'] === '') {
                $errors['name'] = 'Please enter your name.';
            }
            if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please enter a valid email address.';
            }
            if ($data['subject'] === '') {
                $errors['subject'] = 'Please enter a subject.';
            }
            if ($data['message'] === '') {
                $errors['message'] = 'Please enter a message.';
            }

            if (!$errors) {
                $contact = (new ContactMessage())
                    ->setName($data['name'])
                    ->setEmail($data['email'])
                    ->setSubject($data['subject'])
                    ->setMessage($data['message']);

                $em->persist($contact);
                $em->flush();

                // Third-party submission via configured Mailer provider (Brevo SMTP).
                try {
                    $receiver = $_ENV['MAILER_FROM_ADDRESS'] ?? 'lcampana.student@asiancollege.edu.ph';
                    $mailer->send(
                        (new Email())
                            ->from($receiver)
                            ->to($receiver)
                            ->replyTo($data['email'])
                            ->subject('[Client Contact] ' . $data['subject'])
                            ->text(
                                "New contact message\n\n" .
                                "Name: {$data['name']}\n" .
                                "Email: {$data['email']}\n" .
                                "Subject: {$data['subject']}\n\n" .
                                "Message:\n{$data['message']}\n"
                            )
                    );
                    $this->addFlash('success', 'Thanks! Your message was sent successfully.');
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Your message was saved, but email delivery failed. Please try again shortly.');
                }

                return $this->redirectToRoute('client_contact');
            }
        }

        return $this->render('client/contact.html.twig', [
            'form_data' => $data,
            'errors' => $errors,
        ]);
    }
}

