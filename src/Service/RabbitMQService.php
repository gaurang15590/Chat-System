<?php

namespace App\Service;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use Psr\Log\LoggerInterface;

class RabbitMQService
{
    private AMQPStreamConnection $connection;
    private AMQPChannel $channel;
    private const EXCHANGE_NAME = 'chat_exchange';
    private const QUEUE_NAME = 'chat_messages';

    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $password,
        private string $vhost,
        private LoggerInterface $logger
    ) {
        $this->connect();
        $this->setupExchangeAndQueue();
    }

    private function connect(): void
    {
        try {
            $this->connection = new AMQPStreamConnection(
                $this->host,
                $this->port,
                $this->user,
                $this->password,
                $this->vhost
            );
            $this->channel = $this->connection->channel();
            $this->logger->info('Connected to RabbitMQ successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to connect to RabbitMQ: ' . $e->getMessage());
            throw $e;
        }
    }

    private function setupExchangeAndQueue(): void
    {
        try {
            // Declare exchange
            $this->channel->exchange_declare(
                self::EXCHANGE_NAME,
                'topic',
                false,
                true,
                false
            );

            // Declare queue
            $this->channel->queue_declare(
                self::QUEUE_NAME,
                false,
                true,
                false,
                false
            );

            // Bind queue to exchange with routing key pattern
            $this->channel->queue_bind(
                self::QUEUE_NAME,
                self::EXCHANGE_NAME,
                'chat.*'
            );

            $this->logger->info('RabbitMQ exchange and queue setup completed');
        } catch (\Exception $e) {
            $this->logger->error('Failed to setup RabbitMQ exchange and queue: ' . $e->getMessage());
            throw $e;
        }
    }

    public function publishMessage(array $messageData, string $roomId): void
    {
        try {
            $message = new AMQPMessage(
                json_encode($messageData),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            $routingKey = 'chat.' . $roomId;

            $this->channel->basic_publish($message, self::EXCHANGE_NAME, $routingKey);
            $this->logger->info("Published message to room: {$roomId}");
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish message: ' . $e->getMessage());
            throw $e;
        }
    }

    public function consumeMessages(callable $callback): void
    {
        try {
            $this->channel->basic_consume(
                self::QUEUE_NAME,
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $msg) use ($callback) {
                    try {
                        $messageData = json_decode($msg->getBody(), true);
                        $callback($messageData);
                        $msg->ack();
                    } catch (\Exception $e) {
                        $this->logger->error('Error processing message: ' . $e->getMessage());
                        $msg->nack(false, false);
                    }
                }
            );

            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to consume messages: ' . $e->getMessage());
            throw $e;
        }
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

        $this->publishMessage($statusData, $roomId);
    }

    public function close(): void
    {
        try {
            $this->channel->close();
            $this->connection->close();
            $this->logger->info('RabbitMQ connection closed');
        } catch (\Exception $e) {
            $this->logger->error('Error closing RabbitMQ connection: ' . $e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}