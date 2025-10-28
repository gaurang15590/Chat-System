<?php

namespace App\WebSocket;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\MessageBroadcastService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use Symfony\Component\Serializer\SerializerInterface;

class WebSocketServer
{
    private ConnectionManager $connectionManager;
    private SocketServer $socket;

    public function __construct(
        private string $host,
        private int $port,
        private LoopInterface $loop,
        private UserRepository $userRepository,
        private MessageRepository $messageRepository,
        private EntityManagerInterface $entityManager,
        private MessageBroadcastService $messageBroadcastService,
        private SerializerInterface $serializer,
        private LoggerInterface $logger
    ) {
        $this->connectionManager = new ConnectionManager(
            $this->userRepository,
            $this->entityManager,
            $this->messageBroadcastService,
            $this->serializer,
            $this->logger
        );
    }

    public function start(): void
    {
        $this->socket = new SocketServer("{$this->host}:{$this->port}", [], $this->loop);

        $this->socket->on('connection', function (ConnectionInterface $connection) {
            $this->logger->info('New WebSocket connection established');

            $connection->on('data', function ($data) use ($connection) {
                $this->handleMessage($connection, $data);
            });

            $connection->on('close', function () use ($connection) {
                $this->connectionManager->removeConnection($connection);
                $this->logger->info('WebSocket connection closed');
            });

            $connection->on('error', function (\Exception $e) use ($connection) {
                $this->logger->error('WebSocket connection error: ' . $e->getMessage());
                $this->connectionManager->removeConnection($connection);
            });
        });

        // Register this server instance
        $this->messageBroadcastService->registerServer("server_{$this->port}", $this->port);

        $this->logger->info("WebSocket server started on {$this->host}:{$this->port}");
    }

    private function handleMessage(ConnectionInterface $connection, string $data): void
    {
        try {
            $message = json_decode($data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError($connection, 'Invalid JSON format');
                return;
            }

            $type = $message['type'] ?? null;

            switch ($type) {
                case 'auth':
                    $this->handleAuth($connection, $message);
                    break;
                case 'chat_message':
                    $this->handleChatMessage($connection, $message);
                    break;
                case 'join_room':
                    $this->handleJoinRoom($connection, $message);
                    break;
                case 'get_recent_messages':
                    $this->handleGetRecentMessages($connection, $message);
                    break;
                case 'ping':
                    $this->handlePing($connection);
                    break;
                default:
                    $this->sendError($connection, 'Unknown message type');
            }
        } catch (\Exception $e) {
            $this->logger->error('Error handling WebSocket message: ' . $e->getMessage());
            $this->sendError($connection, 'Server error');
        }
    }

    private function handleAuth(ConnectionInterface $connection, array $message): void
    {
        $userId = $message['userId'] ?? null;
        $roomId = $message['roomId'] ?? 'general';

        if (!$userId) {
            $this->sendError($connection, 'User ID is required');
            return;
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            $this->sendError($connection, 'User not found');
            return;
        }

        $this->connectionManager->addConnection($connection, [
            'userId' => $userId,
            'roomId' => $roomId
        ]);

        $response = [
            'type' => 'auth_success',
            'userId' => $userId,
            'username' => $user->getUsername(),
            'roomId' => $roomId
        ];

        $connection->write(json_encode($response));

        // Broadcast user joined
        $this->connectionManager->broadcastToRoom($roomId, [
            'type' => 'user_joined',
            'userId' => $userId,
            'username' => $user->getUsername(),
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ]);
    }

    private function handleChatMessage(ConnectionInterface $connection, array $message): void
    {
        $content = $message['content'] ?? '';
        $roomId = $message['roomId'] ?? 'general';
        $userId = $message['userId'] ?? null;

        if (empty($content) || !$userId) {
            $this->sendError($connection, 'Content and user ID are required');
            return;
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            $this->sendError($connection, 'User not found');
            return;
        }

        // Save message to database
        $messageEntity = new Message();
        $messageEntity->setContent($content)
                     ->setSender($user)
                     ->setRoomId($roomId)
                     ->setMessageType('text');

        $this->entityManager->persist($messageEntity);
        $this->entityManager->flush();

        // Prepare message for broadcasting
        $broadcastMessage = [
            'type' => 'chat_message',
            'id' => $messageEntity->getId(),
            'content' => $content,
            'senderId' => $userId,
            'senderUsername' => $user->getUsername(),
            'roomId' => $roomId,
            'messageType' => 'text',
            'timestamp' => $messageEntity->getCreatedAt()->format('c')
        ];

        // Publish message for distribution across WebSocket servers
        $this->messageBroadcastService->broadcastMessage($broadcastMessage, $roomId);
        
        // Broadcast to local connections immediately
        $this->connectionManager->broadcastToRoom($roomId, $broadcastMessage);
    }

    private function handleJoinRoom(ConnectionInterface $connection, array $message): void
    {
        $roomId = $message['roomId'] ?? null;
        $userId = $message['userId'] ?? null;

        if (!$roomId || !$userId) {
            $this->sendError($connection, 'Room ID and User ID are required');
            return;
        }

        // For simplicity, we'll assume connection is already authenticated
        // In a real app, you'd verify the user's session/token

        $response = [
            'type' => 'room_joined',
            'roomId' => $roomId,
            'onlineUsers' => $this->connectionManager->getOnlineUsersInRoom($roomId)
        ];

        $connection->write(json_encode($response));
    }

    private function handleGetRecentMessages(ConnectionInterface $connection, array $message): void
    {
        $roomId = $message['roomId'] ?? 'general';
        $limit = min($message['limit'] ?? 20, 100); // Max 100 messages

        $messages = $this->messageRepository->findRecentByRoom($roomId, $limit);
        
        $messageData = array_map(function (Message $msg) {
            return [
                'id' => $msg->getId(),
                'content' => $msg->getContent(),
                'senderId' => $msg->getSender()->getId(),
                'senderUsername' => $msg->getSender()->getUsername(),
                'roomId' => $msg->getRoomId(),
                'messageType' => $msg->getMessageType(),
                'timestamp' => $msg->getCreatedAt()->format('c')
            ];
        }, array_reverse($messages));

        $response = [
            'type' => 'recent_messages',
            'roomId' => $roomId,
            'messages' => $messageData
        ];

        $connection->write(json_encode($response));
    }

    private function handlePing(ConnectionInterface $connection): void
    {
        $connection->write(json_encode(['type' => 'pong']));
    }

    private function sendError(ConnectionInterface $connection, string $error): void
    {
        $response = [
            'type' => 'error',
            'message' => $error
        ];

        $connection->write(json_encode($response));
    }



    public function getConnectionManager(): ConnectionManager
    {
        return $this->connectionManager;
    }
}