<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;

use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/messages', name: 'api_messages_')]
class MessageController extends AbstractController
{
    public function __construct(
        private MessageRepository $messageRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,

        private SerializerInterface $serializer
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['content']) || !isset($data['sender_id']) || !isset($data['room_id'])) {
            return $this->json(['error' => 'Content, sender_id, and room_id are required'], 400);
        }

        $sender = $this->userRepository->find($data['sender_id']);
        if (!$sender) {
            return $this->json(['error' => 'Sender not found'], 404);
        }

        // Save message to database
        $message = new Message();
        $message->setContent($data['content'])
                ->setSender($sender)
                ->setRoomId($data['room_id'])
                ->setMessageType($data['message_type'] ?? 'text')
                ->setMetadata($data['metadata'] ?? null);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        // For now, we'll handle message broadcasting within the WebSocket server directly
        // In a multi-server setup, you could use Redis or database for synchronization

        return $this->json([
            'message' => 'Message sent successfully',
            'data' => json_decode($this->serializer->serialize($message, 'json', ['groups' => ['message:read']]))
        ], 201);
    }

    #[Route('/room/{roomId}', name: 'get_by_room', methods: ['GET'])]
    public function getByRoom(string $roomId, Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));

        $messages = $this->messageRepository->findByRoomIdPaginated($roomId, $page, $limit);
        $total = $this->messageRepository->countByRoomId($roomId);

        return $this->json([
            'messages' => json_decode($this->serializer->serialize($messages, 'json', ['groups' => ['message:read']])),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    #[Route('/room/{roomId}/recent', name: 'get_recent', methods: ['GET'])]
    public function getRecent(string $roomId, Request $request): JsonResponse
    {
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        
        $messages = $this->messageRepository->findRecentByRoom($roomId, $limit);
        
        // Reverse to get chronological order (oldest first)
        $messages = array_reverse($messages);

        return $this->json([
            'messages' => json_decode($this->serializer->serialize($messages, 'json', ['groups' => ['message:read']])),
            'room_id' => $roomId,
            'count' => count($messages)
        ]);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $message = $this->messageRepository->find($id);
        
        if (!$message) {
            return $this->json(['error' => 'Message not found'], 404);
        }

        return $this->json(
            json_decode($this->serializer->serialize($message, 'json', ['groups' => ['message:read']]))
        );
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $message = $this->messageRepository->find($id);
        
        if (!$message) {
            return $this->json(['error' => 'Message not found'], 404);
        }

        $this->entityManager->remove($message);
        $this->entityManager->flush();

        return $this->json(['message' => 'Message deleted successfully']);
    }

    #[Route('/stats/room/{roomId}', name: 'room_stats', methods: ['GET'])]
    public function getRoomStats(string $roomId): JsonResponse
    {
        $totalMessages = $this->messageRepository->countByRoomId($roomId);
        
        return $this->json([
            'room_id' => $roomId,
            'total_messages' => $totalMessages,
            'generated_at' => (new \DateTimeImmutable())->format('c')
        ]);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        // Get total message count
        $totalMessages = $this->messageRepository->count([]);
        
        // Get message count by room
        $roomStats = $this->messageRepository->getMessageCountByRoom();
        
        return $this->json([
            'total_messages' => $totalMessages,
            'room_stats' => $roomStats,
            'generated_at' => (new \DateTimeImmutable())->format('c')
        ]);
    }
}