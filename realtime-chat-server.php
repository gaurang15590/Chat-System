<?php

require_once 'vendor/autoload.php';

use React\EventLoop\Loop;
use React\Socket\SocketServer;
use React\Socket\ConnectionInterface;

// WebSocket frame creation function
function createWebSocketFrame($payload) {
    $payloadLength = strlen($payload);
    
    if ($payloadLength < 126) {
        $frame = chr(0x81) . chr($payloadLength) . $payload;
    } elseif ($payloadLength < 65536) {
        $frame = chr(0x81) . chr(126) . pack('n', $payloadLength) . $payload;
    } else {
        $frame = chr(0x81) . chr(127) . pack('J', $payloadLength) . $payload;
    }
    
    return $frame;
}

// WebSocket frame decoding function
function decodeWebSocketFrame($data) {
    if (strlen($data) < 2) return false;
    
    $firstByte = ord($data[0]);
    $secondByte = ord($data[1]);
    
    $opcode = $firstByte & 0x0F;
    $masked = $secondByte & 0x80;
    $length = $secondByte & 0x7F;
    
    $offset = 2;
    
    if ($length == 126) {
        if (strlen($data) < 4) return false;
        $length = unpack('n', substr($data, 2, 2))[1];
        $offset = 4;
    } elseif ($length == 127) {
        if (strlen($data) < 10) return false;
        $length = unpack('J', substr($data, 2, 8))[1];
        $offset = 10;
    }
    
    if ($masked) {
        if (strlen($data) < $offset + 4) return false;
        $mask = substr($data, $offset, 4);
        $offset += 4;
    }
    
    if (strlen($data) < $offset + $length) return false;
    
    $payload = substr($data, $offset, $length);
    
    if ($masked) {
        for ($i = 0; $i < $length; $i++) {
            $payload[$i] = $payload[$i] ^ $mask[$i % 4];
        }
    }
    
    return $payload;
}

// Store connected clients
$clients = [];
$userCounter = 0;

$loop = Loop::get();
$socket = new SocketServer('127.0.0.1:8080', [], $loop);

echo "Real-Time Chat Server listening on 127.0.0.1:8080\n";

