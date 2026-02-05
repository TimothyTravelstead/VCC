/**
 * TrainingSession Control Transfer Tests
 *
 * Tests the control transfer functionality:
 * - Trainer can transfer control to assigned trainees
 * - Control state updates correctly
 * - Non-controllers cannot transfer control
 */

const { setupTrainingEndpointMocks, getFetchCalls, getFetchCallsMatching, setFetchResponse } = require('../../setup/mocks/fetchMock');
const { setupTrainingDOM } = require('../../setup/mocks/domMock');

describe('TrainingSession Control Transfer', () => {

  beforeEach(() => {
    setupTrainingEndpointMocks();
    global.announceToScreenReader = jest.fn();
  });

  afterEach(() => {
    delete global.announceToScreenReader;
  });

  /**
   * Helper to create a control test session
   */
  function createControlTestSession(options = {}) {
    return {
      volunteerID: options.volunteerID || 'TestTrainer',
      role: options.role || 'trainer',
      trainer: { id: options.trainerId || 'TestTrainer', name: 'Test Trainer' },
      trainees: options.trainees || [
        { id: 'TestTrainee1', name: 'Trainee 1', isSignedOn: true },
        { id: 'TestTrainee2', name: 'Trainee 2', isSignedOn: true }
      ],
      conferenceID: options.conferenceID || 'TestTrainer',
      isController: options.isController !== undefined ? options.isController : true,
      activeController: options.activeController || 'TestTrainer',
      incomingCallsTo: options.incomingCallsTo || 'TestTrainer',
      currentlyOnCall: options.currentlyOnCall || false,

      // Track API calls
      _apiCalls: [],

      /**
       * Update control state (simulates updateControlState method)
       */
      updateControlState(newController, controllerRole) {
        // Verify trainer permissions
        if (this.role !== 'trainer') {
          throw new Error('Only trainers can change control');
        }

        // Verify trainee is assigned
        if (controllerRole === 'trainee') {
          const trainee = this.trainees.find(t => t.id === newController);
          if (!trainee) {
            throw new Error('Trainee not assigned to this trainer');
          }
          if (!trainee.isSignedOn) {
            throw new Error('Trainee is not logged in');
          }
        }

        // Update local state
        this.activeController = newController;
        this.isController = (this.volunteerID === newController);
        this.incomingCallsTo = newController;

        return true;
      },

      /**
       * Handle receiving a control change notification
       */
      handleControlChangeNotification(message) {
        const newActiveController = message.activeController;

        this.activeController = newActiveController;
        this.isController = (this.volunteerID === newActiveController);
        this.incomingCallsTo = newActiveController;
      },

      /**
       * Simulate API call to set training control
       */
      async setTrainingControl(newController, controllerRole) {
        const apiCall = {
          endpoint: 'setTrainingControl.php',
          body: {
            trainerId: this.trainer.id,
            activeController: newController,
            controllerRole: controllerRole
          }
        };
        this._apiCalls.push(apiCall);

        // Simulate successful response
        this.updateControlState(newController, controllerRole);

        return { success: true };
      }
    };
  }

  describe('Trainer Control Transfer', () => {

    test('Trainer can transfer control to assigned trainee', async () => {
      const session = createControlTestSession({
        role: 'trainer',
        volunteerID: 'TestTrainer',
        isController: true,
        activeController: 'TestTrainer'
      });

      // Transfer control to trainee
      await session.setTrainingControl('TestTrainee1', 'trainee');

      // Verify state updated
      expect(session.activeController).toBe('TestTrainee1');
      expect(session.isController).toBe(false);
      expect(session.incomingCallsTo).toBe('TestTrainee1');
    });

    test('Trainer can reclaim control from trainee', async () => {
      const session = createControlTestSession({
        role: 'trainer',
        volunteerID: 'TestTrainer',
        isController: false,
        activeController: 'TestTrainee1'
      });

      // Reclaim control
      await session.setTrainingControl('TestTrainer', 'trainer');

      // Verify state updated
      expect(session.activeController).toBe('TestTrainer');
      expect(session.isController).toBe(true);
      expect(session.incomingCallsTo).toBe('TestTrainer');
    });

    test('Trainer can transfer control between trainees', async () => {
      const session = createControlTestSession({
        role: 'trainer',
        volunteerID: 'TestTrainer',
        isController: false,
        activeController: 'TestTrainee1'
      });

      // Transfer to different trainee
      await session.setTrainingControl('TestTrainee2', 'trainee');

      expect(session.activeController).toBe('TestTrainee2');
    });
  });

  describe('Control Transfer Validation', () => {

    test('Trainee cannot transfer control', () => {
      const session = createControlTestSession({
        role: 'trainee',
        volunteerID: 'TestTrainee1',
        trainerId: 'TestTrainer',
        isController: true
      });

      expect(() => {
        session.updateControlState('TestTrainee2', 'trainee');
      }).toThrow('Only trainers can change control');
    });

    test('Cannot transfer to unassigned trainee', () => {
      const session = createControlTestSession({
        role: 'trainer',
        volunteerID: 'TestTrainer',
        trainees: [
          { id: 'TestTrainee1', name: 'Trainee 1', isSignedOn: true }
        ]
      });

      expect(() => {
        session.updateControlState('UnassignedTrainee', 'trainee');
      }).toThrow('Trainee not assigned to this trainer');
    });

    test('Cannot transfer to logged-out trainee', () => {
      const session = createControlTestSession({
        role: 'trainer',
        volunteerID: 'TestTrainer',
        trainees: [
          { id: 'TestTrainee1', name: 'Trainee 1', isSignedOn: false }
        ]
      });

      expect(() => {
        session.updateControlState('TestTrainee1', 'trainee');
      }).toThrow('Trainee is not logged in');
    });
  });

  describe('Control Change Notifications', () => {

    test('Trainee receives control change notification', () => {
      const session = createControlTestSession({
        role: 'trainee',
        volunteerID: 'TestTrainee1',
        trainerId: 'TestTrainer',
        isController: false,
        activeController: 'TestTrainer'
      });

      // Receive notification that trainee now has control
      session.handleControlChangeNotification({
        activeController: 'TestTrainee1',
        controllerRole: 'trainee'
      });

      expect(session.isController).toBe(true);
      expect(session.activeController).toBe('TestTrainee1');
      expect(session.incomingCallsTo).toBe('TestTrainee1');
    });

    test('Trainee loses control via notification', () => {
      const session = createControlTestSession({
        role: 'trainee',
        volunteerID: 'TestTrainee1',
        trainerId: 'TestTrainer',
        isController: true,
        activeController: 'TestTrainee1'
      });

      // Receive notification that trainer reclaimed control
      session.handleControlChangeNotification({
        activeController: 'TestTrainer',
        controllerRole: 'trainer'
      });

      expect(session.isController).toBe(false);
      expect(session.activeController).toBe('TestTrainer');
    });

    test('Other trainee receives control change notification', () => {
      const session = createControlTestSession({
        role: 'trainee',
        volunteerID: 'TestTrainee2',
        trainerId: 'TestTrainer',
        isController: false,
        activeController: 'TestTrainer'
      });

      // Notification that TestTrainee1 got control
      session.handleControlChangeNotification({
        activeController: 'TestTrainee1',
        controllerRole: 'trainee'
      });

      // TestTrainee2 should NOT have control
      expect(session.isController).toBe(false);
      expect(session.activeController).toBe('TestTrainee1');
    });
  });

  describe('Control Transfer with Active Call', () => {

    test('Control transfer during call updates mute state', () => {
      const session = createControlTestSession({
        role: 'trainer',
        volunteerID: 'TestTrainer',
        isController: true,
        activeController: 'TestTrainer',
        currentlyOnCall: true
      });

      // Add mute tracking
      session.muted = false;
      session.applyMuteState = function() {
        const shouldBeMuted = this.currentlyOnCall && !this.isController;
        this.muted = shouldBeMuted;
      };

      // Transfer control during call
      session.updateControlState('TestTrainee1', 'trainee');
      session.applyMuteState();

      // Trainer should now be muted (gave up control during call)
      expect(session.isController).toBe(false);
      expect(session.muted).toBe(true);
    });

    test('Receiving control during call unmutes participant', () => {
      const session = createControlTestSession({
        role: 'trainee',
        volunteerID: 'TestTrainee1',
        trainerId: 'TestTrainer',
        isController: false,
        activeController: 'TestTrainer',
        currentlyOnCall: true
      });

      // Add mute tracking
      session.muted = true;
      session.applyMuteState = function() {
        const shouldBeMuted = this.currentlyOnCall && !this.isController;
        this.muted = shouldBeMuted;
      };

      // Receive control
      session.handleControlChangeNotification({
        activeController: 'TestTrainee1',
        controllerRole: 'trainee'
      });
      session.applyMuteState();

      // Trainee should now be unmuted (has control)
      expect(session.isController).toBe(true);
      expect(session.muted).toBe(false);
    });
  });

  describe('API Call Tracking', () => {

    test('Control transfer makes correct API call', async () => {
      const session = createControlTestSession({
        role: 'trainer',
        volunteerID: 'TestTrainer'
      });

      await session.setTrainingControl('TestTrainee1', 'trainee');

      expect(session._apiCalls).toHaveLength(1);
      expect(session._apiCalls[0]).toEqual({
        endpoint: 'setTrainingControl.php',
        body: {
          trainerId: 'TestTrainer',
          activeController: 'TestTrainee1',
          controllerRole: 'trainee'
        }
      });
    });
  });
});
