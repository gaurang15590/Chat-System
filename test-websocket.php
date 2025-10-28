<?php

require_once 'vendor/autoload.php';

use React\EventLoop\Loop;
use React\Socket\SocketServer;

$loop = Loop::get();

echo "Starting simple WebSocket test server on 127.0.0.1:8080\n";

try {
    $socket = new SocketServer('127.0.0.1:8080', [], $loop);
    
    $socket->on('connection', function ($connection) {
        echo "New connection established\n";
        
        $connection->on('data', function ($data) use ($connection) {
            echo "Received data: " . $data . "\n";
            $connection->write("Echo: " . $data);
        });
        
        $connection->on('close', function () {
            echo "Connection closed\n";
        });
        
        $connection->on('error', function ($error) {
            echo "Connection error: " . $error->getMessage() . "\n";
        });
    });
    
    echo "Server started successfully! Listening on 127.0.0.1:8080\n";
    echo "Press Ctrl+C to stop\n";
    
    $loop->run();
    
} catch (Exception $e) {
    echo "Error starting server: " . $e->getMessage() . "\n";
}