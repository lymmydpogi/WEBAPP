<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function profile(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User not found.');
        }

        // -------------------------------
        // Avatar upload (separate form)
        // -------------------------------
        if ($request->isMethod('POST') && $request->files->has('avatar')) {
            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $request->files->get('avatar');

            if ($avatarFile) {
                $uploadsDir = $this->getParameter('avatars_directory');
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0775, true);
                }

                $newFilename = uniqid() . '.' . $avatarFile->guessExtension();
                try {
                    $avatarFile->move($uploadsDir, $newFilename);
                    $user->setAvatar($newFilename);
                    $em->flush();
                    $this->addFlash('success', 'Profile picture updated successfully!');
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload avatar: ' . $e->getMessage());
                }
            }

            return $this->redirectToRoute('app_profile');
        }

        // -------------------------------
        // Profile info form
        // -------------------------------
        $profileForm = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
            'is_profile' => true,
        ]);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('app_profile');
        }

        // -------------------------------
        // Password modal form
        // -------------------------------
        $passwordForm = $this->createForm(ChangePasswordType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $newPassword = $passwordForm->get('new_password')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $em->flush();
            $this->addFlash('success', 'Password updated successfully!');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'profileForm' => $profileForm->createView(),
            'passwordForm' => $passwordForm->createView(),
        ]);
    }
}
