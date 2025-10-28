<?php

namespace App\WebSocket;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MessageBroadcastService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use React\Socket\ConnectionInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ConnectionManager
{
    private array $connections = [];
    private array $userConnections = [];
    private array $roomConnections = [];

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private MessageBroadcastService $messageBroadcastService,
        private SerializerInterface $serializer,
        private LoggerInterface $logger
    ) {
    }

    public function addConnection(ConnectionInterface $connection, array $connectionData): void
    {
        $connectionId = spl_object_id($connection);
        $userId = $connectionData['userId'] ?? null;
        $roomId = $connectionData['roomId'] ?? 'general';

        $this->connections[$connectionId] = [
            'connection' => $connection,
            'userId' => $userId,
            'roomId' => $roomId,
            'connectedAt' => new \DateTimeImmutable()
        ];

        if ($userId) {
            $this->userConnections[$userId][] = $connectionId;
            $this->setUserOnlineStatus($userId, true);
        }

        if (!isset($this->roomConnections[$roomId])) {
            $this->roomConnections[$roomId] = [];
        }
        $this->roomConnections[$roomId][] = $connectionId;

        $this->logger->info("Connection added: {$connectionId}, User: {$userId}, Room: {$roomId}");
    }

    public function removeConnection(ConnectionInterface $connection): void
    {
        $connectionId = spl_object_id($connection);
        
        if (!isset($this->connections[$connectionId])) {
            return;
        }

        $connectionData = $this->connections[$connectionId];
        $userId = $connectionData['userId'];
        $roomId = $connectionData['roomId'];

        // Remove from main connections array
        unset($this->connections[$connectionId]);

        // Remove from user connections
        if ($userId && isset($this->userConnections[$userId])) {
            $this->userConnections[$userId] = array_filter(
                $this->userConnections[$userId],
                fn($id) => $id !== $connectionId
            );

            // If user has no more connections, set offline
            if (empty($this->userConnections[$userId])) {
                unset($this->userConnections[$userId]);
                $this->setUserOnlineStatus($userId, false);
            }
        }

        // Remove from room connections
        if (isset($this->roomConnections[$roomId])) {
            $this->roomConnections[$roomId] = array_filter(
                $this->roomConnections[$roomId],
                fn($id) => $id !== $connectionId
            );

            if (empty($this->roomConnections[$roomId])) {
                unset($this->roomConnections[$roomId]);
            }
        }

        $this->logger->info("Connection removed: {$connectionId}, User: {$userId}, Room: {$roomId}");
    }

    public function broadcastToRoom(string $roomId, array $message): void
    {
        if (!isset($this->roomConnections[$roomId])) {
            return;
        }

        $messageJson = json_encode($message);

        foreach ($this->roomConnections[$roomId] as $connectionId) {
            if (isset($this->connections[$connectionId])) {
                $connection = $this->connections[$connectionId]['connection'];
                try {
                    $connection->write($messageJson);
                } catch (\Exception $e) {
                    $this->logger->error("Failed to send message to connection {$connectionId}: " . $e->getMessage());
                }
            }
        }

        $this->logger->info("Broadcast message to room {$roomId}: " . count($this->roomConnections[$roomId]) . " connections");
    }

    public function sendToUser(int $userId, array $message): void
    {
        if (!isset($this->userConnections[$userId])) {
            return;
        }

        $messageJson = json_encode($message);

        foreach ($this->userConnections[$userId] as $connectionId) {
            if (isset($this->connections[$connectionId])) {
                $connection = $this->connections[$connectionId]['connection'];
                try {
                    $connection->write($messageJson);
                } catch (\Exception $e) {
                    $this->logger->error("Failed to send message to user {$userId}, connection {$connectionId}: " . $e->getMessage());
                }
            }
        }
    }

    private function setUserOnlineStatus(int $userId, bool $isOnline): void
    {
        try {
            $user = $this->userRepository->find($userId);
            if ($user) {
                $this->userRepository->setOnlineStatus($user, $isOnline);
                
                // Broadcast user status change
                $this->messageBroadcastService->publishUserStatusChange(
                    $userId,
                    $user->getUsername(),
                    $isOnline
                );
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to update user online status: " . $e->getMessage());
        }
    }

    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    public function getRoomConnectionCount(string $roomId): int
    {
        return isset($this->roomConnections[$roomId]) ? count($this->roomConnections[$roomId]) : 0;
    }

    public function getOnlineUsersInRoom(string $roomId): array
    {
        $onlineUsers = [];
        
        if (!isset($this->roomConnections[$roomId])) {
            return $onlineUsers;
        }

        foreach ($this->roomConnections[$roomId] as $connectionId) {
            if (isset($this->connections[$connectionId])) {
                $userId = $this->connections[$connectionId]['userId'];
                if ($userId && !in_array($userId, $onlineUsers)) {
                    $onlineUsers[] = $userId;
                }
            }
        }

        return $onlineUsers;
    }
}