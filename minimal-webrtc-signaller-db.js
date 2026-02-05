// minimal-webrtc-signaller-db.js - ENHANCED WITH DATABASE AUTHENTICATION
const http = require('http');
const { Server } = require('socket.io');

// Timestamp utility function
function ts() { return new Date().toISOString().substr(11, 12); }

// Check for volunteer-auth.js dependency
let VolunteerAuth;
try {
  VolunteerAuth = require('./volunteer-auth.js');
} catch (error) {
  console.error(ts(), '💥', 'Failed to load volunteer-auth.js:', error.message);
  console.error(ts(), '⚠️', 'Running without database authentication - using simple validation');
  VolunteerAuth = null;
}

const PORT = process.env.PORT || 3000;

const server = http.createServer((_, res) => {
  res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
  res.end('<h1>✅  Database-authenticated signalling server is running</h1>');
});

const io = new Server(server, {
  cors: { origin: '*', methods: ['GET', 'POST'] },
  transports: ['websocket', 'polling']
});

// Initialize database authentication
const volunteerAuth = VolunteerAuth ? new VolunteerAuth() : null;

// Simple fallback authentication when database auth is not available
function simpleAuth(userId, authToken) {
  return userId?.length > 3 && authToken?.length > 10 && !authToken.includes('fake');
}

// Track user mappings with database validation
const socketToUser = new Map(); // socket.id -> volunteerID
const userToSocket = new Map(); // volunteerID -> socket.id
const roomUsers = new Map();    // room -> Set of volunteerIDs
const authenticatedUsers = new Map(); // volunteerID -> { socketId, lastSeen, userInfo }

