class ChatClient {
    constructor() {
        this.socket = null;
        this.isConnected = false;
        this.currentRoom = window.INITIAL_ROOM || 'general';
        this.currentUser = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        
        this.initializeElements();
        this.bindEvents();
        this.showAuthModal();
    }
    
    initializeElements() {
        // DOM elements
        this.messagesContainer = document.getElementById('messages');
        this.messageInput = document.getElementById('message-input');
        this.sendBtn = document.getElementById('send-btn');
        this.usernameInput = document.getElementById('username-input');
        this.connectBtn = document.getElementById('connect-btn');
        this.connectionStatus = document.getElementById('connection-status');
        this.currentRoomElement = document.getElementById('current-room');
        this.onlineUsers = document.getElementById('online-users');
        this.userInfo = document.getElementById('user-info');
        this.messageForm = document.getElementById('message-form');
        this.authModal = document.getElementById('auth-modal');
        this.authForm = document.getElementById('auth-form');
        this.modalUsername = document.getElementById('modal-username');
        this.modalEmail = document.getElementById('modal-email');
        
        // Room elements
        this.roomItems = document.querySelectorAll('.room-item');
    }
    
    bindEvents() {
        // Auth form submission
        this.authForm.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleUserRegistration();
        });
        
        // Connect button
        this.connectBtn.addEventListener('click', () => {
            this.connectToChat();
        });
        
        // Send message
        this.sendBtn.addEventListener('click', () => {
            this.sendMessage();
        });
        
        // Enter key to send message
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.sendMessage();
            }
        });
        
        // Room switching
        this.roomItems.forEach(room => {
            room.addEventListener('click', () => {
                this.switchRoom(room.dataset.room);
            });
        });
        
        // Username input enter key
        this.usernameInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.connectToChat();
            }
        });
    }
    
    showAuthModal() {
        this.authModal.style.display = 'block';
    }
    
    hideAuthModal() {
        this.authModal.style.display = 'none';
    }
    
    async handleUserRegistration() {
        const username = this.modalUsername.value.trim();
        const email = this.modalEmail.value.trim();
        
        if (!username || !email) {
            alert('Please fill in all fields');
            return;
        }
        
        try {
            // Try to get existing user first
            let response = await fetch(`/api/users/username/${username}`);
            let userData;
            
            if (response.ok) {
                userData = await response.json();
            } else if (response.status === 404) {
                // Create new user
                response = await fetch('/api/users', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ username, email })
                });
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Failed to create user');
                }
                
                const result = await response.json();
                userData = result.user;
            } else {
                throw new Error('Failed to check user');
            }
            
            this.currentUser = userData;
            this.usernameInput.value = username;
            this.hideAuthModal();
            this.connectToChat();
            
        } catch (error) {
            console.error('Registration error:', error);
            alert('Error: ' + error.message);
        }
    }
    
    connectToChat() {
        if (!this.currentUser) {
            alert('Please complete registration first');
            this.showAuthModal();
            return;
        }
        
        if (this.isConnected) {
            this.disconnect();
            return;
        }
        
        this.updateConnectionStatus('connecting');
        this.connectBtn.disabled = true;
        
        try {
            this.socket = new WebSocket('ws://127.0.0.1:8080');
            
            this.socket.onopen = () => {
                this.onSocketOpen();
            };
            
            this.socket.onmessage = (event) => {
                this.onSocketMessage(event);
            };
            
            this.socket.onclose = () => {
                this.onSocketClose();
            };
            
            this.socket.onerror = (error) => {
                this.onSocketError(error);
            };
            
        } catch (error) {
            console.error('Failed to connect:', error);
            this.updateConnectionStatus('disconnected');
            this.connectBtn.disabled = false;
        }
    }
    
    onSocketOpen() {
        console.log('WebSocket connected');
        this.isConnected = true;
        this.reconnectAttempts = 0;
        this.updateConnectionStatus('connected');
        
        // Authenticate user
        this.sendSocketMessage({
            type: 'auth',
            user_id: this.currentUser.id,
            userId: this.currentUser.id,
            username: this.currentUser.username,
            roomId: this.currentRoom
        });
        
        // Show message form, hide user info
        this.userInfo.style.display = 'none';
        this.messageForm.style.display = 'flex';
        this.messageInput.focus();
        
        // Load recent messages
        this.loadRecentMessages();
    }
    
    onSocketMessage(event) {
        try {
            const data = JSON.parse(event.data);
            this.handleSocketMessage(data);
        } catch (error) {
            console.error('Failed to parse message:', error);
        }
    }
    
    handleSocketMessage(data) {
        switch (data.type) {
            case 'auth_success':
                console.log('Authentication successful');
                break;
                
            case 'chat_message':
                this.displayMessage(data);
                break;
                
            case 'message_sent':
                this.displayMessage(data);
                break;
                
            case 'user_joined':
                this.displaySystemMessage(`${data.username} joined the room`);
                this.updateOnlineUsers();
                break;
                
            case 'user_left':
                this.displaySystemMessage(`${data.username} left the room`);
                this.updateOnlineUsers();
                break;
                
            case 'user_status':
                this.updateUserStatus(data);
                break;
                
            case 'recent_messages':
                this.displayRecentMessages(data.messages);
                break;
                
            case 'room_joined':
                this.updateOnlineUsers(data.onlineUsers);
                break;
                
            case 'error':
                console.error('Server error:', data.message);
                alert('Error: ' + data.message);
                break;
                
            case 'pong':
                // Heartbeat response
                break;
                
            default:
                console.log('Unknown message type:', data);
        }
    }
    
    onSocketClose() {
        console.log('WebSocket disconnected');
        this.isConnected = false;
        this.updateConnectionStatus('disconnected');
        
        // Show user info, hide message form
        this.userInfo.style.display = 'flex';
        this.messageForm.style.display = 'none';
        this.connectBtn.disabled = false;
        
        // Attempt reconnection
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts})...`);
            setTimeout(() => {
                this.connectToChat();
            }, this.reconnectDelay * this.reconnectAttempts);
        }
    }
    
    onSocketError(error) {
        console.error('WebSocket error:', error);
        this.updateConnectionStatus('disconnected');
    }
    
    sendSocketMessage(message) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify(message));
        }
    }
    
    sendMessage() {
        const content = this.messageInput.value.trim();
        if (!content || !this.isConnected) return;
        
        this.sendSocketMessage({
            type: 'chat_message',
            content: content,
            userId: this.currentUser.id,
            roomId: this.currentRoom
        });
        
        this.messageInput.value = '';
        this.messageInput.focus();
    }
    
    displayMessage(messageData) {
        const messageElement = document.createElement('div');
        messageElement.className = 'message';
        
        if (messageData.senderId === this.currentUser.id) {
            messageElement.classList.add('own');
        }
        
        const time = new Date(messageData.timestamp).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        messageElement.innerHTML = `
            <div class="message-header">
                <span class="message-sender">${messageData.senderUsername}</span>
                <span class="message-time">${time}</span>
            </div>
            <div class="message-content">${this.escapeHtml(messageData.content)}</div>
        `;
        
        this.messagesContainer.appendChild(messageElement);
        this.scrollToBottom();
    }
    
    displaySystemMessage(message) {
        const messageElement = document.createElement('div');
        messageElement.className = 'system-message';
        messageElement.textContent = message;
        
        this.messagesContainer.appendChild(messageElement);
        this.scrollToBottom();
    }
    
    displayRecentMessages(messages) {
        this.messagesContainer.innerHTML = '';
        messages.forEach(message => {
            this.displayMessage(message);
        });
    }
    
    async loadRecentMessages() {
        try {
            const response = await fetch(`/api/messages/room/${this.currentRoom}/recent?limit=20`);
            if (response.ok) {
                const data = await response.json();
                this.displayRecentMessages(data.messages);
            }
        } catch (error) {
            console.error('Failed to load recent messages:', error);
        }
    }
    
    switchRoom(roomId) {
        if (roomId === this.currentRoom) return;
        
        this.currentRoom = roomId;
        this.currentRoomElement.textContent = `# ${roomId}`;
        
        // Update active room
        this.roomItems.forEach(room => {
            room.classList.toggle('active', room.dataset.room === roomId);
        });
        
        // Clear messages
        this.messagesContainer.innerHTML = '';
        
        // If connected, join new room
        if (this.isConnected) {
            this.sendSocketMessage({
                type: 'join_room',
                roomId: roomId,
                userId: this.currentUser.id
            });
            this.loadRecentMessages();
        }
        
        // Update URL
        history.pushState({}, '', `/room/${roomId}`);
    }
    
    updateConnectionStatus(status) {
        this.connectionStatus.className = status;
        this.connectionStatus.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        
        if (status === 'connected') {
            this.connectBtn.textContent = 'Disconnect';
            this.connectBtn.disabled = false;
        } else {
            this.connectBtn.textContent = 'Connect';
        }
    }
    
    updateOnlineUsers(users = []) {
        // This would be implemented with actual user data from the server
        // For now, we'll just update the count
        const count = users.length || Math.floor(Math.random() * 10) + 1;
        const countElement = document.getElementById(`${this.currentRoom}-count`);
        if (countElement) {
            countElement.textContent = count;
        }
    }
    
    updateUserStatus(statusData) {
        // Update online user list based on status changes
        console.log('User status updated:', statusData);
    }
    
    scrollToBottom() {
        const container = document.getElementById('messages-container');
        container.scrollTop = container.scrollHeight;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    disconnect() {
        if (this.socket) {
            this.socket.close();
        }
    }
    
    // Heartbeat to keep connection alive
    startHeartbeat() {
        setInterval(() => {
            if (this.isConnected) {
                this.sendSocketMessage({ type: 'ping' });
            }
        }, 30000); // 30 seconds
    }
}

// Initialize chat when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.chatClient = new ChatClient();
    window.chatClient.startHeartbeat();
});

// Handle page unload
window.addEventListener('beforeunload', () => {
    if (window.chatClient) {
        window.chatClient.disconnect();
    }
});