/**
 * Twilio SDK Mock
 * Mocks the @twilio/voice-sdk Device and Connection classes
 */

class MockConnection {
  constructor(options = {}) {
    this.status = options.status || 'pending';
    this.isMuted = false;
    this._parameters = options.parameters || { CallSid: 'CA_test_' + Date.now() };
    this._eventHandlers = {};
    this._customParameters = options.customParameters || {};
  }

  get parameters() {
    return this._parameters;
  }

  get customParameters() {
    return this._customParameters;
  }

  mute(shouldMute) {
    if (shouldMute === undefined) {
      // Toggle if no argument
      this.isMuted = !this.isMuted;
    } else {
      this.isMuted = shouldMute;
    }
    this._triggerEvent('mute', this.isMuted, this);
    return this;
  }

  accept() {
    this.status = 'open';
    this._triggerEvent('accept', this);
    return this;
  }

  reject() {
    this.status = 'closed';
    this._triggerEvent('reject', this);
    return this;
  }

  disconnect() {
    this.status = 'closed';
    this._triggerEvent('disconnect', this);
    return this;
  }

  on(event, handler) {
    if (!this._eventHandlers[event]) {
      this._eventHandlers[event] = [];
    }
    this._eventHandlers[event].push(handler);
    return this;
  }

  off(event, handler) {
    if (this._eventHandlers[event]) {
      this._eventHandlers[event] = this._eventHandlers[event].filter(h => h !== handler);
    }
    return this;
  }

  _triggerEvent(event, ...args) {
    if (this._eventHandlers[event]) {
      this._eventHandlers[event].forEach(handler => handler(...args));
    }
  }

  // Test helpers
  _simulateAccept() {
    return this.accept();
  }

  _simulateDisconnect() {
    return this.disconnect();
  }

  _simulateIncoming(params = {}) {
    this.status = 'pending';
    this._parameters = { ...this._parameters, ...params };
    this._triggerEvent('incoming', this);
    return this;
  }
}

class MockDevice {
  constructor(token, options = {}) {
    this.token = token;
    this.options = options;
    this.state = 'registered';
    this._eventHandlers = {};
    this._connections = [];
    this._currentConnection = null;
  }

  register() {
    this.state = 'registered';
    this._triggerEvent('registered', this);
    return Promise.resolve(this);
  }

  unregister() {
    this.state = 'unregistered';
    this._triggerEvent('unregistered', this);
    return Promise.resolve(this);
  }

  connect(params = {}) {
    const connection = new MockConnection({
      parameters: { CallSid: 'CA_outgoing_' + Date.now() },
      customParameters: params
    });
    this._currentConnection = connection;
    this._connections.push(connection);

    // Simulate async connection
    setTimeout(() => {
      connection.status = 'connecting';
      this._triggerEvent('connect', connection);
    }, 0);

    return Promise.resolve(connection);
  }

  disconnectAll() {
    this._connections.forEach(conn => conn.disconnect());
    this._connections = [];
    this._currentConnection = null;
    return this;
  }

  on(event, handler) {
    if (!this._eventHandlers[event]) {
      this._eventHandlers[event] = [];
    }
    this._eventHandlers[event].push(handler);
    return this;
  }

  off(event, handler) {
    if (this._eventHandlers[event]) {
      this._eventHandlers[event] = this._eventHandlers[event].filter(h => h !== handler);
    }
    return this;
  }

  _triggerEvent(event, ...args) {
    if (this._eventHandlers[event]) {
      this._eventHandlers[event].forEach(handler => handler(...args));
    }
  }

  // Test helpers
  _simulateIncomingCall(params = {}) {
    const connection = new MockConnection({
      status: 'pending',
      parameters: { CallSid: 'CA_incoming_' + Date.now(), ...params }
    });
    this._currentConnection = connection;
    this._connections.push(connection);
    this._triggerEvent('incoming', connection);
    return connection;
  }

  _getActiveConnection() {
    return this._currentConnection;
  }
}

/**
 * Create a mock callMonitor object that simulates the global callMonitor
 */
function createMockCallMonitor(options = {}) {
  const device = new MockDevice('test_token');
  let activeCall = null;

  return {
    device,
    getDevice() {
      return device;
    },
    getActiveCall() {
      return activeCall;
    },
    setActiveCall(call) {
      activeCall = call;
    },
    // Simulate receiving an incoming call
    _simulateIncomingCall(params = {}) {
      activeCall = device._simulateIncomingCall(params);
      return activeCall;
    },
    // Simulate call ending
    _simulateCallEnd() {
      if (activeCall) {
        activeCall.disconnect();
        activeCall = null;
      }
    }
  };
}

module.exports = {
  MockDevice,
  MockConnection,
  createMockCallMonitor
};
