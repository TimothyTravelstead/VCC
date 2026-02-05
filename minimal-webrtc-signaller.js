// minimal-webrtc-signaller.js - ENHANCED WITH SIGNAL TARGET RESOLUTION FIX
const http = require('http');
const { Server } = require('socket.io');

const PORT = process.env.PORT || 3000;

const server = http.createServer((_, res) => {
  res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
  res.end('<h1>✅  Enhanced Signalling server is running</h1>');
});

const io = new Server(server, {
  cors: { origin: '*', methods: ['GET', 'POST'] },
  transports: ['websocket', 'polling']
});

// Track user mappings with enhanced validation
const socketToUser = new Map(); // socket.id -> volunteerID
const userToSocket = new Map(); // volunteerID -> socket.id
const roomUsers = new Map();    // room -> Set of volunteerIDs
const authenticatedUsers = new Map(); // volunteerID -> authentication data
const pendingAuth = new Map();  // socket.id -> pending auth data

// Simplified authentication
function isValidAuth(userId, authToken) {
  return userId?.length > 3 && authToken?.length > 10 && !authToken.includes('fake');
}

function recordAuth(socket, userId, authToken) {
  authenticatedUsers.set(userId, {
    socketId: socket.id,
    authToken,
    lastSeen: Date.now()
  });
}

io.on('connection', socket => {
  console.log(ts(), '🔌', socket.id, 'connected from', socket.handshake.address);

  // Simplified join-room with essential validation
  socket.on('join-room', ({ room, userId, authToken }) => {
    if (!room || !userId) {
      socket.emit('error', { message: 'Room and userId required' });
      return;
    }
    
    if (!isValidAuth(userId, authToken)) {
      console.warn(ts(), '🚨', socket.id, 'Auth failed for', userId);
      socket.emit('auth-failed', { reason: 'Invalid credentials' });
      return;
    }
    
    // Handle existing session
    const existingSocket = userToSocket.get(userId);
    if (existingSocket && existingSocket !== socket.id && io.sockets.sockets.has(existingSocket)) {
      io.sockets.sockets.get(existingSocket).emit('session-replaced', { reason: 'New session' });
      io.sockets.sockets.get(existingSocket).disconnect(true);
      socketToUser.delete(existingSocket);
    }
    
    // Record user
    recordAuth(socket, userId, authToken);
    socketToUser.set(socket.id, userId);
    userToSocket.set(userId, socket.id);
    
    if (!roomUsers.has(room)) roomUsers.set(room, new Set());
    roomUsers.get(room).add(userId);
    
    socket.join(room);
    console.log(ts(), '🟢', socket.id, `(${userId})`, 'joined', room);
    
    socket.to(room).emit('peer-joined', { id: socket.id, userId, timestamp: Date.now() });
    socket.emit('join-confirmed', { room, userId, timestamp: Date.now() });
  });

  socket.on('signal', ({ room, to, type, payload }) => {
    if (!room || !type) return;
    
    const fromUserId = socketToUser.get(socket.id);
    if (!fromUserId || !authenticatedUsers.has(fromUserId)) {
      socket.emit('auth-required', { message: 'Authentication required' });
      return;
    }
    
    // Update last seen
    authenticatedUsers.get(fromUserId).lastSeen = Date.now();
    
    const msg = { from: socket.id, fromUserId, type, payload, timestamp: Date.now() };
    console.log(ts(), '📡', socket.id, `(${fromUserId})`, 'signal', type, 'to', to || 'room');
    
    if (to) {
      // ENHANCED TARGET RESOLUTION FIX
      let targetSocketId = null;
      
      // PRIORITY 1: Try as volunteerID (this is what screenSharingControl sends)
      if (userToSocket.has(to)) {
        targetSocketId = userToSocket.get(to);
        console.log(ts(), '🎯', 'Resolved', to, 'as volunteerID →', targetSocketId);
      } 
      // PRIORITY 2: Try as socket.id (backward compatibility)
      else if (io.sockets.sockets.has(to)) {
        targetSocketId = to;
        console.log(ts(), '🎯', 'Using', to, 'as socket.id directly');
      }
      
      // ENHANCED: Verify target socket still exists and deliver message
      if (targetSocketId && io.sockets.sockets.has(targetSocketId)) {
        try {
          io.to(targetSocketId).emit('signal', msg);
          console.log(ts(), '✅', 'Signal', type, 'delivered to', to, '→', targetSocketId);
        } catch (error) {
          console.error(ts(), '💥', 'Error delivering signal to', targetSocketId, ':', error);
          socket.emit('signal-failed', { 
            originalTarget: to, 
            type: type,
            reason: 'Delivery error: ' + error.message,
            timestamp: Date.now()
          });
        }
      } else {
        // ENHANCED: Better error reporting with diagnostics
        console.warn(ts(), '❌', 'Signal target not found:', to);
        console.warn(ts(), '📋', 'Available users:', Array.from(userToSocket.keys()));
        console.warn(ts(), '📋', 'Available sockets:', Array.from(io.sockets.sockets.keys()));
        
        // ADDED: Notify sender of delivery failure for retry logic
        socket.emit('signal-failed', { 
          originalTarget: to, 
          type: type,
          reason: 'Target not found',
          availableUsers: Array.from(userToSocket.keys()),
          availableSockets: Array.from(io.sockets.sockets.keys()),
          timestamp: Date.now()
        });
      }
    } else {
      // Broadcast to room
      socket.to(room).emit('signal', msg);
      console.log(ts(), '📢', 'Signal', type, 'broadcasted to room', room);
    }
  });

  socket.on('disconnecting', () => {
    const userId = socketToUser.get(socket.id);
    console.log(ts(), '… disconnecting', socket.id, `(${userId || 'no-userId'})`,
                'rooms:', [...socket.rooms].join(','));
    
    for (const room of socket.rooms) {
      if (room !== socket.id) {
        // Enhanced peer-left notification
        const peerLeftData = { 
          id: socket.id,
          userId: userId || null,
          timestamp: Date.now(),
          reason: 'disconnect'
        };
        
        socket.to(room).emit('peer-left', peerLeftData);
        console.log(ts(), '📢', 'peer-left broadcasted to room', room, ':', peerLeftData);
        
        // Clean up room membership
        if (userId && roomUsers.has(room)) {
          roomUsers.get(room).delete(userId);
          // Clean up empty room sets
          if (roomUsers.get(room).size === 0) {
            roomUsers.delete(room);
          }
        }
      }
    }
  });

  socket.on('disconnect', reason => {
    const userId = socketToUser.get(socket.id);
    console.log(ts(), '❌', socket.id, `(${userId || 'unknown'})`, 'disconnected:', reason);
    
    if (userId && userToSocket.get(userId) === socket.id) {
      userToSocket.delete(userId);
      authenticatedUsers.delete(userId);
    }
    socketToUser.delete(socket.id);
  });

  // Handle errors
  socket.on('error', (error) => {
    const userId = socketToUser.get(socket.id);
    console.error(ts(), '💥', socket.id, `(${userId || 'no-userId'})`, 'socket error:', error);
  });

  // ADDED: Debug endpoint to check current mappings
  socket.on('debug-mappings', () => {
    const userId = socketToUser.get(socket.id);
    const debugInfo = {
      socketToUser: Object.fromEntries(socketToUser),
      userToSocket: Object.fromEntries(userToSocket),
      roomUsers: Object.fromEntries(Array.from(roomUsers.entries()).map(([k, v]) => [k, Array.from(v)])),
      connectedSockets: Array.from(io.sockets.sockets.keys()),
      requestingSocket: {
        id: socket.id,
        userId: userId,
        rooms: Array.from(socket.rooms)
      }
    };
    
    socket.emit('debug-mappings-response', debugInfo);
    console.log(ts(), '🔍', socket.id, `(${userId || 'no-userId'})`, 'debug info sent');
  });
});

