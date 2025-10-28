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
$socket = new SocketServer('127.0.0.1:8082', [], $loop);

echo "WebSocket Server 2 listening on 127.0.0.1:8082\n";

$socket->on('connection', function (ConnectionInterface $connection) {
    echo "New connection to Server 2 from {$connection->getRemoteAddress()}\n";
    
    $connection->once('data', function ($data) use ($connection) {
        // Check if this is a WebSocket handshake
        if (strpos($data, 'Upgrade: websocket') !== false) {
            echo "WebSocket handshake detected on Server 2\n";
            
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
                echo "WebSocket handshake completed on Server 2\n";
                
                // Send a welcome message
                $welcomeMessage = json_encode(['type' => 'welcome', 'message' => 'Connected to WebSocket Server 2 (Port 8082)', 'server_id' => 'server_2']);
                $frame = createWebSocketFrame($welcomeMessage);
                $connection->write($frame);
                
                // Handle subsequent WebSocket frames
                $connection->on('data', function ($data) use ($connection) {
                    echo "Received WebSocket data on Server 2\n";
                    $decoded = decodeWebSocketFrame($data);
                    if ($decoded) {
                        echo "Server 2 Decoded: " . $decoded . "\n";
                        // Echo the message back with server info
                        $response = json_encode(['type' => 'echo', 'data' => $decoded, 'server' => 'server_2']);
                        $frame = createWebSocketFrame($response);
                        $connection->write($frame);
                    }
                });
            }
        }
    });
    
    $connection->on('close', function () {
        echo "Connection to Server 2 closed\n";
    });
});

echo "Starting WebSocket Server 2...\n";
$loop->run();