io.on('connection', socket => {
  console.log(ts(), '🔌', socket.id, 'connected from', socket.handshake.address);

  // Database-authenticated join-room
  socket.on('join-room', async ({ room, userId, authToken }) => {
    console.log(ts(), '🔑', socket.id, 'attempting to join room:', room, 'as user:', userId);
    
    if (!room || !userId) {
      socket.emit('error', { message: 'Room and userId required' });
      return;
    }
    
    try {
      let isLoggedOn = false;
      let userInfo = null;
      
      // Check database authentication if available, otherwise use simple auth
      if (volunteerAuth) {
        isLoggedOn = await volunteerAuth.isVolunteerLoggedOn(userId);
        if (isLoggedOn) {
          userInfo = await volunteerAuth.getVolunteerInfo(userId);
        }
      } else {
        // Fallback to simple authentication
        isLoggedOn = simpleAuth(userId, authToken);
        userInfo = { UserName: userId, UserID: userId };
        console.log(ts(), '⚠️', 'Using simple auth for', userId, '- database auth not available');
      }
      
      if (!isLoggedOn) {
        console.warn(ts(), '🚨', socket.id, 'Auth failed for', userId, '- not logged in');
        socket.emit('auth-failed', { 
          reason: volunteerAuth ? 'User not logged in (database check failed)' : 'Invalid credentials (simple auth)',
          userId: userId
        });
        return;
      }
      
      console.log(ts(), '✅', socket.id, 'Auth successful for', userId, userInfo?.UserName || '');
      
      // Handle existing session
      const existingSocket = userToSocket.get(userId);
      if (existingSocket && existingSocket !== socket.id && io.sockets.sockets.has(existingSocket)) {
        console.log(ts(), '🔄', 'Replacing existing session for', userId);
        io.sockets.sockets.get(existingSocket).emit('session-replaced', { reason: 'New session' });
        io.sockets.sockets.get(existingSocket).disconnect(true);
        socketToUser.delete(existingSocket);
      }
      
      // Record authenticated user
      socketToUser.set(socket.id, userId);
      userToSocket.set(userId, socket.id);
      authenticatedUsers.set(userId, {
        socketId: socket.id,
        lastSeen: Date.now(),
        userInfo: userInfo
      });
      
      if (!roomUsers.has(room)) roomUsers.set(room, new Set());
      roomUsers.get(room).add(userId);
      
      socket.join(room);
      console.log(ts(), '🟢', socket.id, `(${userId})`, 'joined', room);
      
      socket.to(room).emit('peer-joined', { 
        id: socket.id, 
        userId, 
        userName: userInfo?.UserName,
        timestamp: Date.now() 
      });
      socket.emit('join-confirmed', { 
        room, 
        userId, 
        userInfo,
        timestamp: Date.now() 
      });
      
    } catch (error) {
      console.error(ts(), '💥', socket.id, 'Auth error for', userId, ':', error.message);
      socket.emit('auth-failed', { 
        reason: 'Authentication system error',
        error: error.message
      });
    }
  });

  socket.on('signal', ({ room, to, type, payload }) => {
    if (!room || !type) return;
    
    const fromUserId = socketToUser.get(socket.id);
    if (!fromUserId || !authenticatedUsers.has(fromUserId)) {
      socket.emit('auth-required', { message: 'Authentication required' });
      return;
    }
    
    // Update last seen
    const userAuth = authenticatedUsers.get(fromUserId);
    userAuth.lastSeen = Date.now();
    
    const msg = { from: socket.id, fromUserId, type, payload, timestamp: Date.now() };
    console.log(ts(), '📡', socket.id, `(${fromUserId})`, 'signal', type, 'to', to || 'room');
    
    if (to) {
      // Enhanced target resolution
      let targetSocketId = null;
      
      // Try as volunteerID first
      if (userToSocket.has(to)) {
        targetSocketId = userToSocket.get(to);
        console.log(ts(), '🎯', 'Resolved', to, 'as volunteerID →', targetSocketId);
      } 
      // Try as socket.id (backward compatibility)
      else if (io.sockets.sockets.has(to)) {
        targetSocketId = to;
        console.log(ts(), '🎯', 'Using', to, 'as socket.id directly');
      }
      
      // Deliver message if target found
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
        console.warn(ts(), '❌', 'Signal target not found:', to);
        socket.emit('signal-failed', { 
          originalTarget: to, 
          type: type,
          reason: 'Target not found',
          availableUsers: Array.from(userToSocket.keys()),
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

  socket.on('error', (error) => {
    const userId = socketToUser.get(socket.id);
    console.error(ts(), '💥', socket.id, `(${userId || 'no-userId'})`, 'socket error:', error);
  });

  // Health check
  socket.on('ping', () => {
    socket.emit('pong');
  });

  // Debug endpoint
  socket.on('debug-mappings', () => {
    const userId = socketToUser.get(socket.id);
    const debugInfo = {
      socketToUser: Object.fromEntries(socketToUser),
      userToSocket: Object.fromEntries(userToSocket),
      roomUsers: Object.fromEntries(Array.from(roomUsers.entries()).map(([k, v]) => [k, Array.from(v)])),
      authenticatedUsers: Object.fromEntries(Array.from(authenticatedUsers.entries()).map(([k, v]) => [k, {
        socketId: v.socketId,
        lastSeen: new Date(v.lastSeen).toISOString(),
        userName: v.userInfo?.UserName
      }])),
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

// Periodic validation - check if users are still logged in (only if database auth is available)
if (volunteerAuth) {
  setInterval(async () => {
    const userIds = Array.from(authenticatedUsers.keys());
    
    if (userIds.length === 0) {
      return;
    }
    
    console.log(ts(), '🔍', 'Validating', userIds.length, 'authenticated users...');
    
    try {
      const validationResults = await volunteerAuth.validateMultipleVolunteers(userIds);
      let disconnectedCount = 0;
      
      for (const [userId, isStillLoggedOn] of validationResults) {
        if (!isStillLoggedOn) {
          // User is no longer logged in, disconnect them
          const userAuth = authenticatedUsers.get(userId);
          if (userAuth && io.sockets.sockets.has(userAuth.socketId)) {
            const socket = io.sockets.sockets.get(userAuth.socketId);
            console.log(ts(), '⚠️', 'Disconnecting', userId, '- no longer logged in');
            socket.emit('session-expired', { 
              reason: 'No longer logged in (database check)',
              timestamp: Date.now()
            });
            socket.disconnect(true);
            disconnectedCount++;
          }
        }
      }
      
      if (disconnectedCount > 0) {
        console.log(ts(), '🧹', 'Disconnected', disconnectedCount, 'users who are no longer logged in');
      }
      
    } catch (error) {
      console.error(ts(), '💥', 'Error during periodic validation:', error.message);
    }
    
  }, 120000); // Every 2 minutes
}

// Cleanup stale mappings
setInterval(() => {
  const connectedSockets = new Set(io.sockets.sockets.keys());
  let cleanedUp = 0;
  
  // Clean up stale socket->user mappings
  for (const [socketId, userId] of socketToUser.entries()) {
    if (!connectedSockets.has(socketId)) {
      socketToUser.delete(socketId);
      if (userToSocket.get(userId) === socketId) {
        userToSocket.delete(userId);
        authenticatedUsers.delete(userId);
      }
      cleanedUp++;
    }
  }
  
  if (cleanedUp > 0) {
    console.log(ts(), '🧹', 'Cleaned up', cleanedUp, 'stale mappings');
  }
  
  // Status log every 5 minutes
  const now = Date.now();
  if (!this.lastStatusLog || now - this.lastStatusLog > 300000) {
    this.lastStatusLog = now;
    console.log(ts(), '📊', 'Status: Connected sockets:', connectedSockets.size, 
                'Authenticated users:', authenticatedUsers.size, 'Active rooms:', roomUsers.size);
  }
}, 60000); // Every minute

// Graceful shutdown
process.on('SIGINT', async () => {
  console.log(ts(), '🛑', 'Graceful shutdown initiated...');
  
  // Notify all connected clients
  io.emit('server-shutdown', { 
    message: 'Server is shutting down', 
    timestamp: Date.now() 
  });
  
  // Close database connections
  if (volunteerAuth) {
    try {
      await volunteerAuth.close();
    } catch (error) {
      console.error('Error closing database connections:', error);
    }
  }
  
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

server.listen(PORT, () => {
  const authType = volunteerAuth ? 'Database-authenticated' : 'Simple-authenticated';
  console.log(ts(), `🚀  ${authType} signalling server running on http://localhost:${PORT}`);
});