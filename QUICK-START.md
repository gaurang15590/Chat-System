# 🚀 Quick Reference - Chat System

## Start Commands
```bash
# Terminal 1 - Web Server
php -S localhost:8000 -t public

# Terminal 2 - WebSocket Server  
php realtime-chat-server.php
```

## 🌐 URLs Quick Access

### Main Chat Interfaces
| URL | Description | Best For |
|-----|-------------|----------|
| `http://localhost:8000/realtime-chat.html` | Simple Real-time Chat | **Beginners & Testing** |
| `http://localhost:8000/room/general` | Room-based Chat | **Professional Use** |
| `http://localhost:8000/multi-server-test.html` | Testing Interface | **Development** |

### Direct Room Access  
| URL | Room |
|-----|------|
| `http://localhost:8000/room/general` | General Chat |
| `http://localhost:8000/room/tech` | Tech Discussion |
| `http://localhost:8000/room/random` | Random Chat |

## 🎯 Recommended Flow
1. **Start both servers** (2 terminals)
2. **Open**: `http://localhost:8000/realtime-chat.html`
3. **Enter username** and join chat
4. **Open another browser window** with different username
5. **Test real-time messaging** between windows

## 🔧 Quick Troubleshooting
- **Connection failed?** → Check WebSocket server running
- **Page not found?** → Check web server running  
- **No messages?** → Press F12, check Console for errors

---
**🎉 Main URL**: `http://localhost:8000/realtime-chat.html`