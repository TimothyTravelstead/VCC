<?php
/**
 * Tests for setTrainingControl.php
 *
 * Tests:
 * - Trainer can set control to assigned trainee
 * - Non-trainer cannot set control
 * - Cannot transfer to unassigned trainee
 * - Three-table sync verification
 */

use PHPUnit\Framework\TestCase;

class SetTrainingControlTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset test database before each test
        TestDatabase::reset();

        // Insert standard test data
        $this->setupStandardTestData();
    }

    protected function setupStandardTestData(): void
    {
        // Insert trainer
        insertTestVolunteer([
            'UserName' => 'TestTrainer',
            'FullName' => 'Test Trainer',
            'trainer' => 1,
            'LoggedOn' => 4,
            'TraineeID' => 'TestTrainee1,TestTrainee2'
        ]);

        // Insert trainees
        insertTestVolunteer([
            'UserName' => 'TestTrainee1',
            'FullName' => 'Test Trainee 1',
            'trainee' => 1,
            'LoggedOn' => 6
        ]);

        insertTestVolunteer([
            'UserName' => 'TestTrainee2',
            'FullName' => 'Test Trainee 2',
            'trainee' => 1,
            'LoggedOn' => 6
        ]);

        // Insert initial control (trainer has control)
        insertTestTrainingControl('TestTrainer', 'TestTrainer', 'trainer');
    }

    /**
     * Simulate the setTrainingControl.php logic
     */
    protected function simulateSetTrainingControl(
        string $trainerId,
        string $activeController,
        string $controllerRole,
        ?string $sessionTrainer = null
    ): array {
        $sessionTrainer = $sessionTrainer ?? $trainerId;

        // Verify trainer permissions
        if ($sessionTrainer !== $trainerId) {
            return ['error' => 'You can only change control for your own training session', 'code' => 403];
        }

        // Validate controller role
        if (!in_array($controllerRole, ['trainer', 'trainee'])) {
            return ['error' => 'Controller role must be trainer or trainee', 'code' => 400];
        }

        // Get trainer's trainees
        $pdo = TestDatabase::getPDO();
        $stmt = $pdo->prepare("SELECT TraineeID FROM volunteers WHERE UserName = ?");
        $stmt->execute([$trainerId]);
        $trainer = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$trainer) {
            return ['error' => 'Trainer not found', 'code' => 404];
        }

        // Validate trainee is assigned
        if ($controllerRole === 'trainee') {
            $assignedTrainees = array_map('trim', explode(',', $trainer->TraineeID ?? ''));

            if (!in_array($activeController, $assignedTrainees)) {
                return ['error' => 'Invalid trainee: not assigned to this trainer', 'code' => 400];
            }

            // Check trainee is logged in
            $stmt = $pdo->prepare("SELECT LoggedOn FROM volunteers WHERE UserName = ?");
            $stmt->execute([$activeController]);
            $trainee = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$trainee || $trainee->LoggedOn != 6) {
                return ['error' => 'Invalid trainee: not logged in as trainee', 'code' => 400];
            }
        }

        // Update training_session_control
        $stmt = $pdo->prepare("
            INSERT INTO training_session_control (trainer_id, active_controller, controller_role)
            VALUES (?, ?, ?)
            ON CONFLICT(trainer_id) DO UPDATE SET
            active_controller = excluded.active_controller,
            controller_role = excluded.controller_role
        ");
        $stmt->execute([$trainerId, $activeController, $controllerRole]);

        // Get controller's LoggedOn status
        $stmt = $pdo->prepare("SELECT LoggedOn FROM volunteers WHERE UserName = ?");
        $stmt->execute([$activeController]);
        $controllerInfo = $stmt->fetch(PDO::FETCH_OBJ);
        $controllerStatus = $controllerInfo ? $controllerInfo->LoggedOn : 4;

        // Update CallControl - add new controller
        $stmt = $pdo->prepare("
            INSERT INTO CallControl (user_id, logged_on_status, can_receive_calls, can_receive_chats)
            VALUES (?, ?, 1, 1)
            ON CONFLICT(user_id) DO UPDATE SET
            logged_on_status = excluded.logged_on_status,
            can_receive_calls = 1,
            can_receive_chats = 1
        ");
        $stmt->execute([$activeController, $controllerStatus]);

        // Remove other participants from CallControl
        $participants = array_merge([$trainerId], $assignedTrainees ?? []);
        $otherParticipants = array_filter($participants, fn($p) => $p !== $activeController && !empty($p));

        if (!empty($otherParticipants)) {
            $placeholders = implode(',', array_fill(0, count($otherParticipants), '?'));
            $stmt = $pdo->prepare("DELETE FROM CallControl WHERE user_id IN ($placeholders)");
            $stmt->execute(array_values($otherParticipants));
        }

        return [
            'success' => true,
            'trainerId' => $trainerId,
            'activeController' => $activeController,
            'controllerRole' => $controllerRole
        ];
    }

    /**
     * Test trainer can transfer control to assigned trainee
     */
    public function testTrainerCanTransferControlToAssignedTrainee(): void
    {
        $result = $this->simulateSetTrainingControl('TestTrainer', 'TestTrainee1', 'trainee');

        $this->assertTrue($result['success']);
        $this->assertEquals('TestTrainee1', $result['activeController']);
        $this->assertEquals('trainee', $result['controllerRole']);

        // Verify database state
        $control = getTestTrainingControl('TestTrainer');
        $this->assertEquals('TestTrainee1', $control->active_controller);
        $this->assertEquals('trainee', $control->controller_role);
    }

    /**
     * Test trainer can reclaim control
     */
    public function testTrainerCanReclaimControl(): void
    {
        // First transfer to trainee
        $this->simulateSetTrainingControl('TestTrainer', 'TestTrainee1', 'trainee');

        // Then reclaim
        $result = $this->simulateSetTrainingControl('TestTrainer', 'TestTrainer', 'trainer');

        $this->assertTrue($result['success']);
        $this->assertEquals('TestTrainer', $result['activeController']);
        $this->assertEquals('trainer', $result['controllerRole']);
    }

    /**
     * Test cannot transfer to unassigned trainee
     */
    public function testCannotTransferToUnassignedTrainee(): void
    {
        // Insert unassigned trainee
        insertTestVolunteer([
            'UserName' => 'UnassignedTrainee',
            'trainee' => 1,
            'LoggedOn' => 6
        ]);

        $result = $this->simulateSetTrainingControl('TestTrainer', 'UnassignedTrainee', 'trainee');

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(400, $result['code']);
        $this->assertStringContainsString('not assigned', $result['error']);
    }

    /**
     * Test cannot transfer to logged-out trainee
     */
    public function testCannotTransferToLoggedOutTrainee(): void
    {
        // Update trainee to logged out
        $pdo = TestDatabase::getPDO();
        $pdo->exec("UPDATE volunteers SET LoggedOn = 0 WHERE UserName = 'TestTrainee1'");

        $result = $this->simulateSetTrainingControl('TestTrainer', 'TestTrainee1', 'trainee');

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(400, $result['code']);
        $this->assertStringContainsString('not logged in', $result['error']);
    }

    /**
     * Test cannot transfer to another trainer's session
     */
    public function testCannotTransferToAnotherTrainersSession(): void
    {
        // Insert another trainer
        insertTestVolunteer([
            'UserName' => 'OtherTrainer',
            'trainer' => 1,
            'LoggedOn' => 4
        ]);

        $result = $this->simulateSetTrainingControl(
            'OtherTrainer',
            'TestTrainee1',
            'trainee',
            'TestTrainer' // Session trainer is different
        );

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(403, $result['code']);
    }

    /**
     * Test three-table sync: training_session_control updated
     */
    public function testThreeTableSyncTrainingSessionControl(): void
    {
        $this->simulateSetTrainingControl('TestTrainer', 'TestTrainee1', 'trainee');

        $control = getTestTrainingControl('TestTrainer');

        $this->assertEquals('TestTrainee1', $control->active_controller);
        $this->assertEquals('trainee', $control->controller_role);
    }

    /**
     * Test three-table sync: CallControl updated for new controller
     */
    public function testThreeTableSyncCallControlNewController(): void
    {
        $this->simulateSetTrainingControl('TestTrainer', 'TestTrainee1', 'trainee');

        $callControl = getTestCallControl('TestTrainee1');

        $this->assertNotFalse($callControl);
        $this->assertEquals(1, $callControl->can_receive_calls);
        $this->assertEquals(1, $callControl->can_receive_chats);
        $this->assertEquals(6, $callControl->logged_on_status); // Trainee status
    }

    /**
     * Test three-table sync: CallControl removes old controller
     */
    public function testThreeTableSyncCallControlRemovesOldController(): void
    {
        // First add trainer to CallControl
        $pdo = TestDatabase::getPDO();
        $pdo->exec("
            INSERT INTO CallControl (user_id, logged_on_status, can_receive_calls, can_receive_chats)
            VALUES ('TestTrainer', 4, 1, 1)
        ");

        // Transfer control to trainee
        $this->simulateSetTrainingControl('TestTrainer', 'TestTrainee1', 'trainee');

        // Trainer should be removed from CallControl
        $trainerCallControl = getTestCallControl('TestTrainer');
        $this->assertFalse($trainerCallControl);

        // Trainee should be in CallControl
        $traineeCallControl = getTestCallControl('TestTrainee1');
        $this->assertNotFalse($traineeCallControl);
    }

    /**
     * Test control transfer between trainees
     */
    public function testControlTransferBetweenTrainees(): void
    {
        // First transfer to trainee 1
        $this->simulateSetTrainingControl('TestTrainer', 'TestTrainee1', 'trainee');

        // Then transfer to trainee 2
        $result = $this->simulateSetTrainingControl('TestTrainer', 'TestTrainee2', 'trainee');

        $this->assertTrue($result['success']);
        $this->assertEquals('TestTrainee2', $result['activeController']);

        // Trainee1 should be removed from CallControl
        $trainee1CallControl = getTestCallControl('TestTrainee1');
        $this->assertFalse($trainee1CallControl);

        // Trainee2 should be in CallControl
        $trainee2CallControl = getTestCallControl('TestTrainee2');
        $this->assertNotFalse($trainee2CallControl);
    }

    /**
     * Test invalid controller role
     */
    public function testInvalidControllerRole(): void
    {
        $result = $this->simulateSetTrainingControl('TestTrainer', 'TestTrainer', 'invalid_role');

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(400, $result['code']);
        $this->assertStringContainsString('must be trainer or trainee', $result['error']);
    }
}