// Broadcast message to all connected clients except sender
function broadcastMessage($message, $excludeClient = null) {
    global $clients;
    
    $frame = createWebSocketFrame($message);
    
    foreach ($clients as $client) {
        if ($client['connection'] !== $excludeClient && $client['connection']->isWritable()) {
            try {
                $client['connection']->write($frame);
            } catch (Exception $e) {
                echo "Error broadcasting to client: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Send message to specific client
function sendToClient($connection, $message) {
    if ($connection->isWritable()) {
        try {
            $frame = createWebSocketFrame($message);
            $connection->write($frame);
        } catch (Exception $e) {
            echo "Error sending to client: " . $e->getMessage() . "\n";
        }
    }
}

// Get client info by connection
function getClient($connection) {
    global $clients;
    foreach ($clients as $client) {
        if ($client['connection'] === $connection) {
            return $client;
        }
    }
    return null;
}

// Remove client
function removeClient($connection) {
    global $clients;
    foreach ($clients as $key => $client) {
        if ($client['connection'] === $connection) {
            $username = $client['username'] ?? 'Unknown User';
            unset($clients[$key]);
            
            // Notify other users
            $userLeftMessage = json_encode([
                'type' => 'user_left',
                'username' => $username,
                'user_count' => count($clients),
                'timestamp' => date('c')
            ]);
            
            broadcastMessage($userLeftMessage);
            echo "User left: $username (Total users: " . count($clients) . ")\n";
            break;
        }
    }
}

$socket->on('connection', function (ConnectionInterface $connection) use (&$clients, &$userCounter) {
    echo "New connection from {$connection->getRemoteAddress()}\n";
    
    $connection->once('data', function ($data) use ($connection, &$clients, &$userCounter) {
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
                
                // Send welcome message
                $welcomeMessage = json_encode([
                    'type' => 'welcome', 
                    'message' => 'Connected to Real-Time Chat Server',
                    'timestamp' => date('c')
                ]);
                sendToClient($connection, $welcomeMessage);
                
                // Handle subsequent WebSocket frames
                $connection->on('data', function ($data) use ($connection, &$clients, &$userCounter) {
                    $decoded = decodeWebSocketFrame($data);
                    if ($decoded) {
                        echo "Received: " . $decoded . "\n";
                        
                        try {
                            $message = json_decode($decoded, true);
                            if ($message) {
                                handleMessage($connection, $message, $clients, $userCounter);
                            }
                        } catch (Exception $e) {
                            echo "Error parsing message: " . $e->getMessage() . "\n";
                        }
                    }
                });
            }
        }
    });
    
    $connection->on('close', function () use ($connection) {
        echo "Connection closed\n";
        removeClient($connection);
    });
    
    $connection->on('error', function ($error) use ($connection) {
        echo "Connection error: " . $error->getMessage() . "\n";
        removeClient($connection);
    });
});

function handleMessage($connection, $message, &$clients, &$userCounter) {
    $type = $message['type'] ?? '';
    
    switch ($type) {
        case 'auth':
            echo "Auth message received: " . json_encode($message) . "\n";
            $userId = $message['user_id'] ?? $message['userId'] ?? ++$userCounter;
            $username = $message['username'] ?? 'User' . $userId;
            echo "Extracted userId: $userId, username: $username\n";
            
            // Store client info
            $clients[] = [
                'connection' => $connection,
                'user_id' => $userId,
                'username' => $username,
                'joined_at' => time()
            ];
            
            echo "User authenticated: $username (ID: $userId)\n";
            
            // Send auth success to user
            $authResponse = json_encode([
                'type' => 'auth_success',
                'message' => 'Authentication successful',
                'user_id' => $userId,
                'username' => $username,
                'timestamp' => date('c')
            ]);
            sendToClient($connection, $authResponse);
            
            // Notify other users about new user
            $userJoinedMessage = json_encode([
                'type' => 'user_joined',
                'username' => $username,
                'user_count' => count($clients),
                'timestamp' => date('c')
            ]);
            broadcastMessage($userJoinedMessage, $connection);
            
            echo "Total connected users: " . count($clients) . "\n";
            break;
            
        case 'message':
            $client = getClient($connection);
            if ($client) {
                $chatMessage = [
                    'type' => 'message',
                    'message' => $message['message'] ?? '',
                    'username' => $client['username'],
                    'user_id' => $client['user_id'],
                    'timestamp' => date('c')
                ];
                
                echo "Broadcasting message from {$client['username']}: {$message['message']}\n";
                
                // Send confirmation back to sender
                $senderMessage = [
                    'type' => 'message_sent',
                    'message' => $message['message'] ?? '',
                    'username' => $client['username'],
                    'user_id' => $client['user_id'],
                    'timestamp' => date('c')
                ];
                sendToClient($connection, json_encode($senderMessage));
                
                // Broadcast to other clients (exclude sender)
                $messageJson = json_encode($chatMessage);
                broadcastMessage($messageJson, $connection);
            }
            break;
            
        case 'typing':
            $client = getClient($connection);
            if ($client) {
                $typingMessage = json_encode([
                    'type' => 'typing',
                    'username' => $client['username'],
                    'timestamp' => date('c')
                ]);
                broadcastMessage($typingMessage, $connection);
            }
            break;
            
        case 'chat_message':
            $client = getClient($connection);
            if ($client) {
                $messageContent = $message['content'] ?? '';
                $roomId = $message['roomId'] ?? 'general';
                
                // Create standardized message format for room-based chat
                $chatMessage = [
                    'type' => 'chat_message',
                    'content' => $messageContent,
                    'senderId' => $client['user_id'],
                    'senderName' => $client['username'],
                    'roomId' => $roomId,
                    'timestamp' => date('c')
                ];
                
                echo "Broadcasting room message from {$client['username']} in room $roomId: $messageContent\n";
                
                // Send confirmation back to sender
                $senderMessage = [
                    'type' => 'message_sent',
                    'content' => $messageContent,
                    'senderId' => $client['user_id'],
                    'senderName' => $client['username'],
                    'roomId' => $roomId,
                    'timestamp' => date('c')
                ];
                sendToClient($connection, json_encode($senderMessage));
                
                // Broadcast to other clients (exclude sender)
                $messageJson = json_encode($chatMessage);
                broadcastMessage($messageJson, $connection);
            }
            break;
            
        case 'ping':
            $pongMessage = json_encode([
                'type' => 'pong',
                'timestamp' => date('c')
            ]);
            sendToClient($connection, $pongMessage);
            break;
            
        default:
            echo "Unknown message type: $type\n";
    }
}

$socket->on('error', function ($error) {
    echo "Socket error: " . $error->getMessage() . "\n";
});

echo "Starting Real-Time Chat Server...\n";
echo "Users can connect and chat in real-time!\n";
echo "Press Ctrl+C to stop the server.\n\n";

$loop->run();