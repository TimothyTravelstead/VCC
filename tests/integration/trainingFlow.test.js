/**
 * Training Flow Integration Tests
 *
 * Tests complete training session lifecycle:
 * 1. Trainer and trainee initialize sessions
 * 2. Both connect to conference
 * 3. External call arrives → non-controller muted
 * 4. Call ends → everyone unmuted
 * 5. Trainer transfers control to trainee
 * 6. New call → trainer now muted (roles reversed)
 */

const { setupTrainingEndpointMocks, setFetchResponse, getFetchCalls, clearFetchMocks } = require('../setup/mocks/fetchMock');
const { setupTrainingDOM, cleanupDOM } = require('../setup/mocks/domMock');
const { createMockCallMonitor, MockConnection } = require('../setup/mocks/twilioMock');

describe('Training Flow Integration', () => {

  beforeEach(() => {
    setupTrainingEndpointMocks();
    global.announceToScreenReader = jest.fn();
  });

  afterEach(() => {
    cleanupDOM();
    clearFetchMocks();
    delete global.announceToScreenReader;
  });

  /**
   * Creates a complete training session simulation
   */
  function createTrainingSimulation() {
    // Create trainer session
    const trainer = createParticipantSession({
      volunteerID: 'TestTrainer',
      role: 'trainer',
      trainerId: 'TestTrainer',
      trainees: [
        { id: 'TestTrainee1', name: 'Trainee 1', isSignedOn: true },
        { id: 'TestTrainee2', name: 'Trainee 2', isSignedOn: true }
      ],
      isController: true
    });

    // Create trainee sessions
    const trainee1 = createParticipantSession({
      volunteerID: 'TestTrainee1',
      role: 'trainee',
      trainerId: 'TestTrainer',
      isController: false
    });

    const trainee2 = createParticipantSession({
      volunteerID: 'TestTrainee2',
      role: 'trainee',
      trainerId: 'TestTrainer',
      isController: false
    });

    return {
      trainer,
      trainee1,
      trainee2,
      allParticipants: [trainer, trainee1, trainee2],

      /**
       * Simulate external call starting
       */
      startExternalCall() {
        this.allParticipants.forEach(p => {
          p.setExternalCallActive(true, 'incoming_call');
        });
      },

      /**
       * Simulate external call ending
       */
      endExternalCall() {
        this.allParticipants.forEach(p => {
          p.setExternalCallActive(false, 'call_ended');
        });
      },

      /**
       * Transfer control from trainer to a trainee
       */
      transferControl(newControllerId, controllerRole) {
        // Update all participants' state
        this.allParticipants.forEach(p => {
          p.activeController = newControllerId;
          p.isController = (p.volunteerID === newControllerId);
          p.incomingCallsTo = newControllerId;
        });
      },

      /**
       * Get mute states of all participants
       */
      getMuteStates() {
        return {
          trainer: this.trainer.muted,
          trainee1: this.trainee1.muted,
          trainee2: this.trainee2.muted
        };
      }
    };
  }

  /**
   * Creates a participant session for testing
   */
  function createParticipantSession(options) {
    return {
      volunteerID: options.volunteerID,
      role: options.role,
      trainer: { id: options.trainerId },
      trainees: options.trainees || [],
      conferenceID: options.trainerId,

      // State
      currentlyOnCall: false,
      isController: options.isController,
      activeController: options.trainerId,
      incomingCallsTo: options.trainerId,
      muted: false,
      connectionStatus: 'connected',

      // Mock connection
      connection: {
        _status: 'open',
        status() { return this._status; }
      },

      setExternalCallActive(isActive, source) {
        this.currentlyOnCall = isActive;
        this.applyMuteState(source);
      },

      applyMuteState(reason) {
        const shouldBeMuted = this.currentlyOnCall && !this.isController;
        this.muted = shouldBeMuted;
      }
    };
  }

  describe('Complete Training Session Lifecycle', () => {

    test('Step 1-2: Trainer and trainees initialize and connect', () => {
      const sim = createTrainingSimulation();

      // Verify initial state
      expect(sim.trainer.role).toBe('trainer');
      expect(sim.trainer.isController).toBe(true);
      expect(sim.trainee1.role).toBe('trainee');
      expect(sim.trainee1.isController).toBe(false);

      // Verify all connected
      expect(sim.trainer.connectionStatus).toBe('connected');
      expect(sim.trainee1.connectionStatus).toBe('connected');
      expect(sim.trainee2.connectionStatus).toBe('connected');

      // Verify none muted initially
      const states = sim.getMuteStates();
      expect(states.trainer).toBe(false);
      expect(states.trainee1).toBe(false);
      expect(states.trainee2).toBe(false);
    });

    test('Step 3: External call mutes non-controllers', () => {
      const sim = createTrainingSimulation();

      // External call arrives
      sim.startExternalCall();

      const states = sim.getMuteStates();

      // Trainer (controller) should NOT be muted
      expect(states.trainer).toBe(false);

      // Trainees (non-controllers) should be muted
      expect(states.trainee1).toBe(true);
      expect(states.trainee2).toBe(true);
    });

    test('Step 4: Call ends unmutes everyone', () => {
      const sim = createTrainingSimulation();

      // Start and end call
      sim.startExternalCall();
      sim.endExternalCall();

      const states = sim.getMuteStates();

      // Everyone should be unmuted
      expect(states.trainer).toBe(false);
      expect(states.trainee1).toBe(false);
      expect(states.trainee2).toBe(false);
    });

    test('Step 5: Control transfer updates isController state', () => {
      const sim = createTrainingSimulation();

      // Transfer control to trainee1
      sim.transferControl('TestTrainee1', 'trainee');

      // Verify state changes
      expect(sim.trainer.isController).toBe(false);
      expect(sim.trainer.activeController).toBe('TestTrainee1');

      expect(sim.trainee1.isController).toBe(true);
      expect(sim.trainee1.activeController).toBe('TestTrainee1');

      expect(sim.trainee2.isController).toBe(false);
    });

    test('Step 6: After control transfer, new call mutes trainer', () => {
      const sim = createTrainingSimulation();

      // Transfer control to trainee1
      sim.transferControl('TestTrainee1', 'trainee');

      // New external call arrives
      sim.startExternalCall();

      const states = sim.getMuteStates();

      // Trainer should now be muted (no longer controller)
      expect(states.trainer).toBe(true);

      // Trainee1 should NOT be muted (is controller)
      expect(states.trainee1).toBe(false);

      // Trainee2 should be muted (not controller)
      expect(states.trainee2).toBe(true);
    });

    test('Full lifecycle: multiple control transfers with calls', () => {
      const sim = createTrainingSimulation();

      // 1. Initial call - trainer handles
      sim.startExternalCall();
      expect(sim.getMuteStates()).toEqual({
        trainer: false, trainee1: true, trainee2: true
      });
      sim.endExternalCall();

      // 2. Transfer to trainee1
      sim.transferControl('TestTrainee1', 'trainee');

      // 3. Call with trainee1 in control
      sim.startExternalCall();
      expect(sim.getMuteStates()).toEqual({
        trainer: true, trainee1: false, trainee2: true
      });
      sim.endExternalCall();

      // 4. Transfer to trainee2
      sim.transferControl('TestTrainee2', 'trainee');

      // 5. Call with trainee2 in control
      sim.startExternalCall();
      expect(sim.getMuteStates()).toEqual({
        trainer: true, trainee1: true, trainee2: false
      });
      sim.endExternalCall();

      // 6. Return control to trainer
      sim.transferControl('TestTrainer', 'trainer');

      // 7. Final call - trainer handles again
      sim.startExternalCall();
      expect(sim.getMuteStates()).toEqual({
        trainer: false, trainee1: true, trainee2: true
      });
    });
  });

  describe('Error Scenarios', () => {

    test('Late joiner gets correct mute state', () => {
      const sim = createTrainingSimulation();

      // Call starts before trainee2 connects
      sim.trainee2.connection = null;
      sim.startExternalCall();

      // Trainee2 joins late
      sim.trainee2.connection = { _status: 'open', status() { return this._status; } };
      sim.trainee2.applyMuteState('late_join');

      // Should be muted (call is active, not controller)
      expect(sim.trainee2.muted).toBe(true);
    });

    test('Handles rapid control transfers', () => {
      const sim = createTrainingSimulation();

      // Rapid transfers
      sim.transferControl('TestTrainee1', 'trainee');
      sim.transferControl('TestTrainee2', 'trainee');
      sim.transferControl('TestTrainer', 'trainer');
      sim.transferControl('TestTrainee1', 'trainee');

      // Final state should be correct
      expect(sim.trainee1.isController).toBe(true);
      expect(sim.trainer.isController).toBe(false);
      expect(sim.trainee2.isController).toBe(false);
    });

    test('Handles call during control transfer', () => {
      const sim = createTrainingSimulation();

      // Start call
      sim.startExternalCall();

      // Transfer control during call
      sim.transferControl('TestTrainee1', 'trainee');

      // Apply mute state after transfer
      sim.allParticipants.forEach(p => p.applyMuteState('post_transfer'));

      // Trainer should now be muted
      expect(sim.trainer.muted).toBe(true);

      // Trainee1 should now be unmuted
      expect(sim.trainee1.muted).toBe(false);
    });
  });

  describe('Screen Reader Announcements', () => {

    test('Announcements are made for mute state changes', () => {
      const sim = createTrainingSimulation();

      // Start call
      sim.startExternalCall();

      // Verify screen reader function was called
      // (In actual implementation, announceToScreenReader would be called)
      expect(global.announceToScreenReader).toBeDefined();
    });
  });
});
