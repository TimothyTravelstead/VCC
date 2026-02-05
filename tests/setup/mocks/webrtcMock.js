/**
 * WebRTC Mock
 * Mocks RTCPeerConnection, MediaStream, and getDisplayMedia
 */

class MockMediaStreamTrack {
  constructor(kind = 'video') {
    this.kind = kind;
    this.enabled = true;
    this.muted = false;
    this.readyState = 'live';
    this.id = 'track_' + Math.random().toString(36).substr(2, 9);
    this._eventHandlers = {};
  }

  stop() {
    this.readyState = 'ended';
    this._triggerEvent('ended');
  }

  clone() {
    return new MockMediaStreamTrack(this.kind);
  }

  on(event, handler) {
    if (!this._eventHandlers[event]) {
      this._eventHandlers[event] = [];
    }
    this._eventHandlers[event].push(handler);
    return this;
  }

  addEventListener(event, handler) {
    return this.on(event, handler);
  }

  removeEventListener(event, handler) {
    if (this._eventHandlers[event]) {
      this._eventHandlers[event] = this._eventHandlers[event].filter(h => h !== handler);
    }
  }

  _triggerEvent(event, ...args) {
    if (this._eventHandlers[event]) {
      this._eventHandlers[event].forEach(handler => handler(...args));
    }
  }
}

class MockMediaStream {
  constructor(tracks = []) {
    this.id = 'stream_' + Math.random().toString(36).substr(2, 9);
    this.active = true;
    this._tracks = tracks.length > 0 ? tracks : [
      new MockMediaStreamTrack('video'),
      new MockMediaStreamTrack('audio')
    ];
  }

  getTracks() {
    return this._tracks;
  }

  getVideoTracks() {
    return this._tracks.filter(t => t.kind === 'video');
  }

  getAudioTracks() {
    return this._tracks.filter(t => t.kind === 'audio');
  }

  addTrack(track) {
    this._tracks.push(track);
  }

  removeTrack(track) {
    this._tracks = this._tracks.filter(t => t.id !== track.id);
  }

  clone() {
    return new MockMediaStream(this._tracks.map(t => t.clone()));
  }
}

class MockRTCPeerConnection {
  constructor(config = {}) {
    this.configuration = config;
    this.connectionState = 'new';
    this.iceConnectionState = 'new';
    this.iceGatheringState = 'new';
    this.signalingState = 'stable';
    this.localDescription = null;
    this.remoteDescription = null;
    this._eventHandlers = {};
    this._localStreams = [];
    this._remoteStreams = [];
    this._senders = [];
    this._receivers = [];
  }

  createOffer(options = {}) {
    return Promise.resolve({
      type: 'offer',
      sdp: 'mock_offer_sdp_' + Date.now()
    });
  }

  createAnswer(options = {}) {
    return Promise.resolve({
      type: 'answer',
      sdp: 'mock_answer_sdp_' + Date.now()
    });
  }

  setLocalDescription(desc) {
    this.localDescription = desc;
    this.signalingState = desc.type === 'offer' ? 'have-local-offer' : 'stable';
    return Promise.resolve();
  }

  setRemoteDescription(desc) {
    this.remoteDescription = desc;
    this.signalingState = desc.type === 'offer' ? 'have-remote-offer' : 'stable';

    // Simulate ICE candidate gathering
    setTimeout(() => {
      this._triggerEvent('icecandidate', { candidate: { candidate: 'mock_candidate' } });
      this._triggerEvent('icecandidate', { candidate: null }); // End of candidates
    }, 10);

    return Promise.resolve();
  }

  addIceCandidate(candidate) {
    return Promise.resolve();
  }

  addTrack(track, stream) {
    const sender = {
      track,
      getStats: () => Promise.resolve(new Map())
    };
    this._senders.push(sender);
    return sender;
  }

  addStream(stream) {
    this._localStreams.push(stream);
    stream.getTracks().forEach(track => this.addTrack(track, stream));
  }

  removeTrack(sender) {
    this._senders = this._senders.filter(s => s !== sender);
  }

  getSenders() {
    return this._senders;
  }

  getReceivers() {
    return this._receivers;
  }

  close() {
    this.connectionState = 'closed';
    this.signalingState = 'closed';
    this._triggerEvent('connectionstatechange');
  }

  on(event, handler) {
    if (!this._eventHandlers[event]) {
      this._eventHandlers[event] = [];
    }
    this._eventHandlers[event].push(handler);
    return this;
  }

  addEventListener(event, handler) {
    return this.on(event, handler);
  }

  removeEventListener(event, handler) {
    if (this._eventHandlers[event]) {
      this._eventHandlers[event] = this._eventHandlers[event].filter(h => h !== handler);
    }
  }

  _triggerEvent(event, data) {
    // Support both onXxx handlers and addEventListener
    const handlerName = 'on' + event;
    if (this[handlerName]) {
      this[handlerName](data);
    }
    if (this._eventHandlers[event]) {
      this._eventHandlers[event].forEach(handler => handler(data));
    }
  }

  // Test helpers
  _simulateTrack(track) {
    const receiver = { track };
    this._receivers.push(receiver);
    this._triggerEvent('track', {
      track,
      streams: [new MockMediaStream([track])]
    });
  }

  _simulateConnectionEstablished() {
    this.iceConnectionState = 'connected';
    this.connectionState = 'connected';
    this._triggerEvent('iceconnectionstatechange');
    this._triggerEvent('connectionstatechange');
  }
}

/**
 * Mock for navigator.mediaDevices.getDisplayMedia
 */
function mockGetDisplayMedia(constraints = {}) {
  return Promise.resolve(new MockMediaStream([
    new MockMediaStreamTrack('video')
  ]));
}

module.exports = {
  MockMediaStreamTrack,
  MockMediaStream,
  MockRTCPeerConnection,
  mockGetDisplayMedia
};
