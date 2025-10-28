<?php

namespace App\WebSocket;

use App\Entity\User;
use App\Entity\Message;
use App\Repository\UserRepository;
use App\Service\MessageBrokerInterface;
use App\Service\WebSocketFleetManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Real-time WebSocket Server with Fleet Support
 * Handles WebSocket connections and integrates with pub/sub messaging
 */
class FleetWebSocketServer
{
    private SocketServer $socket;
    private array $connections = [];
    private array $userConnections = [];
    private string $serverId;
    private string $fleetSubscriptionId;

    public function __construct(
        private string $host,
        private int $port,
        private LoopInterface $loop,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private MessageBrokerInterface $messageBroker,
        private WebSocketFleetManager $fleetManager,
        private SerializerInterface $serializer,
        private LoggerInterface $logger
    ) {
        $this->serverId = "ws_server_{$this->port}_" . uniqid();
    }

    public function start(): void
    {
        // Register this server with the fleet
        $this->fleetManager->registerServer($this->serverId, $this->port);

        // Subscribe to fleet-wide messages
        $this->fleetSubscriptionId = $this->messageBroker->subscribe(
            'chat.messages',
            [$this, 'handleFleetMessage']
        );

        // Create socket server
        $this->socket = new SocketServer("{$this->host}:{$this->port}", [], $this->loop);

        $this->socket->on('connection', function (ConnectionInterface $connection) {
            $this->handleNewConnection($connection);
        });

        // Set up heartbeat to update fleet stats
        $this->loop->addPeriodicTimer(10, function() {
            $this->fleetManager->updateServerConnections(
                $this->serverId,
                count($this->connections)
            );
        });

        $this->logger->info("WebSocket server started in fleet", [
            'server_id' => $this->serverId,
            'host' => $this->host,
            'port' => $this->port
        ]);
    }

    private function handleNewConnection(ConnectionInterface $connection): void
    {
        $connectionId = uniqid('conn_');
        $this->connections[$connectionId] = [
            'connection' => $connection,
            'user_id' => null,
            'rooms' => [],
            'created_at' => time()
        ];

        $this->logger->info('New WebSocket connection', [
            'connection_id' => $connectionId,
            'total_connections' => count($this->connections)
        ]);

        // Handle incoming messages
        $connection->on('data', function ($data) use ($connectionId) {
            $this->handleConnectionMessage($connectionId, $data);
        });

        // Handle connection close
        $connection->on('close', function () use ($connectionId) {
            $this->handleConnectionClose($connectionId);
        });

        // Handle connection error
        $connection->on('error', function (\Exception $e) use ($connectionId) {
            $this->logger->error('WebSocket connection error', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            $this->handleConnectionClose($connectionId);
        });

        // Send welcome message
        $this->sendToConnection($connectionId, [
            'type' => 'connected',
            'server_id' => $this->serverId,
            'connection_id' => $connectionId,
            'message' => 'Connected to WebSocket server'
        ]);
    }

    private function handleConnectionMessage(string $connectionId, string $data): void
    {
        try {
            $message = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError($connectionId, 'Invalid JSON format');
                return;
            }

            $this->logger->debug('Received WebSocket message', [
                'connection_id' => $connectionId,
                'type' => $message['type'] ?? 'unknown',
                'message' => $message
            ]);

            $type = $message['type'] ?? null;

            switch ($type) {
                case 'auth':
                    $this->handleAuthentication($connectionId, $message);
                    break;
                
                case 'join_room':
                    $this->handleJoinRoom($connectionId, $message);
                    break;
                
                case 'leave_room':
                    $this->handleLeaveRoom($connectionId, $message);
                    break;
                
                case 'chat_message':
                    $this->handleChatMessage($connectionId, $message);
                    break;
                
                case 'ping':
                    $this->sendToConnection($connectionId, ['type' => 'pong']);
                    break;
                
                default:
                    $this->sendError($connectionId, 'Unknown message type: ' . $type);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error handling connection message', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            $this->sendError($connectionId, 'Internal server error');
        }
    }

    private function handleAuthentication(string $connectionId, array $message): void
    {
        $userId = $message['user_id'] ?? null;
        
        if (!$userId) {
            $this->sendError($connectionId, 'User ID required for authentication');
            return;
        }

        // Find user in database
        $user = $this->userRepository->find($userId);
        if (!$user) {
            $this->sendError($connectionId, 'User not found');
            return;
        }

        // Update connection with user info
        $this->connections[$connectionId]['user_id'] = $userId;
        $this->userConnections[$userId] = $connectionId;

        // Update user online status
        $user->setIsOnline(true);
        $user->setLastSeenAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->sendToConnection($connectionId, [
            'type' => 'auth_success',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail()
            ]
        ]);

        $this->logger->info('User authenticated', [
            'connection_id' => $connectionId,
            'user_id' => $userId,
            'username' => $user->getUsername()
        ]);

        // Notify fleet about user join
        $this->messageBroker->publish('user.events', [
            'type' => 'user_online',
            'user_id' => $userId,
            'username' => $user->getUsername(),
            'server_id' => $this->serverId
        ]);
    }

    private function handleJoinRoom(string $connectionId, array $message): void
    {
        $roomId = $message['room_id'] ?? null;
        
        if (!$roomId) {
            $this->sendError($connectionId, 'Room ID required');
            return;
        }

        if (!isset($this->connections[$connectionId])) {
            return;
        }

        $this->connections[$connectionId]['rooms'][] = $roomId;

        $this->sendToConnection($connectionId, [
            'type' => 'joined_room',
            'room_id' => $roomId
        ]);

        // Send recent messages from this room
        $this->sendRoomHistory($connectionId, $roomId);
    }

