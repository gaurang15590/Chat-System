# Real-time Chat System with Multi-WebSocket Fleet and Pub/Sub Messaging

A high-performance real-time chat system built with Symfony (PHP) that supports multiple WebSocket servers with RabbitMQ pub/sub messaging for seamless message synchronization across the entire fleet.

## 🚀 Features

### Core Features
- **Real-time messaging** with WebSocket connections
- **Multi-server scalability** using RabbitMQ pub/sub
- **Multiple chat rooms** support
- **User presence** tracking (online/offline status)
- **Message persistence** with MySQL database
- **REST API** for chat operations
- **Responsive web interface**

### Technical Features
- **WebSocket Fleet Management**: Multiple WebSocket servers can run simultaneously
- **Message Broadcasting**: RabbitMQ ensures messages reach all connected users across all servers
- **Connection Management**: Robust connection handling with automatic reconnection
- **Database Integration**: Persistent message storage with Doctrine ORM
- **Real-time User Status**: Live online/offline user tracking
- **Scalable Architecture**: Designed for horizontal scaling

## 🏗️ Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   WebSocket     │    │   WebSocket     │    │   WebSocket     │
│   Server 1      │    │   Server 2      │    │   Server N      │
│   (Port 8080)   │    │   (Port 8081)   │    │   (Port 808N)   │
└─────────┬───────┘    └─────────┬───────┘    └─────────┬───────┘
          │                      │                      │
          └──────────────────────┼──────────────────────┘
                                 │
                    ┌─────────────────┐
                    │   RabbitMQ      │
                    │   Message       │
                    │   Broker        │
                    └─────────┬───────┘
                              │
              ┌───────────────────────────────┐
              │        Symfony App            │
              │  ┌─────────────────────────┐  │
              │  │     REST API            │  │
              │  │   (Users/Messages)      │  │
              │  └─────────────────────────┘  │
              │  ┌─────────────────────────┐  │
              │  │   Database Layer        │  │
              │  │   (MySQL + Doctrine)    │  │
              │  └─────────────────────────┘  │
              └───────────────────────────────┘
```

## 📋 Prerequisites

- PHP 8.1 or higher
- Composer
- MySQL 8.0+
- RabbitMQ Server
- XAMPP (for local development)

## 🛠️ Installation & Setup

### 1. Clone and Setup Project

```bash
# Navigate to your XAMPP htdocs directory
cd d:\xampp\htdocs\Chat-System

# Install PHP dependencies
composer install

# Copy environment file
copy .env .env.local
```

### 2. Database Configuration

1. **Start XAMPP services** (Apache + MySQL)

2. **Create database**:
```sql
CREATE DATABASE chat_system;
```

3. **Update `.env.local`** with your database credentials:
```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/chat_system?serverVersion=8.0.32&charset=utf8mb4"
```

4. **Run migrations**:
```bash
php bin/console doctrine:migrations:migrate
```

### 3. RabbitMQ Setup

#### Option A: Install RabbitMQ locally
1. Download and install RabbitMQ from https://www.rabbitmq.com/download.html
2. Start RabbitMQ service
3. Access management console: http://localhost:15672 (guest/guest)

#### Option B: Use Docker
```bash
docker run -d --name rabbitmq -p 5672:5672 -p 15672:15672 rabbitmq:3-management
```

### 4. Environment Configuration

Update your `.env.local` file:
```env
# Symfony
APP_ENV=dev
APP_SECRET=your-secret-key-here

# Database
DATABASE_URL="mysql://root:@127.0.0.1:3306/chat_system?serverVersion=8.0.32&charset=utf8mb4"

# WebSocket Configuration
WEBSOCKET_HOST=127.0.0.1
WEBSOCKET_PORT=8080

# RabbitMQ Configuration
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/

# Messenger
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/messages
```

## 🚀 Running the Application

### 1. Start the Symfony Web Server
```bash
# Option A: Using PHP built-in server
php -S localhost:8000 -t public/

# Option B: Using Symfony CLI (if installed)
symfony server:start
```

### 2. Start WebSocket Server
```bash
php bin/console websocket:server
```

**For multiple WebSocket servers (scaling)**:
```bash
# Terminal 1 - Server on port 8080
php bin/console websocket:server --port 8080

# Terminal 2 - Server on port 8081  
php bin/console websocket:server --port 8081

# Terminal 3 - Server on port 8082
php bin/console websocket:server --port 8082
```

### 3. Access the Application
- **Main Application**: http://localhost:8000
- **Specific Room**: http://localhost:8000/room/general
- **RabbitMQ Management**: http://localhost:15672 (guest/guest)

## 📡 API Endpoints

### User Management
```http
POST /api/users
GET /api/users
GET /api/users/{id}
GET /api/users/username/{username}
PATCH /api/users/{id}/status
```

### Message Management
```http
POST /api/messages
GET /api/messages/room/{roomId}
GET /api/messages/room/{roomId}/recent
GET /api/messages/{id}
DELETE /api/messages/{id}
GET /api/messages/stats/room/{roomId}
```

### Example API Usage

#### Create a new user:
```bash
curl -X POST http://localhost:8000/api/users \
  -H "Content-Type: application/json" \
  -d '{"username": "john_doe", "email": "john@example.com"}'
