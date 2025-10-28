<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

/**
 * In-Memory Message Broker Service
 * Implements pub/sub pattern for WebSocket fleet communication
 * Can be replaced with RabbitMQ/Redis when extensions are available
 */
class InMemoryMessageBroker implements MessageBrokerInterface
{
    private array $subscribers = [];
    private array $channels = [];
    private LoggerInterface $logger;
    private LoopInterface $loop;

    public function __construct(LoggerInterface $logger, LoopInterface $loop)
    {
        $this->logger = $logger;
        $this->loop = $loop;
    }

    public function publish(string $channel, array $message): void
    {
        $this->logger->info("Publishing message to channel: {$channel}", [
            'message' => $message,
            'subscribers' => count($this->subscribers[$channel] ?? [])
        ]);

        // Store message in channel history
        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = [];
        }
        
        $message['_timestamp'] = time();
        $message['_id'] = uniqid('msg_');
        $this->channels[$channel][] = $message;

        // Keep only last 100 messages per channel
        if (count($this->channels[$channel]) > 100) {
            $this->channels[$channel] = array_slice($this->channels[$channel], -100);
        }

        // Notify all subscribers
        if (isset($this->subscribers[$channel])) {
            foreach ($this->subscribers[$channel] as $callback) {
                try {
                    $this->loop->futureTick(function() use ($callback, $message) {
                        $callback($message);
                    });
                } catch (\Exception $e) {
                    $this->logger->error("Error notifying subscriber: " . $e->getMessage());
                }
            }
        }
    }

    public function subscribe(string $channel, callable $callback): string
    {
        if (!isset($this->subscribers[$channel])) {
            $this->subscribers[$channel] = [];
        }

        $subscriptionId = uniqid('sub_');
        $this->subscribers[$channel][$subscriptionId] = $callback;

        $this->logger->info("New subscription to channel: {$channel}", [
            'subscription_id' => $subscriptionId,
            'total_subscribers' => count($this->subscribers[$channel])
        ]);

        return $subscriptionId;
    }

    public function unsubscribe(string $channel, string $subscriptionId): void
    {
        if (isset($this->subscribers[$channel][$subscriptionId])) {
            unset($this->subscribers[$channel][$subscriptionId]);
            $this->logger->info("Unsubscribed from channel: {$channel}", [
                'subscription_id' => $subscriptionId
            ]);
        }
    }

    public function getChannelHistory(string $channel, int $limit = 50): array
    {
        return array_slice($this->channels[$channel] ?? [], -$limit);
    }

    public function getStats(): array
    {
        $stats = [];
        foreach ($this->subscribers as $channel => $subs) {
            $stats[$channel] = [
                'subscribers' => count($subs),
                'messages' => count($this->channels[$channel] ?? [])
            ];
        }
        return $stats;
    }
}