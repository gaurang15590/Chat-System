<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

/**
 * WebSocket Fleet Manager
 * Manages multiple WebSocket servers and load balancing
 */
class WebSocketFleetManager
{
    private array $servers = [];
    private array $serverStats = [];
    private LoggerInterface $logger;
    private LoopInterface $loop;
    private MessageBrokerInterface $messageBroker;

    public function __construct(
        LoggerInterface $logger,
        LoopInterface $loop,
        MessageBrokerInterface $messageBroker
    ) {
        $this->logger = $logger;
        $this->loop = $loop;
        $this->messageBroker = $messageBroker;
    }

    /**
     * Register a WebSocket server in the fleet
     */
    public function registerServer(string $serverId, int $port, int $maxConnections = 1000): void
    {
        $this->servers[$serverId] = [
            'id' => $serverId,
            'port' => $port,
            'max_connections' => $maxConnections,
            'current_connections' => 0,
            'status' => 'active',
            'last_heartbeat' => time()
        ];

        $this->serverStats[$serverId] = [
            'messages_sent' => 0,
            'messages_received' => 0,
            'connections_total' => 0,
            'uptime_start' => time()
        ];

        $this->logger->info("Registered WebSocket server in fleet", [
            'server_id' => $serverId,
            'port' => $port,
            'max_connections' => $maxConnections
        ]);

        // Subscribe to fleet-wide messages
        $this->messageBroker->subscribe("fleet.broadcast", function($message) use ($serverId) {
            if ($message['origin_server'] !== $serverId) {
                $this->logger->debug("Relaying message from another server", [
                    'origin_server' => $message['origin_server'],
                    'target_server' => $serverId,
                    'message_type' => $message['type'] ?? 'unknown'
                ]);
            }
        });
    }

    /**
     * Update server connection count
     */
    public function updateServerConnections(string $serverId, int $connectionCount): void
    {
        if (isset($this->servers[$serverId])) {
            $this->servers[$serverId]['current_connections'] = $connectionCount;
            $this->servers[$serverId]['last_heartbeat'] = time();
        }
    }

    /**
     * Get least loaded server for new connections
     */
    public function getLeastLoadedServer(): ?array
    {
        $activeServers = array_filter($this->servers, function($server) {
            return $server['status'] === 'active' && 
                   $server['current_connections'] < $server['max_connections'] &&
                   (time() - $server['last_heartbeat']) < 30; // 30 second timeout
        });

        if (empty($activeServers)) {
            return null;
        }

        // Sort by connection load
        uasort($activeServers, function($a, $b) {
            $loadA = $a['current_connections'] / $a['max_connections'];
            $loadB = $b['current_connections'] / $b['max_connections'];
            return $loadA <=> $loadB;
        });

        return reset($activeServers);
    }

    /**
     * Broadcast message to all servers in fleet
     */
    public function broadcastToFleet(string $originServerId, array $message): void
    {
        $fleetMessage = [
            'origin_server' => $originServerId,
            'timestamp' => time(),
            'payload' => $message
        ];

        $this->messageBroker->publish('fleet.broadcast', $fleetMessage);
        
        $this->logger->info("Broadcasting to WebSocket fleet", [
            'origin_server' => $originServerId,
            'message_type' => $message['type'] ?? 'unknown',
            'active_servers' => count($this->getActiveServers())
        ]);
    }

    /**
     * Get all active servers
     */
    public function getActiveServers(): array
    {
        return array_filter($this->servers, function($server) {
            return $server['status'] === 'active' && 
                   (time() - $server['last_heartbeat']) < 30;
        });
    }

    /**
     * Get fleet statistics
     */
    public function getFleetStats(): array
    {
        $totalConnections = 0;
        $activeServers = 0;

        foreach ($this->servers as $server) {
            if ($server['status'] === 'active') {
                $activeServers++;
                $totalConnections += $server['current_connections'];
            }
        }

        return [
            'total_servers' => count($this->servers),
            'active_servers' => $activeServers,
            'total_connections' => $totalConnections,
            'broker_stats' => $this->messageBroker->getStats(),
            'servers' => $this->servers,
            'server_stats' => $this->serverStats
        ];
    }

    /**
     * Record server statistics
     */
    public function recordServerStat(string $serverId, string $metric, int $increment = 1): void
    {
        if (isset($this->serverStats[$serverId][$metric])) {
            $this->serverStats[$serverId][$metric] += $increment;
        }
    }
}