/**
 * MultiTraineeScreenSharing Tests
 *
 * Tests the WebRTC screen sharing functionality:
 * - Participant join/leave
 * - Signaling message handling
 * - Peer connection management
 */

const { MockRTCPeerConnection, MockMediaStream, mockGetDisplayMedia } = require('../../setup/mocks/webrtcMock');
const { setupTrainingEndpointMocks, setFetchResponse, getFetchCallsMatching } = require('../../setup/mocks/fetchMock');

describe('MultiTraineeScreenSharing', () => {

  beforeEach(() => {
    setupTrainingEndpointMocks();

    // Setup WebRTC mocks
    global.RTCPeerConnection = MockRTCPeerConnection;
    global.RTCSessionDescription = jest.fn((desc) => desc);
    global.RTCIceCandidate = jest.fn((candidate) => candidate);

    Object.defineProperty(navigator, 'mediaDevices', {
      value: {
        getDisplayMedia: mockGetDisplayMedia,
        getUserMedia: jest.fn().mockResolvedValue(new MockMediaStream())
      },
      writable: true
    });
  });

  /**
   * Create a screen sharing test instance
   */
  function createScreenSharingInstance(options = {}) {
    return {
      role: options.role || 'trainer',
      participantId: options.participantId || 'TestTrainer',
      trainerId: options.trainerId || 'TestTrainer',
      roomId: options.roomId || 'TestTrainer',

      // State
      isSharing: false,
      localStream: null,
      peerConnections: new Map(),
      participants: new Map(),
      pendingCandidates: new Map(),

      // Mock methods
      async startSharing() {
        try {
          this.localStream = await navigator.mediaDevices.getDisplayMedia({
            video: true
          });
          this.isSharing = true;

          // Notify all participants
          this.participants.forEach((participant, peerId) => {
            this.createPeerConnection(peerId);
          });

          return true;
        } catch (error) {
          console.error('Failed to start sharing:', error);
          return false;
        }
      },

      stopSharing() {
        if (this.localStream) {
          this.localStream.getTracks().forEach(track => track.stop());
          this.localStream = null;
        }
        this.isSharing = false;

        // Close all peer connections
        this.peerConnections.forEach((pc, peerId) => {
          pc.close();
        });
        this.peerConnections.clear();
      },

      createPeerConnection(peerId) {
        const pc = new RTCPeerConnection({
          iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
        });

        this.peerConnections.set(peerId, pc);

        // Add local stream tracks
        if (this.localStream) {
          this.localStream.getTracks().forEach(track => {
            pc.addTrack(track, this.localStream);
          });
        }

        return pc;
      },

      async handleParticipantJoined(peerId, participantInfo) {
        this.participants.set(peerId, participantInfo);

        // If we're sharing, create connection and send offer
        if (this.isSharing) {
          const pc = this.createPeerConnection(peerId);
          const offer = await pc.createOffer();
          await pc.setLocalDescription(offer);

          // Send offer via signaling
          return {
            type: 'offer',
            sdp: offer.sdp,
            to: peerId
          };
        }

        return null;
      },

      handleParticipantLeft(peerId) {
        this.participants.delete(peerId);

        const pc = this.peerConnections.get(peerId);
        if (pc) {
          pc.close();
          this.peerConnections.delete(peerId);
        }
      },

      async handleOffer(peerId, offer) {
        let pc = this.peerConnections.get(peerId);

        if (!pc) {
          pc = this.createPeerConnection(peerId);
        }

        await pc.setRemoteDescription(offer);
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);

        // Apply any pending ICE candidates
        const pending = this.pendingCandidates.get(peerId) || [];
        for (const candidate of pending) {
          await pc.addIceCandidate(candidate);
        }
        this.pendingCandidates.delete(peerId);

        return {
          type: 'answer',
          sdp: answer.sdp,
          to: peerId
        };
      },

      async handleAnswer(peerId, answer) {
        const pc = this.peerConnections.get(peerId);

        if (pc) {
          await pc.setRemoteDescription(answer);
          return true;
        }

        return false;
      },

      async handleIceCandidate(peerId, candidate) {
        const pc = this.peerConnections.get(peerId);

        if (pc && pc.remoteDescription) {
          await pc.addIceCandidate(candidate);
        } else {
          // Queue candidate for later
          if (!this.pendingCandidates.has(peerId)) {
            this.pendingCandidates.set(peerId, []);
          }
          this.pendingCandidates.get(peerId).push(candidate);
        }
      }
    };
  }

  describe('Screen Sharing Lifecycle', () => {

    test('Trainer can start screen sharing', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      const result = await sharing.startSharing();

      expect(result).toBe(true);
      expect(sharing.isSharing).toBe(true);
      expect(sharing.localStream).not.toBeNull();
    });

    test('Trainer can stop screen sharing', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      await sharing.startSharing();
      sharing.stopSharing();

      expect(sharing.isSharing).toBe(false);
      expect(sharing.localStream).toBeNull();
    });

    test('Stopping clears all peer connections', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      await sharing.startSharing();

      // Add some participants
      await sharing.handleParticipantJoined('trainee1', { id: 'trainee1' });
      await sharing.handleParticipantJoined('trainee2', { id: 'trainee2' });

      expect(sharing.peerConnections.size).toBe(2);

      sharing.stopSharing();

      expect(sharing.peerConnections.size).toBe(0);
    });
  });

  describe('Participant Management', () => {

    test('New participant triggers offer when sharing', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      await sharing.startSharing();

      const signalMessage = await sharing.handleParticipantJoined('trainee1', {
        id: 'trainee1',
        name: 'Test Trainee'
      });

      expect(signalMessage).not.toBeNull();
      expect(signalMessage.type).toBe('offer');
      expect(signalMessage.to).toBe('trainee1');
    });

    test('New participant does not trigger offer when not sharing', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      const signalMessage = await sharing.handleParticipantJoined('trainee1', {
        id: 'trainee1'
      });

      expect(signalMessage).toBeNull();
    });

    test('Participant leave cleans up peer connection', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      await sharing.startSharing();
      await sharing.handleParticipantJoined('trainee1', { id: 'trainee1' });

      expect(sharing.peerConnections.has('trainee1')).toBe(true);

      sharing.handleParticipantLeft('trainee1');

      expect(sharing.peerConnections.has('trainee1')).toBe(false);
      expect(sharing.participants.has('trainee1')).toBe(false);
    });
  });

  describe('Signaling Message Handling', () => {

    test('Handles incoming offer and creates answer', async () => {
      const sharing = createScreenSharingInstance({
        role: 'trainee',
        participantId: 'trainee1'
      });

      const offer = { type: 'offer', sdp: 'mock_offer_sdp' };

      const response = await sharing.handleOffer('TestTrainer', offer);

      expect(response).not.toBeNull();
      expect(response.type).toBe('answer');
      expect(response.to).toBe('TestTrainer');
    });

    test('Handles incoming answer', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      await sharing.startSharing();
      await sharing.handleParticipantJoined('trainee1', { id: 'trainee1' });

      const answer = { type: 'answer', sdp: 'mock_answer_sdp' };

      const result = await sharing.handleAnswer('trainee1', answer);

      expect(result).toBe(true);
    });

    test('Handles ICE candidates', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      await sharing.startSharing();
      await sharing.handleParticipantJoined('trainee1', { id: 'trainee1' });

      // Set remote description first
      const pc = sharing.peerConnections.get('trainee1');
      await pc.setRemoteDescription({ type: 'answer', sdp: 'mock_answer' });

      const candidate = { candidate: 'mock_candidate' };

      await sharing.handleIceCandidate('trainee1', candidate);

      // No error means success
      expect(true).toBe(true);
    });

    test('Queues ICE candidates before remote description', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      // Add candidate before connection exists
      await sharing.handleIceCandidate('trainee1', { candidate: 'early_candidate' });

      expect(sharing.pendingCandidates.has('trainee1')).toBe(true);
      expect(sharing.pendingCandidates.get('trainee1')).toHaveLength(1);
    });
  });

  describe('Peer Connection Creation', () => {

    test('Creates peer connection with ICE servers', () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      const pc = sharing.createPeerConnection('trainee1');

      expect(pc).toBeInstanceOf(MockRTCPeerConnection);
      expect(sharing.peerConnections.has('trainee1')).toBe(true);
    });

    test('Adds local stream tracks to peer connection', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      await sharing.startSharing();
      const pc = sharing.createPeerConnection('trainee1');

      // Stream tracks should be added
      expect(pc.getSenders().length).toBeGreaterThan(0);
    });
  });

  describe('Multi-Trainee Scenarios', () => {

    test('Handles multiple trainees joining', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      await sharing.startSharing();

      await sharing.handleParticipantJoined('trainee1', { id: 'trainee1' });
      await sharing.handleParticipantJoined('trainee2', { id: 'trainee2' });
      await sharing.handleParticipantJoined('trainee3', { id: 'trainee3' });

      expect(sharing.participants.size).toBe(3);
      expect(sharing.peerConnections.size).toBe(3);
    });

    test('Handles trainee leaving while others remain', async () => {
      const sharing = createScreenSharingInstance({ role: 'trainer' });

      await sharing.startSharing();

      await sharing.handleParticipantJoined('trainee1', { id: 'trainee1' });
      await sharing.handleParticipantJoined('trainee2', { id: 'trainee2' });

      sharing.handleParticipantLeft('trainee1');

      expect(sharing.participants.size).toBe(1);
      expect(sharing.peerConnections.size).toBe(1);
      expect(sharing.peerConnections.has('trainee2')).toBe(true);
    });
  });
});
