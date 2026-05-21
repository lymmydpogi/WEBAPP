<?php

namespace App\Controller\Client;

use App\Entity\User;
use App\Form\ClientProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ProfileController extends AbstractController
{
    #[Route('/client/profile', name: 'client_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('client_landing');
        }

        if (($user->getFirstName() === null || $user->getLastName() === null) && $user->getName()) {
            $parts = preg_split('/\s+/', trim((string) $user->getName()), 2) ?: [];
            if ($user->getFirstName() === null && isset($parts[0])) {
                $user->setFirstName($parts[0]);
            }
            if ($user->getLastName() === null) {
                $user->setLastName($parts[1] ?? $parts[0] ?? '');
            }
        }

        $form = $this->createForm(ClientProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $form->get('avatarFile')->getData();

            if ($avatarFile instanceof UploadedFile) {
                $uploadsDir = (string) $this->getParameter('avatars_directory');
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0775, true);
                }

                $originalName = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalName)->lower();
                $extension = $avatarFile->guessExtension() ?: 'bin';
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

                try {
                    $avatarFile->move($uploadsDir, $newFilename);

                    $previousAvatar = $user->getAvatar();
                    $user->setAvatar($newFilename);
                    if ($previousAvatar && $previousAvatar !== $newFilename) {
                        $oldPath = $uploadsDir . DIRECTORY_SEPARATOR . $previousAvatar;
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                } catch (FileException $e) {
                    $this->addFlash('error', 'Avatar upload failed. Please try again.');

                    return $this->redirectToRoute('client_profile');
                }
            }

            $user->setName(trim((string) $user->getFirstName() . ' ' . (string) $user->getLastName()));
            $em->flush();

            $this->addFlash('success', 'Profile updated successfully.');

            return $this->redirectToRoute('client_profile');
        }

        return $this->render('client/profile.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
}

