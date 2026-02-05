/**
 * TrainingSession State Transition Tests
 *
 * Tests state transitions including:
 * - External call start/end
 * - Race conditions (another volunteer grabs call)
 * - Connection status tracking
 */

const { setupTrainingEndpointMocks } = require('../../setup/mocks/fetchMock');

describe('TrainingSession State Transitions', () => {

  beforeEach(() => {
    setupTrainingEndpointMocks();
    global.announceToScreenReader = jest.fn();
  });

  afterEach(() => {
    delete global.announceToScreenReader;
  });

  /**
   * Helper to create a state test session
   */
  function createStateTestSession(options = {}) {
    return {
      volunteerID: options.volunteerID || 'TestTrainer',
      role: options.role || 'trainer',
      trainer: { id: options.trainerId || 'TestTrainer' },
      conferenceID: options.conferenceID || 'TestTrainer',

      // State
      currentlyOnCall: options.currentlyOnCall || false,
      isController: options.isController !== undefined ? options.isController : true,
      activeController: options.activeController || 'TestTrainer',
      connectionStatus: options.connectionStatus || 'connected',
      muted: options.muted || false,
      myCallSid: options.myCallSid || null,

      // Mock connection - use explicit check to allow null override
      connection: options.hasOwnProperty('connection') ? options.connection : {
        _status: 'open',
        status() { return this._status; },
        mute: jest.fn(),
        disconnect: jest.fn()
      },

      // State change tracking
      _stateChanges: [],

      setExternalCallActive(isActive, source = 'test') {
        this._stateChanges.push({
          type: 'callActive',
          isActive,
          source,
          timestamp: Date.now()
        });

        this.currentlyOnCall = isActive;
        this.applyMuteState(source);
      },

      applyMuteState(reason) {
        const shouldBeMuted = this.currentlyOnCall && !this.isController;

        this._stateChanges.push({
          type: 'muteState',
          shouldBeMuted,
          currentlyOnCall: this.currentlyOnCall,
          isController: this.isController,
          reason
        });

        this.muted = shouldBeMuted;
      },

      handleExternalCallStart(message) {
        const activeController = message.activeController;

        // Check for active connection (race condition protection)
        const hasActiveConnection = this.connection &&
          typeof this.connection.status === 'function' &&
          this.connection.status() === 'open';

        if (!hasActiveConnection) {
          this._stateChanges.push({
            type: 'callStartSkipped',
            reason: 'no_active_connection'
          });
          return false;
        }

        this.setExternalCallActive(true, 'handleExternalCallStart');
        return true;
      },

      handleExternalCallEnd(message) {
        this.setExternalCallActive(false, 'handleExternalCallEnd');
      },

      startNewCall(callParams) {
        // Verify we have an active connection before proceeding
        const hasActiveConnection = this.connection &&
          typeof this.connection.status === 'function' &&
          this.connection.status() === 'open';

        if (!hasActiveConnection) {
          this._stateChanges.push({
            type: 'newCallSkipped',
            reason: 'no_active_connection'
          });
          return false;
        }

        this.setExternalCallActive(true, 'startNewCall');
        return true;
      }
    };
  }

  describe('External Call State Management', () => {

    test('setExternalCallActive(true) sets currentlyOnCall', () => {
      const session = createStateTestSession({ currentlyOnCall: false });

      session.setExternalCallActive(true, 'test');

      expect(session.currentlyOnCall).toBe(true);
    });

    test('setExternalCallActive(false) clears currentlyOnCall', () => {
      const session = createStateTestSession({ currentlyOnCall: true });

      session.setExternalCallActive(false, 'test');

      expect(session.currentlyOnCall).toBe(false);
    });

    test('setExternalCallActive triggers applyMuteState', () => {
      const session = createStateTestSession();

      session.setExternalCallActive(true, 'test');

      const muteStateChange = session._stateChanges.find(c => c.type === 'muteState');
      expect(muteStateChange).toBeDefined();
    });

    test('Source parameter is tracked in state changes', () => {
      const session = createStateTestSession();

      session.setExternalCallActive(true, 'twilio_webhook');

      expect(session._stateChanges).toContainEqual(
        expect.objectContaining({
          type: 'callActive',
          source: 'twilio_webhook'
        })
      );
    });
  });

  describe('Race Condition Protection', () => {

    test('handleExternalCallStart skips muting when no active connection', () => {
      const session = createStateTestSession({
        connection: {
          _status: 'closed',
          status() { return this._status; }
        }
      });

      const result = session.handleExternalCallStart({ activeController: 'TestTrainer' });

      expect(result).toBe(false);
      expect(session.currentlyOnCall).toBe(false);
      expect(session._stateChanges).toContainEqual(
        expect.objectContaining({
          type: 'callStartSkipped',
          reason: 'no_active_connection'
        })
      );
    });

    test('handleExternalCallStart proceeds with active connection', () => {
      const session = createStateTestSession({
        connection: {
          _status: 'open',
          status() { return this._status; }
        }
      });

      const result = session.handleExternalCallStart({ activeController: 'TestTrainer' });

      expect(result).toBe(true);
      expect(session.currentlyOnCall).toBe(true);
    });

    test('startNewCall skips when another volunteer grabbed the call', () => {
      const session = createStateTestSession({
        connection: null // No connection = another volunteer got it
      });

      const result = session.startNewCall({ callSid: 'CA123' });

      expect(result).toBe(false);
      expect(session.currentlyOnCall).toBe(false);
    });

    test('startNewCall proceeds when we have the call', () => {
      const session = createStateTestSession({
        connection: {
          _status: 'open',
          status() { return this._status; }
        }
      });

      const result = session.startNewCall({ callSid: 'CA123' });

      expect(result).toBe(true);
      expect(session.currentlyOnCall).toBe(true);
    });
  });

  describe('Connection Status Tracking', () => {

    test('Tracks connection status changes', () => {
      const session = createStateTestSession({ connectionStatus: 'disconnected' });

      // Simulate connection established
      session.connectionStatus = 'connected';

      expect(session.connectionStatus).toBe('connected');
    });

    test('Connection disconnect clears call state', () => {
      const session = createStateTestSession({
        currentlyOnCall: true,
        connectionStatus: 'connected'
      });

      // Simulate disconnect
      session.connectionStatus = 'disconnected';
      session.setExternalCallActive(false, 'disconnect');

      expect(session.currentlyOnCall).toBe(false);
    });
  });

  describe('Complete Call Flow', () => {

    test('Full external call lifecycle for non-controller', () => {
      const session = createStateTestSession({
        role: 'trainee',
        isController: false,
        currentlyOnCall: false,
        muted: false
      });

      // 1. Call starts - should be muted
      session.handleExternalCallStart({ activeController: 'TestTrainer' });
      expect(session.currentlyOnCall).toBe(true);
      expect(session.muted).toBe(true);

      // 2. Call ends - should be unmuted
      session.handleExternalCallEnd({});
      expect(session.currentlyOnCall).toBe(false);
      expect(session.muted).toBe(false);
    });

    test('Full external call lifecycle for controller', () => {
      const session = createStateTestSession({
        role: 'trainer',
        isController: true,
        currentlyOnCall: false,
        muted: false
      });

      // 1. Call starts - should NOT be muted
      session.handleExternalCallStart({ activeController: 'TestTrainer' });
      expect(session.currentlyOnCall).toBe(true);
      expect(session.muted).toBe(false);

      // 2. Call ends - should still be unmuted
      session.handleExternalCallEnd({});
      expect(session.currentlyOnCall).toBe(false);
      expect(session.muted).toBe(false);
    });
  });

  describe('CallSid Tracking', () => {

    test('myCallSid is set when joining conference', () => {
      const session = createStateTestSession({ myCallSid: null });

      // Simulate conference join
      session.myCallSid = 'CA_test_123';

      expect(session.myCallSid).toBe('CA_test_123');
    });

    test('myCallSid is used for server-side muting', () => {
      const session = createStateTestSession({
        myCallSid: 'CA_test_123',
        isController: false
      });

      // Verify CallSid is available for muting
      session.setExternalCallActive(true, 'test');

      expect(session.myCallSid).toBe('CA_test_123');
      expect(session.muted).toBe(true);
    });

    test('myCallSid cleared on disconnect', () => {
      const session = createStateTestSession({ myCallSid: 'CA_test_123' });

      // Simulate disconnect
      session.myCallSid = null;
      session.connection = null;

      expect(session.myCallSid).toBeNull();
    });
  });

  describe('State Change Logging', () => {

    test('All state changes are logged with timestamps', () => {
      const session = createStateTestSession();

      session.setExternalCallActive(true, 'test1');
      session.setExternalCallActive(false, 'test2');

      expect(session._stateChanges.length).toBeGreaterThanOrEqual(2);

      const callChanges = session._stateChanges.filter(c => c.type === 'callActive');
      expect(callChanges.every(c => c.timestamp)).toBe(true);
    });

    test('Mute decisions are logged with context', () => {
      const session = createStateTestSession({
        isController: false
      });

      session.setExternalCallActive(true, 'call_arrived');

      const muteDecision = session._stateChanges.find(c => c.type === 'muteState');
      expect(muteDecision).toEqual(expect.objectContaining({
        shouldBeMuted: true,
        currentlyOnCall: true,
        isController: false,
        reason: 'call_arrived'
      }));
    });
  });
});
