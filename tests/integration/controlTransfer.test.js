/**
 * Control Transfer Integration Tests
 *
 * Tests control transfer scenarios:
 * - Trainer to trainee
 * - Trainee to trainer
 * - Between trainees
 * - Invalid transfers
 */

const { setupTrainingEndpointMocks, setFetchResponse, getFetchCallsMatching, clearFetchMocks } = require('../setup/mocks/fetchMock');

describe('Control Transfer Integration', () => {

  beforeEach(() => {
    setupTrainingEndpointMocks();
  });

  afterEach(() => {
    clearFetchMocks();
  });

  /**
   * Create a control transfer test environment
   */
  function createControlTransferEnv() {
    // Shared state across all participants
    const sharedState = {
      activeController: 'TestTrainer',
      controllerRole: 'trainer',
      currentlyOnCall: false
    };

    // Create participant
    function createParticipant(options) {
      return {
        volunteerID: options.volunteerID,
        role: options.role,
        trainer: { id: 'TestTrainer' },
        trainees: options.trainees || [],
        isController: options.volunteerID === sharedState.activeController,
        muted: false,
        sharedState,

        // Methods
        get activeController() {
          return this.sharedState.activeController;
        },

        updateFromSharedState() {
          this.isController = this.volunteerID === this.sharedState.activeController;
          this.applyMuteState();
        },

        applyMuteState() {
          this.muted = this.sharedState.currentlyOnCall && !this.isController;
        },

        // Trainer-only method
        async transferControl(newControllerId, controllerRole) {
          if (this.role !== 'trainer') {
            throw new Error('Only trainers can transfer control');
          }

          // Update shared state
          this.sharedState.activeController = newControllerId;
          this.sharedState.controllerRole = controllerRole;

          return { success: true };
        }
      };
    }

    const trainer = createParticipant({
      volunteerID: 'TestTrainer',
      role: 'trainer',
      trainees: [
        { id: 'TestTrainee1', isSignedOn: true },
        { id: 'TestTrainee2', isSignedOn: true }
      ]
    });

    const trainee1 = createParticipant({
      volunteerID: 'TestTrainee1',
      role: 'trainee'
    });

    const trainee2 = createParticipant({
      volunteerID: 'TestTrainee2',
      role: 'trainee'
    });

    return {
      trainer,
      trainee1,
      trainee2,
      sharedState,
      allParticipants: [trainer, trainee1, trainee2],

      updateAllParticipants() {
        this.allParticipants.forEach(p => p.updateFromSharedState());
      },

      startCall() {
        this.sharedState.currentlyOnCall = true;
        this.allParticipants.forEach(p => p.applyMuteState());
      },

      endCall() {
        this.sharedState.currentlyOnCall = false;
        this.allParticipants.forEach(p => p.applyMuteState());
      }
    };
  }

  describe('Trainer to Trainee Transfer', () => {

    test('Trainer can transfer control to trainee', async () => {
      const env = createControlTransferEnv();

      // Initial state
      expect(env.trainer.isController).toBe(true);
      expect(env.trainee1.isController).toBe(false);

      // Transfer control
      const result = await env.trainer.transferControl('TestTrainee1', 'trainee');
      env.updateAllParticipants();

      // Verify transfer
      expect(result.success).toBe(true);
      expect(env.sharedState.activeController).toBe('TestTrainee1');
      expect(env.trainer.isController).toBe(false);
      expect(env.trainee1.isController).toBe(true);
    });

    test('After transfer, trainee handles calls unmuted', async () => {
      const env = createControlTransferEnv();

      // Transfer control
      await env.trainer.transferControl('TestTrainee1', 'trainee');
      env.updateAllParticipants();

      // Start call
      env.startCall();

      // Trainee1 should be unmuted (is controller)
      expect(env.trainee1.muted).toBe(false);

      // Trainer should be muted (not controller)
      expect(env.trainer.muted).toBe(true);
    });
  });

  describe('Trainee to Trainer Transfer', () => {

    test('Trainer can reclaim control from trainee', async () => {
      const env = createControlTransferEnv();

      // First transfer to trainee
      await env.trainer.transferControl('TestTrainee1', 'trainee');
      env.updateAllParticipants();

      expect(env.trainee1.isController).toBe(true);

      // Reclaim control
      await env.trainer.transferControl('TestTrainer', 'trainer');
      env.updateAllParticipants();

      expect(env.trainer.isController).toBe(true);
      expect(env.trainee1.isController).toBe(false);
    });

    test('Trainee cannot transfer control', async () => {
      const env = createControlTransferEnv();

      // Transfer to trainee first
      await env.trainer.transferControl('TestTrainee1', 'trainee');
      env.updateAllParticipants();

      // Trainee tries to transfer
      await expect(
        env.trainee1.transferControl('TestTrainee2', 'trainee')
      ).rejects.toThrow('Only trainers can transfer control');
    });
  });

  describe('Between Trainees Transfer', () => {

    test('Trainer can transfer from one trainee to another', async () => {
      const env = createControlTransferEnv();

      // Transfer to trainee1
      await env.trainer.transferControl('TestTrainee1', 'trainee');
      env.updateAllParticipants();

      expect(env.trainee1.isController).toBe(true);
      expect(env.trainee2.isController).toBe(false);

      // Transfer to trainee2
      await env.trainer.transferControl('TestTrainee2', 'trainee');
      env.updateAllParticipants();

      expect(env.trainee1.isController).toBe(false);
      expect(env.trainee2.isController).toBe(true);
    });

    test('Mute state updates correctly between trainee transfers', async () => {
      const env = createControlTransferEnv();

      // Transfer to trainee1
      await env.trainer.transferControl('TestTrainee1', 'trainee');
      env.updateAllParticipants();

      // Start call
      env.startCall();

      expect(env.trainee1.muted).toBe(false);
      expect(env.trainee2.muted).toBe(true);

      // Transfer to trainee2 during call
      await env.trainer.transferControl('TestTrainee2', 'trainee');
      env.updateAllParticipants();
      env.allParticipants.forEach(p => p.applyMuteState());

      expect(env.trainee1.muted).toBe(true);
      expect(env.trainee2.muted).toBe(false);
    });
  });

  describe('Control Transfer with Active Call', () => {

    test('Transfer during call updates mute state immediately', async () => {
      const env = createControlTransferEnv();

      // Start call
      env.startCall();

      // Trainer unmuted, trainees muted
      expect(env.trainer.muted).toBe(false);
      expect(env.trainee1.muted).toBe(true);

      // Transfer during call
      await env.trainer.transferControl('TestTrainee1', 'trainee');
      env.updateAllParticipants();
      env.allParticipants.forEach(p => p.applyMuteState());

      // States should flip
      expect(env.trainer.muted).toBe(true);
      expect(env.trainee1.muted).toBe(false);
    });

    test('Multiple transfers during call', async () => {
      const env = createControlTransferEnv();
      env.startCall();

      // Transfer through all participants
      await env.trainer.transferControl('TestTrainee1', 'trainee');
      env.updateAllParticipants();
      env.allParticipants.forEach(p => p.applyMuteState());

      expect(env.trainee1.muted).toBe(false);

      await env.trainer.transferControl('TestTrainee2', 'trainee');
      env.updateAllParticipants();
      env.allParticipants.forEach(p => p.applyMuteState());

      expect(env.trainee2.muted).toBe(false);
      expect(env.trainee1.muted).toBe(true);

      await env.trainer.transferControl('TestTrainer', 'trainer');
      env.updateAllParticipants();
      env.allParticipants.forEach(p => p.applyMuteState());

      expect(env.trainer.muted).toBe(false);
      expect(env.trainee1.muted).toBe(true);
      expect(env.trainee2.muted).toBe(true);
    });
  });

  describe('Control State Consistency', () => {

    test('All participants agree on who has control', async () => {
      const env = createControlTransferEnv();

      // Initial state
      expect(env.trainer.activeController).toBe('TestTrainer');
      expect(env.trainee1.activeController).toBe('TestTrainer');
      expect(env.trainee2.activeController).toBe('TestTrainer');

      // Transfer
      await env.trainer.transferControl('TestTrainee1', 'trainee');
      env.updateAllParticipants();

      // All should agree
      expect(env.trainer.activeController).toBe('TestTrainee1');
      expect(env.trainee1.activeController).toBe('TestTrainee1');
      expect(env.trainee2.activeController).toBe('TestTrainee1');
    });

    test('Only one participant has isController=true', async () => {
      const env = createControlTransferEnv();

      function countControllers() {
        return env.allParticipants.filter(p => p.isController).length;
      }

      expect(countControllers()).toBe(1);

      await env.trainer.transferControl('TestTrainee1', 'trainee');
      env.updateAllParticipants();

      expect(countControllers()).toBe(1);

      await env.trainer.transferControl('TestTrainee2', 'trainee');
      env.updateAllParticipants();

      expect(countControllers()).toBe(1);
    });
  });
});
