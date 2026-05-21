<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ClientProfileController extends AbstractController
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

    #[Route('/api/client/profile', name: 'api_client_profile_update', methods: ['PATCH', 'POST'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->apiError('Unauthorized.', Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isGranted('ROLE_CLIENT')) {
            return $this->apiError('Client access required.', Response::HTTP_FORBIDDEN);
        }

        $contentType = (string) $request->headers->get('Content-Type', '');
        $isMultipart = str_contains($contentType, 'multipart/form-data');

        if ($isMultipart) {
            if ($request->request->has('firstName')) {
                $user->setFirstName(trim((string) $request->request->get('firstName')) ?: null);
            }
            if ($request->request->has('lastName')) {
                $user->setLastName(trim((string) $request->request->get('lastName')) ?: null);
            }
            if ($request->request->has('phone')) {
                $user->setPhone(trim((string) $request->request->get('phone')) ?: null);
            }
            if ($request->request->has('address')) {
                $user->setAddress(trim((string) $request->request->get('address')) ?: null);
            }

            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $request->files->get('avatarFile');
            if ($avatarFile instanceof UploadedFile) {
                $error = $this->handleAvatarUpload($user, $avatarFile, $slugger);
                if ($error !== null) {
                    return $this->apiError($error, Response::HTTP_BAD_REQUEST);
                }
            }
        } else {
            $data = json_decode($request->getContent(), true);
            if (!is_array($data)) {
                return $this->apiError('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
            }

            if (array_key_exists('firstName', $data)) {
                $user->setFirstName(trim((string) $data['firstName']) ?: null);
            }
            if (array_key_exists('lastName', $data)) {
                $user->setLastName(trim((string) $data['lastName']) ?: null);
            }
            if (array_key_exists('phone', $data)) {
                $user->setPhone(trim((string) $data['phone']) ?: null);
            }
            if (array_key_exists('address', $data)) {
                $user->setAddress(trim((string) $data['address']) ?: null);
            }
        }

        $first = trim((string) ($user->getFirstName() ?? ''));
        $last = trim((string) ($user->getLastName() ?? ''));
        if ($first !== '' || $last !== '') {
            $user->setName(trim($first . ' ' . $last));
        }

        $entityManager->flush();

        return $this->apiSuccess('Profile updated successfully.', [
            'user' => $this->serializeUser($user, $request),
        ]);
    }

    private function handleAvatarUpload(User $user, UploadedFile $avatarFile, SluggerInterface $slugger): ?string
    {
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
        } catch (FileException) {
            return 'Avatar upload failed. Please try again.';
        }

        return null;
    }

    private function serializeUser(User $user, Request $request): array
    {
        $avatarUrl = null;
        if ($user->getAvatar()) {
            $avatarUrl = $request->getSchemeAndHttpHost() . '/uploads/avatars/' . $user->getAvatar();
        }

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phone' => $user->getPhone(),
            'address' => $user->getAddress(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
            'avatarUrl' => $avatarUrl,
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
