# 📖 Chat System - User Guide

## 🎯 Quick Start - How to Run the Project

### Step 1: Start the Servers (You need 2 terminals)

#### Terminal 1 - Start Web Server:
```bash
cd d:\xampp\htdocs\Chat-System
php -S localhost:8000 -t public
```

#### Terminal 2 - Start WebSocket Server:
```bash
cd d:\xampp\htdocs\Chat-System
php realtime-chat-server.php
```

### Step 2: Access the Application

Once both servers are running, you can access these URLs:

## 🌐 Available Chat Interfaces

### 1. 🎨 Simple Real-Time Chat (Recommended)
**URL**: `http://localhost:8000/realtime-chat.html`

**Features**:
- ✅ Clean, modern interface
- ✅ Instant messaging
- ✅ Easy to use
- ✅ Perfect for testing

**How to Use**:
1. Open the URL in your browser
2. Enter your username
3. Click "Join Chat"
4. Start chatting instantly!

---

### 2. 🏢 Professional Room-Based Chat
**URL**: `http://localhost:8000/room/general`

**Features**:
- ✅ Multiple chat rooms (#general, #random, #tech)
- ✅ User registration system
- ✅ Online users sidebar
- ✅ Slack-like professional interface

**How to Use**:
1. Open the URL in your browser
2. Fill in the registration form (username + email)
3. Click "Join Chat"
4. Switch between different rooms
5. See online users in the sidebar

---

### 3. 🔧 Multi-Server Testing Interface
**URL**: `http://localhost:8000/multi-server-test.html`

**Features**:
- ✅ Test dual connections
- ✅ Developer debugging tools
- ✅ Connection status monitoring

**How to Use**:
1. Open the URL in your browser
2. Connect to both servers
3. Test messaging between servers

---

## 💬 Testing Multi-User Chat

### Option 1: Multiple Browser Windows
1. Open `http://localhost:8000/realtime-chat.html` in multiple browser windows
2. Use different usernames in each window
3. Send messages and see them appear instantly in all windows

### Option 2: Multiple Browser Types
1. Open the chat in Chrome with username "Alice"
2. Open the chat in Firefox with username "Bob"
3. Chat between different browsers

### Option 3: Incognito/Private Windows
1. Open normal window with one username
2. Open incognito/private window with different username
3. Chat between normal and private sessions

---

## 🔄 Application Flow

```
1. User opens chat URL
   ↓
2. User enters username
   ↓
3. JavaScript connects to WebSocket (ws://localhost:8080)
   ↓
4. User sends message
   ↓
5. Message goes to PHP WebSocket server
   ↓
6. Server broadcasts to all connected users
   ↓
7. All users receive message instantly
```

---

## 🛠️ Troubleshooting

### Problem: "Connection failed"
**Solution**: Make sure WebSocket server is running:
```bash
php realtime-chat-server.php
```

### Problem: "Page not found"
**Solution**: Make sure web server is running:
```bash
php -S localhost:8000 -t public
```

### Problem: Messages not showing
**Solution**: 
1. Press F12 in browser
2. Check Console tab for errors
3. Refresh the page

---

## 🎉 Ready to Chat!

**Recommended for beginners**: Start with `http://localhost:8000/realtime-chat.html`

This interface is the easiest to use and perfect for testing the real-time chat functionality.

---

## 📱 Mobile Support

The chat interfaces work on:
- ✅ Desktop browsers (Chrome, Firefox, Safari, Edge)
- ✅ Mobile browsers (iOS Safari, Android Chrome)
- ✅ Tablet browsers

---

## 🚀 Performance Tips

- **For best performance**: Use Chrome or Firefox
- **Multiple users**: Open in different browser windows
- **Testing**: Use the simple real-time chat interface first
- **Development**: Use multi-server test for debugging

---

**🎯 Main URL to remember**: `http://localhost:8000/realtime-chat.html`