    private function handleChatMessage(string $connectionId, array $message): void
    {
        if (!isset($this->connections[$connectionId])) {
            return;
        }

        $connection = $this->connections[$connectionId];
        $userId = $connection['user_id'];
        
        if (!$userId) {
            $this->sendError($connectionId, 'Authentication required');
            return;
        }

        $roomId = $message['room_id'] ?? 'general';
        $content = $message['content'] ?? '';

        if (empty($content)) {
            $this->sendError($connectionId, 'Message content cannot be empty');
            return;
        }

        // Get user info
        $user = $this->userRepository->find($userId);
        if (!$user) {
            $this->sendError($connectionId, 'User not found');
            return;
        }

        // Create message entity
        $messageEntity = new Message();
        $messageEntity->setSender($user);
        $messageEntity->setContent($content);
        $messageEntity->setRoomId($roomId);
        $messageEntity->setMessageType('text');
        $messageEntity->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($messageEntity);
        $this->entityManager->flush();

        // Create message for broadcasting
        $broadcastMessage = [
            'type' => 'chat_message',
            'id' => $messageEntity->getId(),
            'content' => $content,
            'room_id' => $roomId,
            'sender' => [
                'id' => $user->getId(),
                'username' => $user->getUsername()
            ],
            'timestamp' => $messageEntity->getCreatedAt()->format('c'),
            'server_id' => $this->serverId
        ];

        // Publish to message broker for fleet-wide delivery
        $this->messageBroker->publish('chat.messages', $broadcastMessage);

        $this->fleetManager->recordServerStat($this->serverId, 'messages_sent');

        $this->logger->info('Chat message broadcasted', [
            'message_id' => $messageEntity->getId(),
            'user_id' => $userId,
            'room_id' => $roomId,
            'server_id' => $this->serverId
        ]);
    }

    /**
     * Handle messages from other servers in the fleet
     */
    public function handleFleetMessage(array $message): void
    {
        if ($message['server_id'] === $this->serverId) {
            // Don't echo our own messages
            return;
        }

        $this->logger->debug('Received fleet message', [
            'type' => $message['type'] ?? 'unknown',
            'origin_server' => $message['server_id'] ?? 'unknown',
            'current_server' => $this->serverId
        ]);

        // Broadcast to relevant local connections
        if ($message['type'] === 'chat_message') {
            $this->broadcastToRoom($message['room_id'], $message);
        }
    }

    private function broadcastToRoom(string $roomId, array $message): void
    {
        foreach ($this->connections as $connectionId => $connectionData) {
            if (in_array($roomId, $connectionData['rooms'])) {
                $this->sendToConnection($connectionId, $message);
            }
        }
    }

    private function sendRoomHistory(string $connectionId, string $roomId): void
    {
        // Get recent messages from message broker
        $recentMessages = $this->messageBroker->getChannelHistory("room_{$roomId}", 20);
        
        if (!empty($recentMessages)) {
            $this->sendToConnection($connectionId, [
                'type' => 'room_history',
                'room_id' => $roomId,
                'messages' => $recentMessages
            ]);
        }
    }

    private function sendToConnection(string $connectionId, array $message): void
    {
        if (!isset($this->connections[$connectionId])) {
            return;
        }

        try {
            $connection = $this->connections[$connectionId]['connection'];
            $connection->write(json_encode($message));
        } catch (\Exception $e) {
            $this->logger->error('Failed to send message to connection', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage()
            ]);
            $this->handleConnectionClose($connectionId);
        }
    }

    private function sendError(string $connectionId, string $error): void
    {
        $this->sendToConnection($connectionId, [
            'type' => 'error',
            'message' => $error
        ]);
    }

    private function handleConnectionClose(string $connectionId): void
    {
        if (!isset($this->connections[$connectionId])) {
            return;
        }

        $connection = $this->connections[$connectionId];
        $userId = $connection['user_id'];

        // Update user offline status
        if ($userId) {
            $user = $this->userRepository->find($userId);
            if ($user) {
                $user->setIsOnline(false);
                $user->setLastSeenAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                // Notify fleet about user leave
                $this->messageBroker->publish('user.events', [
                    'type' => 'user_offline',
                    'user_id' => $userId,
                    'username' => $user->getUsername(),
                    'server_id' => $this->serverId
                ]);
            }
            
            unset($this->userConnections[$userId]);
        }

        unset($this->connections[$connectionId]);

        $this->logger->info('WebSocket connection closed', [
            'connection_id' => $connectionId,
            'user_id' => $userId,
            'remaining_connections' => count($this->connections)
        ]);
    }

    public function getStats(): array
    {
        return [
            'server_id' => $this->serverId,
            'host' => $this->host,
            'port' => $this->port,
            'connections' => count($this->connections),
            'authenticated_users' => count($this->userConnections),
            'fleet_stats' => $this->fleetManager->getFleetStats()
        ];
    }

    public function stop(): void
    {
        if ($this->fleetSubscriptionId) {
            $this->messageBroker->unsubscribe('chat.messages', $this->fleetSubscriptionId);
        }
        
        // Close all connections
        foreach ($this->connections as $connectionId => $connectionData) {
            $this->handleConnectionClose($connectionId);
        }

        if ($this->socket) {
            $this->socket->close();
        }

        $this->logger->info('WebSocket server stopped', [
            'server_id' => $this->serverId
        ]);
    }
}