```

#### Send a message:
```bash
curl -X POST http://localhost:8000/api/messages \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Hello, World!",
    "sender_id": 1,
    "room_id": "general"
  }'
```

#### Get recent messages:
```bash
curl http://localhost:8000/api/messages/room/general/recent?limit=20
```

## 🔌 WebSocket Protocol

### Client Connection
```javascript
const socket = new WebSocket('ws://127.0.0.1:8080');
```

### Message Types

#### Authentication
```json
{
  "type": "auth",
  "userId": 1,
  "roomId": "general"
}
```

#### Send Message
```json
{
  "type": "chat_message",
  "content": "Hello everyone!",
  "userId": 1,
  "roomId": "general"
}
```

#### Join Room
```json
{
  "type": "join_room",
  "roomId": "tech",
  "userId": 1
}
```

#### Get Recent Messages
```json
{
  "type": "get_recent_messages",
  "roomId": "general",
  "limit": 20
}
```

## 🎯 Usage Guide

### Basic Chat Flow

1. **Open the application** in your browser
2. **Register/Login** by entering username and email
3. **Connect** to the WebSocket server
4. **Join a room** by clicking on room names in the sidebar
5. **Send messages** using the input field
6. **View real-time messages** from other users

### Multi-Server Testing

1. **Start multiple WebSocket servers** on different ports
2. **Connect different browser tabs** to different servers
3. **Send messages from any client** - they should appear on all connected clients regardless of which server they're connected to

## 🔧 Development

### Project Structure
```
Chat-System/
├── src/
│   ├── Controller/          # REST API controllers
│   ├── Entity/              # Doctrine entities
│   ├── Repository/          # Database repositories
│   ├── Service/             # Business logic services
│   ├── WebSocket/           # WebSocket server implementation
│   ├── Message/             # Message classes for Symfony Messenger
│   ├── MessageHandler/      # Message handlers
│   └── Command/             # Console commands
├── config/                  # Configuration files
├── templates/               # Twig templates
├── public/                  # Web assets (CSS, JS)
├── migrations/              # Database migrations
└── bin/                     # Console scripts
```

### Key Components

- **WebSocketServer**: Manages WebSocket connections and message handling
- **ConnectionManager**: Tracks user connections and room memberships
- **RabbitMQService**: Handles pub/sub messaging between servers
- **ChatMessage**: Message class for Symfony Messenger integration
- **User/Message Entities**: Database models with Doctrine ORM

### Adding New Features

#### Adding a New Message Type
1. Update WebSocket message handling in `WebSocketServer.php`
2. Add new message type handling in frontend `chat.js`
3. Update API controllers if needed

#### Adding New Chat Rooms
1. Rooms are created dynamically - no configuration needed
2. Users can join any room by navigating to `/room/{roomId}`

## 🐛 Troubleshooting

### Common Issues

#### WebSocket Connection Failed
- **Check if WebSocket server is running**: `php bin/console websocket:server`
- **Verify port availability**: Default port 8080 should be free
- **Check firewall settings**: Ensure port 8080 is not blocked

#### RabbitMQ Connection Error
- **Verify RabbitMQ is running**: Check service status or Docker container
- **Check credentials**: Ensure username/password in `.env.local` are correct
- **Test connection**: Access http://localhost:15672

#### Database Connection Issues
- **Start MySQL service** in XAMPP
- **Verify database exists**: `chat_system` database should be created
- **Check credentials**: Ensure database URL in `.env.local` is correct
- **Run migrations**: `php bin/console doctrine:migrations:migrate`

#### Messages Not Syncing Across Servers
- **Check RabbitMQ logs**: Look for connection errors
- **Verify exchange/queue setup**: Check RabbitMQ management console
- **Ensure all servers use same RabbitMQ instance**

### Logs and Debugging

#### Enable Symfony Debug Mode
```env
# In .env.local
APP_ENV=dev
```

#### View Logs
```bash
# Symfony logs
tail -f var/log/dev.log

# WebSocket server logs (console output)
php bin/console websocket:server --verbose
```

## 🚀 Deployment

### Production Considerations

1. **Use a proper web server** (Nginx/Apache)
2. **Run WebSocket servers as system services**
3. **Use Redis/RabbitMQ clusters** for high availability
4. **Implement proper logging and monitoring**
5. **Use SSL/TLS** for secure connections
6. **Configure load balancer** for WebSocket servers

### Sample Production Setup

#### Nginx Configuration
```nginx
upstream websocket_backend {
    server 127.0.0.1:8080;
    server 127.0.0.1:8081;
    server 127.0.0.1:8082;
}

server {
    location /ws {
        proxy_pass http://websocket_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

#### Systemd Service for WebSocket Server
```ini
[Unit]
Description=Chat WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/chat-system
ExecStart=/usr/bin/php bin/console websocket:server --port=8080
Restart=always

[Install]
WantedBy=multi-user.target
```

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📞 Support

For issues and questions:
- **Create an issue** on the GitHub repository
- **Check the troubleshooting section** above
- **Review the logs** for error details

---

**Happy Chatting! 💬**