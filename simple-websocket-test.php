<?php

require_once 'vendor/autoload.php';

use React\EventLoop\Loop;
use React\Socket\SocketServer;
use React\Socket\ConnectionInterface;

// Helper function to create WebSocket frame
function createWebSocketFrame($data) {
    $length = strlen($data);
    $frame = chr(129); // FIN + text frame
    
    if ($length < 126) {
        $frame .= chr($length);
    } elseif ($length < 65536) {
        $frame .= chr(126) . pack('n', $length);
    } else {
        $frame .= chr(127) . pack('J', $length);
    }
    
    return $frame . $data;
}

// Helper function to decode WebSocket frame
function decodeWebSocketFrame($data) {
    if (strlen($data) < 2) return false;
    
    $firstByte = ord($data[0]);
    $secondByte = ord($data[1]);
    
    $masked = ($secondByte & 128) === 128;
    $length = $secondByte & 127;
    
    $offset = 2;
    
    if ($length === 126) {
        $length = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
    } elseif ($length === 127) {
        $length = unpack('J', substr($data, $offset, 8))[1];
        $offset += 8;
    }
    
    if ($masked) {
        $mask = substr($data, $offset, 4);
        $offset += 4;
        $payload = substr($data, $offset, $length);
        
        for ($i = 0; $i < $length; $i++) {
            $payload[$i] = $payload[$i] ^ $mask[$i % 4];
        }
    } else {
        $payload = substr($data, $offset, $length);
    }
    
    return $payload;
}

$loop = Loop::get();
$socket = new SocketServer('127.0.0.1:8080', [], $loop);

echo "Simple WebSocket server listening on 127.0.0.1:8080\n";

$socket->on('connection', function (ConnectionInterface $connection) {
    echo "New connection from {$connection->getRemoteAddress()}\n";
    
    $connection->once('data', function ($data) use ($connection) {
        // Check if this is a WebSocket handshake
        if (strpos($data, 'Upgrade: websocket') !== false) {
            echo "WebSocket handshake detected\n";
            
            // Extract the WebSocket key
            if (preg_match('/Sec-WebSocket-Key: (.+)/', $data, $matches)) {
                $key = trim($matches[1]);
                $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                
                // Send WebSocket handshake response
                $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                           "Upgrade: websocket\r\n" .
                           "Connection: Upgrade\r\n" .
                           "Sec-WebSocket-Accept: $acceptKey\r\n" .
                           "\r\n";
                
                $connection->write($response);
                echo "WebSocket handshake completed\n";
                
                // Send a welcome message
                $welcomeMessage = json_encode(['type' => 'welcome', 'message' => 'Connected to WebSocket server']);
                $frame = createWebSocketFrame($welcomeMessage);
                $connection->write($frame);
                
                // Handle subsequent WebSocket frames
                $connection->on('data', function ($data) use ($connection) {
                    echo "Received WebSocket data\n";
                    $decoded = decodeWebSocketFrame($data);
                    if ($decoded) {
                        echo "Decoded: " . $decoded . "\n";
                        
                        // Parse JSON message
                        $message = json_decode($decoded, true);
                        if ($message) {
                            $response = '';
                            
                            switch ($message['type']) {
                                case 'auth':
                                    $userId = $message['user_id'] ?? $message['userId'] ?? 1;
                                    $username = $message['username'] ?? 'User' . $userId;
                                    $response = json_encode([
                                        'type' => 'auth_success',
                                        'message' => 'Authentication successful',
                                        'user_id' => $userId,
                                        'username' => $username,
                                        'server' => 'server_1'
                                    ]);
                                    break;
                                case 'message':
                                    $response = json_encode([
                                        'type' => 'message',
                                        'message' => $message['message'],
                                        'username' => $message['username'] ?? 'Anonymous',
                                        'timestamp' => date('H:i:s'),
                                        'server' => 'server_1'
                                    ]);
                                    break;
                                case 'join_room':
                                    $response = json_encode([
                                        'type' => 'room_joined',
                                        'message' => 'Joined room successfully',
                                        'room_id' => $message['room_id'],
                                        'server' => 'server_1'
                                    ]);
                                    break;
                                default:
                                    $response = json_encode([
                                        'type' => 'echo', 
                                        'data' => $decoded, 
                                        'server' => 'server_1',
                                        'timestamp' => date('H:i:s')
                                    ]);
                            }
                            
                            $frame = createWebSocketFrame($response);
                            $connection->write($frame);
                        }
                    }
                });
            }
        }
    });
    
    $connection->on('close', function () {
        echo "Connection closed\n";
    });
});

echo "Starting server...\n";
$loop->run();