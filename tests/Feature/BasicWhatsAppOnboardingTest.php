<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Conversation;
use App\Models\ScheduledMessage;
use App\Services\OpenRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Mockery;

class BasicWhatsAppOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set test environment variables
        Config::set('services.openrouter.api_key', 'test_api_key');
        Config::set('services.openrouter.model', 'test_model');
        Config::set('services.whatsapp.verify_token', 'test_verify_token');
    }

    public function test_can_create_user_from_webhook()
    {
        // Mock the OpenRouterService
        $mockOpenRouterService = Mockery::mock(OpenRouterService::class);
        $mockOpenRouterService->shouldReceive('generateResponse')
            ->zeroOrMoreTimes()
            ->andReturn('This is a test response from the AI');
        $this->app->instance(OpenRouterService::class, $mockOpenRouterService);
        
        // Send a webhook request
        $payload = [
            'message' => 'Hello',
            'from' => '+1234567890',
            'message_id' => 'test_message_id_1'
        ];
        
        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertStatus(200);
        
        // Assert that a user was created
        $this->assertDatabaseHas('users', [
            'phone_number' => '+1234567890'
        ]);
        
        // Assert that a message log was created
        $this->assertDatabaseHas('message_logs', [
            'message' => 'Hello',
            'direction' => 'incoming'
        ]);
        
        // Assert that an outgoing message was logged
        $this->assertDatabaseHas('message_logs', [
            'direction' => 'outgoing'
        ]);
    }
    
    public function test_can_create_scheduled_message()
    {
        // Create a user with complete preferences
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'phone_number' => '+9876543210',
            'preferences' => json_encode([
                'name' => 'Test User',
                'interests' => 'nutrition, fitness',
                'preferred_time' => '08:30'
            ])
        ]);
        
        // Create a scheduled message for the user
        $scheduledMessage = ScheduledMessage::create([
            'user_id' => $user->id,
            'preferred_time' => '08:30',
            'is_active' => true
        ]);
        
        // Assert that the scheduled message was created
        // The preferred_time is stored as a datetime in the database
        $this->assertDatabaseHas('scheduled_messages', [
            'user_id' => $user->id,
            'is_active' => true
        ]);
        
        // Verify the preferred_time separately since it might be stored in a different format
        $scheduledMessage = ScheduledMessage::where('user_id', $user->id)->first();
        $this->assertNotNull($scheduledMessage);
        $this->assertStringContainsString('08:30', $scheduledMessage->preferred_time);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
