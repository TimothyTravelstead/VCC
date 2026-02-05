<?php
/**
 * Tests for getTrainingControl.php
 *
 * Tests:
 * - Returns existing control record
 * - Creates default record if none exists
 * - Handles invalid trainer gracefully
 */

use PHPUnit\Framework\TestCase;

class GetTrainingControlTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset test database before each test
        TestDatabase::reset();
    }

    /**
     * Test that existing control record is returned correctly
     */
    public function testReturnsExistingControlRecord(): void
    {
        // Insert test trainer
        insertTestVolunteer([
            'UserName' => 'TestTrainer',
            'trainer' => 1,
            'LoggedOn' => 4
        ]);

        // Insert existing control record
        insertTestTrainingControl('TestTrainer', 'TestTrainer', 'trainer');

        // Get control
        $result = getTestTrainingControl('TestTrainer');

        $this->assertNotNull($result);
        $this->assertEquals('TestTrainer', $result->trainer_id);
        $this->assertEquals('TestTrainer', $result->active_controller);
        $this->assertEquals('trainer', $result->controller_role);
    }

    /**
     * Test that control record with trainee as controller is returned
     */
    public function testReturnsControlRecordWithTraineeController(): void
    {
        // Insert test data
        insertTestVolunteer([
            'UserName' => 'TestTrainer',
            'trainer' => 1,
            'LoggedOn' => 4,
            'TraineeID' => 'TestTrainee1'
        ]);

        insertTestVolunteer([
            'UserName' => 'TestTrainee1',
            'trainee' => 1,
            'LoggedOn' => 6
        ]);

        // Insert control record with trainee having control
        insertTestTrainingControl('TestTrainer', 'TestTrainee1', 'trainee');

        // Get control
        $result = getTestTrainingControl('TestTrainer');

        $this->assertNotNull($result);
        $this->assertEquals('TestTrainer', $result->trainer_id);
        $this->assertEquals('TestTrainee1', $result->active_controller);
        $this->assertEquals('trainee', $result->controller_role);
    }

    /**
     * Test that null is returned for non-existent trainer
     */
    public function testReturnsNullForNonExistentTrainer(): void
    {
        $result = getTestTrainingControl('NonExistentTrainer');

        $this->assertFalse($result);
    }

    /**
     * Test that last_updated timestamp is present
     */
    public function testLastUpdatedTimestampIsPresent(): void
    {
        insertTestVolunteer([
            'UserName' => 'TestTrainer',
            'trainer' => 1,
            'LoggedOn' => 4
        ]);

        insertTestTrainingControl('TestTrainer', 'TestTrainer', 'trainer');

        $result = getTestTrainingControl('TestTrainer');

        $this->assertNotNull($result);
        $this->assertObjectHasProperty('last_updated', $result);
    }
}