// Periodic cleanup of stale mappings with enhanced logging
setInterval(() => {
  const connectedSockets = new Set(io.sockets.sockets.keys());
  let cleanedUp = 0;
  const staleMappings = [];
  
  // Clean up stale socket->user mappings
  for (const [socketId, userId] of socketToUser.entries()) {
    if (!connectedSockets.has(socketId)) {
      socketToUser.delete(socketId);
      if (userToSocket.get(userId) === socketId) {
        userToSocket.delete(userId);
      }
      staleMappings.push({ socketId, userId });
      cleanedUp++;
    }
  }
  
  if (cleanedUp > 0) {
    console.log(ts(), '🧹', 'Cleaned up', cleanedUp, 'stale mappings:', staleMappings);
  }
  
  // Log current status every 5 minutes
  const now = Date.now();
  if (!this.lastStatusLog || now - this.lastStatusLog > 300000) {
    this.lastStatusLog = now;
    console.log(ts(), '📊', 'Status: Connected sockets:', connectedSockets.size, 
                'User mappings:', userToSocket.size, 'Active rooms:', roomUsers.size);
  }
}, 60000); // Every minute

// ADDED: Graceful shutdown handling
process.on('SIGINT', () => {
  console.log(ts(), '🛑', 'Graceful shutdown initiated...');
  
  // Notify all connected clients
  io.emit('server-shutdown', { 
    message: 'Server is shutting down', 
    timestamp: Date.now() 
  });
  
  // Close server
  server.close(() => {
    console.log(ts(), '✅', 'Server closed gracefully');
    process.exit(0);
  });
  
  // Force exit after 5 seconds
  setTimeout(() => {
    console.log(ts(), '⚡', 'Force exit');
    process.exit(1);
  }, 5000);
});

server.listen(PORT, () =>
  console.log(ts(), `🚀  Enhanced signalling server with target resolution fix running on http://localhost:${PORT}`));

function ts() { return new Date().toISOString().substr(11, 12); }