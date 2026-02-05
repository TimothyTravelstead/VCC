/**
 * Database Sync Integration Tests
 *
 * Tests three-table consistency:
 * - training_session_control
 * - CallControl
 * - Volunteers (OnCall status)
 *
 * Note: These tests simulate the database operations that would occur
 * through the PHP endpoints. In a full integration test environment,
 * these would make actual HTTP requests to the PHP endpoints.
 */

const { setupTrainingEndpointMocks, setFetchResponse, getFetchCallsMatching, clearFetchMocks } = require('../setup/mocks/fetchMock');

describe('Database Sync Integration', () => {

  beforeEach(() => {
    setupTrainingEndpointMocks();
  });

  afterEach(() => {
    clearFetchMocks();
  });

  /**
   * Simulates the database state
   */
  function createDatabaseSimulation() {
    return {
      // Tables
      volunteers: new Map(),
      training_session_control: new Map(),
      CallControl: new Map(),

      // Helper methods
      insertVolunteer(data) {
        this.volunteers.set(data.UserName, {
          UserName: data.UserName,
          trainer: data.trainer || 0,
          trainee: data.trainee || 0,
          LoggedOn: data.LoggedOn || 0,
          OnCall: data.OnCall || 0,
          TraineeID: data.TraineeID || null
        });
      },

      updateVolunteer(userName, updates) {
        const vol = this.volunteers.get(userName);
        if (vol) {
          Object.assign(vol, updates);
        }
      },

      getVolunteer(userName) {
        return this.volunteers.get(userName);
      },

      insertTrainingControl(trainerId, activeController, controllerRole) {
        this.training_session_control.set(trainerId, {
          trainer_id: trainerId,
          active_controller: activeController,
          controller_role: controllerRole,
          last_updated: new Date().toISOString()
        });
      },

      getTrainingControl(trainerId) {
        return this.training_session_control.get(trainerId);
      },

      insertCallControl(userId, loggedOnStatus) {
        this.CallControl.set(userId, {
          user_id: userId,
          logged_on_status: loggedOnStatus,
          can_receive_calls: 1,
          can_receive_chats: 1
        });
      },

      deleteCallControl(userId) {
        this.CallControl.delete(userId);
      },

      getCallControl(userId) {
        return this.CallControl.get(userId);
      },

      getAllCallControl() {
        return Array.from(this.CallControl.values());
      },

      /**
       * Simulate setTrainingControl.php logic
       */
      setTrainingControl(trainerId, activeController, controllerRole) {
        // Get trainer's trainees
        const trainer = this.getVolunteer(trainerId);
        if (!trainer) {
          throw new Error('Trainer not found');
        }

        const assignedTrainees = trainer.TraineeID
          ? trainer.TraineeID.split(',').map(t => t.trim())
          : [];

        // Validate trainee if controller role is trainee
        if (controllerRole === 'trainee') {
          if (!assignedTrainees.includes(activeController)) {
            throw new Error('Trainee not assigned to this trainer');
          }

          const trainee = this.getVolunteer(activeController);
          if (!trainee || trainee.LoggedOn !== 6) {
            throw new Error('Trainee not logged in');
          }
        }

        // 1. Update training_session_control
        this.insertTrainingControl(trainerId, activeController, controllerRole);

        // 2. Update CallControl - add new controller
        const controller = this.getVolunteer(activeController);
        this.insertCallControl(activeController, controller ? controller.LoggedOn : 4);

        // 3. Remove other participants from CallControl
        const allParticipants = [trainerId, ...assignedTrainees];
        allParticipants.forEach(p => {
          if (p !== activeController) {
            this.deleteCallControl(p);
          }
        });

        return { success: true };
      },

      /**
       * Simulate answerCall.php logic - sync OnCall status
       */
      answerCall(trainerId) {
        const trainer = this.getVolunteer(trainerId);
        if (!trainer) return;

        // Set OnCall for trainer
        this.updateVolunteer(trainerId, { OnCall: 1 });

        // Set OnCall for all assigned trainees
        const trainees = trainer.TraineeID
          ? trainer.TraineeID.split(',').map(t => t.trim())
          : [];

        trainees.forEach(traineeId => {
          this.updateVolunteer(traineeId, { OnCall: 1 });
        });
      },

      /**
       * Simulate call end - clear OnCall status
       */
      endCall(trainerId) {
        const trainer = this.getVolunteer(trainerId);
        if (!trainer) return;

        // Clear OnCall for trainer
        this.updateVolunteer(trainerId, { OnCall: 0 });

        // Clear OnCall for all assigned trainees
        const trainees = trainer.TraineeID
          ? trainer.TraineeID.split(',').map(t => t.trim())
          : [];

        trainees.forEach(traineeId => {
          this.updateVolunteer(traineeId, { OnCall: 0 });
        });
      },

      /**
       * Simulate trainee logout cleanup
       */
      traineeLogout(traineeId, trainerId) {
        // 1. If trainee had control, return to trainer
        const control = this.getTrainingControl(trainerId);
        if (control && control.active_controller === traineeId) {
          this.setTrainingControl(trainerId, trainerId, 'trainer');
        }

        // 2. Remove trainee from CallControl
        this.deleteCallControl(traineeId);

        // 3. Update volunteer status
        this.updateVolunteer(traineeId, { LoggedOn: 0, OnCall: 0 });

        // 4. Remove from trainer's TraineeID list
        const trainer = this.getVolunteer(trainerId);
        if (trainer && trainer.TraineeID) {
          const trainees = trainer.TraineeID.split(',').map(t => t.trim());
          const remaining = trainees.filter(t => t !== traineeId);
          this.updateVolunteer(trainerId, {
            TraineeID: remaining.length > 0 ? remaining.join(',') : null
          });
        }
      },

      /**
       * Simulate trainer logout cleanup
       */
      trainerLogout(trainerId) {
        // 1. Delete training_session_control record
        this.training_session_control.delete(trainerId);

        // 2. Remove trainer from CallControl
        this.deleteCallControl(trainerId);

        // 3. Clear all trainees' OnCall status
        const trainer = this.getVolunteer(trainerId);
        if (trainer && trainer.TraineeID) {
          const trainees = trainer.TraineeID.split(',').map(t => t.trim());
          trainees.forEach(traineeId => {
            this.updateVolunteer(traineeId, { OnCall: 0 });
            this.deleteCallControl(traineeId);
          });
        }

        // 4. Update trainer status
        this.updateVolunteer(trainerId, { LoggedOn: 0, OnCall: 0, TraineeID: null });
      }
    };
  }

  describe('Control Transfer Three-Table Sync', () => {

    test('All three tables updated on control transfer', () => {
      const db = createDatabaseSimulation();

      // Setup
      db.insertVolunteer({
        UserName: 'TestTrainer',
        trainer: 1,
        LoggedOn: 4,
        TraineeID: 'TestTrainee1,TestTrainee2'
      });
      db.insertVolunteer({ UserName: 'TestTrainee1', trainee: 1, LoggedOn: 6 });
      db.insertVolunteer({ UserName: 'TestTrainee2', trainee: 1, LoggedOn: 6 });
      db.insertTrainingControl('TestTrainer', 'TestTrainer', 'trainer');
      db.insertCallControl('TestTrainer', 4);

      // Transfer control
      db.setTrainingControl('TestTrainer', 'TestTrainee1', 'trainee');

      // Verify training_session_control
      const control = db.getTrainingControl('TestTrainer');
      expect(control.active_controller).toBe('TestTrainee1');
      expect(control.controller_role).toBe('trainee');

      // Verify CallControl
      expect(db.getCallControl('TestTrainee1')).toBeDefined();
      expect(db.getCallControl('TestTrainer')).toBeUndefined();
      expect(db.getCallControl('TestTrainee2')).toBeUndefined();
    });

    test('CallControl has correct logged_on_status for controller', () => {
      const db = createDatabaseSimulation();

      db.insertVolunteer({
        UserName: 'TestTrainer',
        trainer: 1,
        LoggedOn: 4,
        TraineeID: 'TestTrainee1'
      });
      db.insertVolunteer({ UserName: 'TestTrainee1', trainee: 1, LoggedOn: 6 });
      db.insertTrainingControl('TestTrainer', 'TestTrainer', 'trainer');

      // Transfer to trainee
      db.setTrainingControl('TestTrainer', 'TestTrainee1', 'trainee');

      const callControl = db.getCallControl('TestTrainee1');
      expect(callControl.logged_on_status).toBe(6); // Trainee status
    });
  });

  describe('OnCall Status Sync', () => {

    test('answerCall sets OnCall for all training participants', () => {
      const db = createDatabaseSimulation();

      db.insertVolunteer({
        UserName: 'TestTrainer',
        trainer: 1,
        LoggedOn: 4,
        OnCall: 0,
        TraineeID: 'TestTrainee1,TestTrainee2'
      });
      db.insertVolunteer({ UserName: 'TestTrainee1', trainee: 1, LoggedOn: 6, OnCall: 0 });
      db.insertVolunteer({ UserName: 'TestTrainee2', trainee: 1, LoggedOn: 6, OnCall: 0 });

      // Answer call
      db.answerCall('TestTrainer');

      // All should have OnCall = 1
      expect(db.getVolunteer('TestTrainer').OnCall).toBe(1);
      expect(db.getVolunteer('TestTrainee1').OnCall).toBe(1);
      expect(db.getVolunteer('TestTrainee2').OnCall).toBe(1);
    });

    test('endCall clears OnCall for all training participants', () => {
      const db = createDatabaseSimulation();

      db.insertVolunteer({
        UserName: 'TestTrainer',
        trainer: 1,
        LoggedOn: 4,
        OnCall: 1,
        TraineeID: 'TestTrainee1'
      });
      db.insertVolunteer({ UserName: 'TestTrainee1', trainee: 1, LoggedOn: 6, OnCall: 1 });

      // End call
      db.endCall('TestTrainer');

      expect(db.getVolunteer('TestTrainer').OnCall).toBe(0);
      expect(db.getVolunteer('TestTrainee1').OnCall).toBe(0);
    });
  });

  describe('Trainee Logout Cleanup', () => {

    test('Trainee logout returns control to trainer if trainee had control', () => {
      const db = createDatabaseSimulation();

      db.insertVolunteer({
        UserName: 'TestTrainer',
        trainer: 1,
        LoggedOn: 4,
        TraineeID: 'TestTrainee1'
      });
      db.insertVolunteer({ UserName: 'TestTrainee1', trainee: 1, LoggedOn: 6 });
      db.insertTrainingControl('TestTrainer', 'TestTrainee1', 'trainee');
      db.insertCallControl('TestTrainee1', 6);

      // Trainee logs out
      db.traineeLogout('TestTrainee1', 'TestTrainer');

      // Control should be back to trainer
      const control = db.getTrainingControl('TestTrainer');
      expect(control.active_controller).toBe('TestTrainer');
      expect(control.controller_role).toBe('trainer');
    });

    test('Trainee logout removes from CallControl', () => {
      const db = createDatabaseSimulation();

      db.insertVolunteer({
        UserName: 'TestTrainer',
        trainer: 1,
        LoggedOn: 4,
        TraineeID: 'TestTrainee1'
      });
      db.insertVolunteer({ UserName: 'TestTrainee1', trainee: 1, LoggedOn: 6 });
      db.insertCallControl('TestTrainee1', 6);

      db.traineeLogout('TestTrainee1', 'TestTrainer');

      expect(db.getCallControl('TestTrainee1')).toBeUndefined();
    });

    test('Trainee logout clears OnCall and LoggedOn', () => {
      const db = createDatabaseSimulation();

      db.insertVolunteer({
        UserName: 'TestTrainer',
        trainer: 1,
        LoggedOn: 4,
        TraineeID: 'TestTrainee1'
      });
      db.insertVolunteer({ UserName: 'TestTrainee1', trainee: 1, LoggedOn: 6, OnCall: 1 });

      db.traineeLogout('TestTrainee1', 'TestTrainer');

      const trainee = db.getVolunteer('TestTrainee1');
      expect(trainee.LoggedOn).toBe(0);
      expect(trainee.OnCall).toBe(0);
    });
  });

  describe('Trainer Logout Cleanup', () => {

    test('Trainer logout deletes training_session_control record', () => {
      const db = createDatabaseSimulation();

      db.insertVolunteer({
        UserName: 'TestTrainer',
        trainer: 1,
        LoggedOn: 4,
        TraineeID: 'TestTrainee1'
      });
      db.insertVolunteer({ UserName: 'TestTrainee1', trainee: 1, LoggedOn: 6 });
      db.insertTrainingControl('TestTrainer', 'TestTrainer', 'trainer');

      db.trainerLogout('TestTrainer');

      expect(db.getTrainingControl('TestTrainer')).toBeUndefined();
    });

    test('Trainer logout clears all trainees OnCall', () => {
      const db = createDatabaseSimulation();

      db.insertVolunteer({
        UserName: 'TestTrainer',
        trainer: 1,
        LoggedOn: 4,
        OnCall: 1,
        TraineeID: 'TestTrainee1,TestTrainee2'
      });
      db.insertVolunteer({ UserName: 'TestTrainee1', trainee: 1, LoggedOn: 6, OnCall: 1 });
      db.insertVolunteer({ UserName: 'TestTrainee2', trainee: 1, LoggedOn: 6, OnCall: 1 });

      db.trainerLogout('TestTrainer');

      expect(db.getVolunteer('TestTrainee1').OnCall).toBe(0);
      expect(db.getVolunteer('TestTrainee2').OnCall).toBe(0);
    });

    test('Trainer logout removes all participants from CallControl', () => {
      const db = createDatabaseSimulation();

      db.insertVolunteer({
        UserName: 'TestTrainer',
        trainer: 1,
        LoggedOn: 4,
        TraineeID: 'TestTrainee1'
      });
      db.insertVolunteer({ UserName: 'TestTrainee1', trainee: 1, LoggedOn: 6 });
      db.insertCallControl('TestTrainer', 4);
      db.insertCallControl('TestTrainee1', 6);

      db.trainerLogout('TestTrainer');

      expect(db.getCallControl('TestTrainer')).toBeUndefined();
      expect(db.getCallControl('TestTrainee1')).toBeUndefined();
    });
  });
});
