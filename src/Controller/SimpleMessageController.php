<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/messages', name: 'api_messages_')]
class SimpleMessageController extends AbstractController
{
    private static array $messages = [];
    private static int $nextId = 1;

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['content']) || !isset($data['sender_id']) || !isset($data['room_id'])) {
            return $this->json(['error' => 'Content, sender_id, and room_id are required'], 400);
        }

        $message = [
            'id' => self::$nextId++,
            'content' => $data['content'],
            'senderId' => $data['sender_id'],
            'senderUsername' => 'User' . $data['sender_id'], // Simplified
            'roomId' => $data['room_id'],
            'messageType' => $data['message_type'] ?? 'text',
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ];

        self::$messages[] = $message;

        return $this->json([
            'message' => 'Message sent successfully',
            'data' => $message
        ], 201);
    }

    #[Route('/room/{roomId}/recent', name: 'get_recent', methods: ['GET'])]
    public function getRecent(string $roomId, Request $request): JsonResponse
    {
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $lastId = $request->query->getInt('lastId', 0);
        
        // Filter messages by room and ID
        $roomMessages = array_filter(self::$messages, function($msg) use ($roomId, $lastId) {
            return $msg['roomId'] === $roomId && $msg['id'] > $lastId;
        });

        // Get most recent messages
        $roomMessages = array_slice(array_reverse($roomMessages), 0, $limit);
        $roomMessages = array_reverse($roomMessages); // Chronological order

        return $this->json([
            'messages' => array_values($roomMessages),
            'room_id' => $roomId,
            'count' => count($roomMessages)
        ]);
    }
}