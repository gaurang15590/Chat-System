<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users', name: 'api_users_')]
class SimpleUserController extends AbstractController
{
    private static array $users = [
        1 => ['id' => 1, 'username' => 'demo_user', 'email' => 'demo@example.com'],
    ];
    private static int $nextId = 2;

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['username']) || !isset($data['email'])) {
            return $this->json(['error' => 'Username and email are required'], 400);
        }

        // Check if username already exists
        foreach (self::$users as $user) {
            if ($user['username'] === $data['username']) {
                return $this->json([
                    'message' => 'User already exists',
                    'user' => $user
                ], 200);
            }
        }

        // Create new user
        $user = [
            'id' => self::$nextId++,
            'username' => $data['username'],
            'email' => $data['email'],
            'isOnline' => true,
            'createdAt' => (new \DateTimeImmutable())->format('c')
        ];

        self::$users[$user['id']] = $user;

        return $this->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_values(self::$users));
    }

    #[Route('/username/{username}', name: 'get_by_username', methods: ['GET'])]
    public function getByUsername(string $username): JsonResponse
    {
        foreach (self::$users as $user) {
            if ($user['username'] === $username) {
                return $this->json($user);
            }
        }

        return $this->json(['error' => 'User not found'], 404);
    }
}