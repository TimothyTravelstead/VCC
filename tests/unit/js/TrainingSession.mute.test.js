/**
 * TrainingSession Mute Logic Tests
 *
 * Tests the core mute rule: shouldBeMuted = currentlyOnCall && !isController
 *
 * Truth Table:
 * | currentlyOnCall | isController | shouldBeMuted |
 * |-----------------|--------------|---------------|
 * | false           | true         | false         |
 * | false           | false        | false         |
 * | true            | true         | false         |
 * | true            | false        | TRUE          |
 */

const { setupTrainingEndpointMocks, getFetchCalls, getFetchCallsMatching } = require('../../setup/mocks/fetchMock');
const { setupTrainingDOM } = require('../../setup/mocks/domMock');
const { createMockCallMonitor } = require('../../setup/mocks/twilioMock');

describe('TrainingSession Mute Logic', () => {
  let session;
  let mockCallMonitor;

  beforeEach(() => {
    // Setup DOM and mocks
    setupTrainingEndpointMocks();
    mockCallMonitor = createMockCallMonitor();
    global.callMonitor = mockCallMonitor;
    global.announceToScreenReader = jest.fn();
  });

  afterEach(() => {
    if (session && session.destroy) {
      session.destroy();
    }
    session = null;
    delete global.callMonitor;
    delete global.announceToScreenReader;
  });

  /**
   * Helper to create a minimal TrainingSession-like object for testing
   * the pure mute logic without full initialization
   */
  function createMuteTestSession(options = {}) {
    return {
      currentlyOnCall: options.currentlyOnCall || false,
      isController: options.isController !== undefined ? options.isController : true,
      volunteerID: options.volunteerID || 'TestUser',
      role: options.role || 'trainer',
      trainer: { id: options.trainerId || 'TestTrainer' },
      conferenceID: options.conferenceID || 'TestTrainer',
      myCallSid: options.myCallSid || null,
      muted: false,
      _previousMuteState: false,
      _muteActions: [],

      // Track mute calls for assertions
      async _doMute(reason) {
        this.muted = true;
        this._muteActions.push({ action: 'mute', reason });
      },

      async _doUnmute(reason) {
        this.muted = false;
        this._muteActions.push({ action: 'unmute', reason });
      },

      // Core method under test
      applyMuteState(reason = 'test') {
        const shouldBeMuted = this.currentlyOnCall && !this.isController;
        const wasMuted = this._previousMuteState;
        this._previousMuteState = shouldBeMuted;

        if (shouldBeMuted) {
          this._doMute(reason);
        } else {
          this._doUnmute(reason);
        }
      },

      setExternalCallActive(isActive, source = 'test') {
        this.currentlyOnCall = isActive;
        this.applyMuteState(source);
      }
    };
  }

  describe('Core Mute Rule: shouldBeMuted = currentlyOnCall && !isController', () => {

    test('No call, trainer (controller) → NOT muted', () => {
      const session = createMuteTestSession({
        currentlyOnCall: false,
        isController: true,
        role: 'trainer'
      });

      session.applyMuteState('test');

      expect(session.muted).toBe(false);
      expect(session._muteActions).toContainEqual(
        expect.objectContaining({ action: 'unmute' })
      );
    });

    test('No call, trainee (non-controller) → NOT muted', () => {
      const session = createMuteTestSession({
        currentlyOnCall: false,
        isController: false,
        role: 'trainee'
      });

      session.applyMuteState('test');

      expect(session.muted).toBe(false);
      expect(session._muteActions).toContainEqual(
        expect.objectContaining({ action: 'unmute' })
      );
    });

    test('Call active, controller → NOT muted', () => {
      const session = createMuteTestSession({
        currentlyOnCall: true,
        isController: true,
        role: 'trainer'
      });

      session.applyMuteState('test');

      expect(session.muted).toBe(false);
      expect(session._muteActions).toContainEqual(
        expect.objectContaining({ action: 'unmute' })
      );
    });

    test('Call active, non-controller → MUTED', () => {
      const session = createMuteTestSession({
        currentlyOnCall: true,
        isController: false,
        role: 'trainee'
      });

      session.applyMuteState('test');

      expect(session.muted).toBe(true);
      expect(session._muteActions).toContainEqual(
        expect.objectContaining({ action: 'mute' })
      );
    });
  });

  describe('setExternalCallActive State Transitions', () => {

    test('Starting a call mutes non-controllers', () => {
      const session = createMuteTestSession({
        currentlyOnCall: false,
        isController: false,
        role: 'trainee'
      });

      // Start external call
      session.setExternalCallActive(true, 'test_call_start');

      expect(session.currentlyOnCall).toBe(true);
      expect(session.muted).toBe(true);
    });

    test('Starting a call does NOT mute controllers', () => {
      const session = createMuteTestSession({
        currentlyOnCall: false,
        isController: true,
        role: 'trainer'
      });

      // Start external call
      session.setExternalCallActive(true, 'test_call_start');

      expect(session.currentlyOnCall).toBe(true);
      expect(session.muted).toBe(false);
    });

    test('Ending a call unmutes everyone', () => {
      const session = createMuteTestSession({
        currentlyOnCall: true,
        isController: false,
        role: 'trainee'
      });

      // Force muted state
      session.muted = true;
      session._previousMuteState = true;

      // End external call
      session.setExternalCallActive(false, 'test_call_end');

      expect(session.currentlyOnCall).toBe(false);
      expect(session.muted).toBe(false);
    });

    test('Multiple state changes apply correctly', () => {
      const session = createMuteTestSession({
        currentlyOnCall: false,
        isController: false,
        role: 'trainee'
      });

      // Initial state - not muted
      expect(session.muted).toBe(false);

      // Call starts - should mute
      session.setExternalCallActive(true, 'call_start');
      expect(session.muted).toBe(true);

      // Call ends - should unmute
      session.setExternalCallActive(false, 'call_end');
      expect(session.muted).toBe(false);

      // Another call - should mute again
      session.setExternalCallActive(true, 'call_start_2');
      expect(session.muted).toBe(true);
    });
  });

  describe('Role-Based Muting Scenarios', () => {

    test('Trainer with control handles external call correctly', () => {
      const session = createMuteTestSession({
        role: 'trainer',
        isController: true,
        currentlyOnCall: false,
        volunteerID: 'TestTrainer'
      });

      // External call arrives, trainer handles it
      session.setExternalCallActive(true, 'incoming_call');

      // Trainer should NOT be muted (they're talking to caller)
      expect(session.muted).toBe(false);
    });

    test('Trainee without control stays muted during external call', () => {
      const session = createMuteTestSession({
        role: 'trainee',
        isController: false,
        currentlyOnCall: false,
        volunteerID: 'TestTrainee',
        trainerId: 'TestTrainer'
      });

      // External call arrives, trainer handles it
      session.setExternalCallActive(true, 'incoming_call');

      // Trainee should be muted (not talking to caller)
      expect(session.muted).toBe(true);
    });

    test('Trainee WITH control handles external call correctly', () => {
      const session = createMuteTestSession({
        role: 'trainee',
        isController: true, // Trainee has control
        currentlyOnCall: false,
        volunteerID: 'TestTrainee',
        trainerId: 'TestTrainer'
      });

      // External call arrives, trainee (with control) handles it
      session.setExternalCallActive(true, 'incoming_call');

      // Trainee with control should NOT be muted
      expect(session.muted).toBe(false);
    });

    test('Trainer without control gets muted during external call', () => {
      const session = createMuteTestSession({
        role: 'trainer',
        isController: false, // Trainee has control
        currentlyOnCall: false,
        volunteerID: 'TestTrainer'
      });

      // External call arrives, trainee handles it
      session.setExternalCallActive(true, 'incoming_call');

      // Trainer without control should be muted
      expect(session.muted).toBe(true);
    });
  });

  describe('Edge Cases', () => {

    test('applyMuteState is idempotent (safe to call multiple times)', () => {
      const session = createMuteTestSession({
        currentlyOnCall: true,
        isController: false
      });

      // Call multiple times
      session.applyMuteState('call_1');
      session.applyMuteState('call_2');
      session.applyMuteState('call_3');

      // Should still be muted, with 3 mute actions logged
      expect(session.muted).toBe(true);
      expect(session._muteActions.filter(a => a.action === 'mute')).toHaveLength(3);
    });

    test('State preserved after applyMuteState when nothing changes', () => {
      const session = createMuteTestSession({
        currentlyOnCall: false,
        isController: true
      });

      session.applyMuteState('initial');
      expect(session.muted).toBe(false);

      // Apply again with same state
      session.applyMuteState('check');
      expect(session.muted).toBe(false);
    });

    test('Handles rapid call start/end transitions', () => {
      const session = createMuteTestSession({
        currentlyOnCall: false,
        isController: false
      });

      // Rapid transitions
      session.setExternalCallActive(true, 'start1');
      session.setExternalCallActive(false, 'end1');
      session.setExternalCallActive(true, 'start2');
      session.setExternalCallActive(false, 'end2');

      // Final state should be unmuted
      expect(session.currentlyOnCall).toBe(false);
      expect(session.muted).toBe(false);
    });
  });
});
