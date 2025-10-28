<?php

namespace App\Command;

use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'websocket:simple',
    description: 'Start a simple WebSocket server for chat'
)]
class SimpleWebSocketCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $host = '127.0.0.1';
        $port = 8080;

        $io->info("Starting simple WebSocket server on {$host}:{$port}");
        
        try {
            $loop = Loop::get();
            $socket = new SocketServer("{$host}:{$port}", [], $loop);
            
            // Store connections
            $connections = [];
            $users = [];

            $socket->on('connection', function ($connection) use (&$connections, &$users, $io) {
                $io->writeln('New WebSocket connection');
                $connections[] = $connection;

                $connection->on('data', function ($data) use ($connection, &$connections, &$users, $io) {
                    try {
                        $message = json_decode($data, true);
                        $io->writeln('Received: ' . json_encode($message));
                        
                        if (isset($message['type'])) {
                            switch ($message['type']) {
                                case 'auth':
                                    $users[spl_object_hash($connection)] = [
                                        'userId' => $message['userId'],
                                        'roomId' => $message['roomId'] ?? 'general'
                                    ];
                                    
                                    $response = json_encode([
                                        'type' => 'auth_success',
                                        'message' => 'Authentication successful'
                                    ]);
                                    $connection->write($response);
                                    break;
                                    
                                case 'chat_message':
                                    // Broadcast message to all connections
                                    $broadcastMessage = json_encode([
                                        'type' => 'chat_message',
                                        'userId' => $message['userId'],
                                        'username' => $message['username'] ?? 'Anonymous',
                                        'content' => $message['content'],
                                        'roomId' => $message['roomId'] ?? 'general',
                                        'timestamp' => date('Y-m-d H:i:s')
                                    ]);
                                    
                                    foreach ($connections as $conn) {
                                        if ($conn !== $connection) {
                                            $conn->write($broadcastMessage);
                                        }
                                    }
                                    break;
                            }
                        }
                    } catch (\Exception $e) {
                        $io->error('Message handling error: ' . $e->getMessage());
                    }
                });

                $connection->on('close', function () use (&$connections, &$users, $connection, $io) {
                    $io->writeln('Connection closed');
                    $key = array_search($connection, $connections);
                    if ($key !== false) {
                        unset($connections[$key]);
                    }
                    unset($users[spl_object_hash($connection)]);
                });

                $connection->on('error', function (\Exception $e) use (&$connections, $connection, $io) {
                    $io->error('Connection error: ' . $e->getMessage());
                    $key = array_search($connection, $connections);
                    if ($key !== false) {
                        unset($connections[$key]);
                    }
                });
            });
            
            $io->success('WebSocket server started successfully!');
            $io->note('Press Ctrl+C to stop the server');
            
            $loop->run();
            
        } catch (\Exception $e) {
            $io->error('Failed to start WebSocket server: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}