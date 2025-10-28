<?php

namespace App\Service;

/**
 * Message Broker Interface
 * Defines the contract for pub/sub messaging systems
 */
interface MessageBrokerInterface
{
    /**
     * Publish a message to a channel
     */
    public function publish(string $channel, array $message): void;

    /**
     * Subscribe to a channel with a callback
     * Returns subscription ID for unsubscribing
     */
    public function subscribe(string $channel, callable $callback): string;

    /**
     * Unsubscribe from a channel
     */
    public function unsubscribe(string $channel, string $subscriptionId): void;

    /**
     * Get recent messages from channel
     */
    public function getChannelHistory(string $channel, int $limit = 50): array;

    /**
     * Get broker statistics
     */
    public function getStats(): array;
}