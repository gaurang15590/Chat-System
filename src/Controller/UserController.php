<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/users', name: 'api_users_')]
class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['username']) || !isset($data['email'])) {
            return $this->json(['error' => 'Username and email are required'], 400);
        }

        // Check if user already exists
        $existingUser = $this->userRepository->findByUsername($data['username']);
        if ($existingUser) {
            return $this->json(['error' => 'Username already exists'], 409);
        }

        $user = new User();
        $user->setUsername($data['username'])
             ->setEmail($data['email']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'User created successfully',
            'user' => json_decode($this->serializer->serialize($user, 'json', ['groups' => ['user:read']]))
        ], 201);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        return $this->json(
            json_decode($this->serializer->serialize($user, 'json', ['groups' => ['user:read']]))
        );
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $onlineOnly = $request->query->getBoolean('online_only', false);
        
        if ($onlineOnly) {
            $users = $this->userRepository->findOnlineUsers();
        } else {
            $users = $this->userRepository->findAll();
        }

        return $this->json([
            'users' => json_decode($this->serializer->serialize($users, 'json', ['groups' => ['user:read']]))
        ]);
    }

    #[Route('/username/{username}', name: 'get_by_username', methods: ['GET'])]
    public function getByUsername(string $username): JsonResponse
    {
        $user = $this->userRepository->findByUsername($username);
        
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        return $this->json(
            json_decode($this->serializer->serialize($user, 'json', ['groups' => ['user:read']]))
        );
    }

    #[Route('/{id}/status', name: 'update_status', methods: ['PATCH'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $isOnline = $data['is_online'] ?? null;

        if ($isOnline === null) {
            return $this->json(['error' => 'is_online field is required'], 400);
        }

        $this->userRepository->setOnlineStatus($user, (bool) $isOnline);

        return $this->json([
            'message' => 'User status updated successfully',
            'user' => json_decode($this->serializer->serialize($user, 'json', ['groups' => ['user:read']]))
        ]);
    }

    #[Route('/online', name: 'online_count', methods: ['GET'])]
    public function getOnlineCount(): JsonResponse
    {
        $onlineUsers = $this->userRepository->findOnlineUsers();
        
        return $this->json([
            'count' => count($onlineUsers),
            'users' => json_decode($this->serializer->serialize($onlineUsers, 'json', ['groups' => ['user:read']])),
            'generated_at' => (new \DateTimeImmutable())->format('c')
        ]);
    }
}