<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class MessageBroadcastService
{
    private static array $connectedServers = [];
    private static array $messageQueue = [];

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function registerServer(string $serverId, int $port): void
    {
        self::$connectedServers[$serverId] = [
            'port' => $port,
            'registered_at' => time()
        ];
        
        $this->logger->info("WebSocket server registered: {$serverId} on port {$port}");
    }

    public function unregisterServer(string $serverId): void
    {
        unset(self::$connectedServers[$serverId]);
        $this->logger->info("WebSocket server unregistered: {$serverId}");
    }

    public function broadcastMessage(array $messageData, string $roomId): void
    {
        // For now, we'll just store messages in memory
        // In a real multi-server setup, you'd use Redis or database for this
        self::$messageQueue[] = [
            'data' => $messageData,
            'room_id' => $roomId,
            'timestamp' => time()
        ];

        // Clean old messages (keep only last 100)
        if (count(self::$messageQueue) > 100) {
            self::$messageQueue = array_slice(self::$messageQueue, -100);
        }

        $this->logger->info("Message queued for broadcast to room: {$roomId}");
    }

    public function getRecentMessages(string $roomId, int $limit = 20): array
    {
        $roomMessages = array_filter(self::$messageQueue, function($msg) use ($roomId) {
            return $msg['room_id'] === $roomId;
        });

        return array_slice($roomMessages, -$limit);
    }

    public function publishUserStatusChange(int $userId, string $username, bool $isOnline, string $roomId = 'general'): void
    {
        $statusData = [
            'type' => 'user_status',
            'userId' => $userId,
            'username' => $username,
            'isOnline' => $isOnline,
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ];

        $this->broadcastMessage($statusData, $roomId);
    }

    public function getConnectedServers(): array
    {
        return self::$connectedServers;
    }
}