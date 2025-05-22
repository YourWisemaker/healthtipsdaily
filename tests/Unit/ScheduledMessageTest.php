<?php

namespace Tests\Unit;

use App\Models\ScheduledMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduledMessageTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create users table first for foreign key constraints
        if (!Schema::hasTable('users')) {
            Artisan::call('migrate:fresh', ['--path' => 'database/migrations/2014_10_12_000000_create_users_table.php']);
        }
        
        // Create scheduled_messages table if it doesn't exist
        if (!Schema::hasTable('scheduled_messages')) {
            Artisan::call('migrate', ['--path' => 'database/migrations/2023_10_15_000000_create_scheduled_messages_table.php']);
        }
    }
    
    public function test_can_create_scheduled_message()
    {
        // Create a user
        $user = User::factory()->create([
            'name' => 'Test User',
            'phone_number' => '+1234567890',
        ]);
        
        // Create a scheduled message
        $scheduledMessage = ScheduledMessage::create([
            'user_id' => $user->id,
            'preferred_time' => '08:00',
            'is_active' => true,
        ]);
        
        // Assert the scheduled message was created
        $this->assertDatabaseHas('scheduled_messages', [
            'user_id' => $user->id,
            'preferred_time' => '08:00',
            'is_active' => true,
        ]);
        
        // Assert the relationship works
        $this->assertEquals($user->id, $scheduledMessage->user->id);
    }
    
    public function test_can_update_scheduled_message()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create a scheduled message
        $scheduledMessage = ScheduledMessage::create([
            'user_id' => $user->id,
            'preferred_time' => '08:00',
            'is_active' => true,
        ]);
        
        // Update the scheduled message
        $scheduledMessage->update([
            'preferred_time' => '18:00',
            'is_active' => false,
        ]);
        
        // Assert the scheduled message was updated
        $this->assertDatabaseHas('scheduled_messages', [
            'id' => $scheduledMessage->id,
            'preferred_time' => '18:00',
            'is_active' => false,
        ]);
    }
    
    public function test_can_delete_scheduled_message()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create a scheduled message
        $scheduledMessage = ScheduledMessage::create([
            'user_id' => $user->id,
            'preferred_time' => '08:00',
            'is_active' => true,
        ]);
        
        // Get the ID before deletion
        $id = $scheduledMessage->id;
        
        // Delete the scheduled message
        $scheduledMessage->delete();
        
        // Assert the scheduled message was deleted
        $this->assertDatabaseMissing('scheduled_messages', [
            'id' => $id,
        ]);
    }
}