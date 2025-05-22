<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Conversation;
use App\Models\ScheduledMessage;
use App\Services\OpenRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Mockery;

class WhatsAppOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create users table first for foreign key constraints
        if (!Schema::hasTable('users')) {
            Artisan::call('migrate:fresh', ['--path' => 'database/migrations/2014_10_12_000000_create_users_table.php']);
        }
        
        // Create necessary tables for testing
        if (!Schema::hasTable('message_logs')) {
            Artisan::call('migrate', ['--path' => 'database/migrations/2023_11_01_000001_create_chatbot_tables.php']);
        }
        
        if (!Schema::hasTable('scheduled_messages')) {
            Artisan::call('migrate', ['--path' => 'database/migrations/2023_10_15_000000_create_scheduled_messages_table.php']);
        }
        
        // Set test environment variables
        Config::set('services.openrouter.api_key', 'test_api_key');
        Config::set('services.openrouter.model', 'test_model');
        Config::set('services.whatsapp.verify_token', 'test_verify_token');
    }

    public function test_new_user_onboarding_flow()
    {
        // Mock the OpenRouterService
        $mockOpenRouterService = Mockery::mock(OpenRouterService::class);
        $mockOpenRouterService->shouldReceive('generateResponse')
            ->zeroOrMoreTimes()
            ->andReturn('This is a test response from the AI');
        $this->app->instance(OpenRouterService::class, $mockOpenRouterService);
        
        // Step 1: First message from a new user
        $payload = [
            'message' => 'Hello',
            'from' => '+1234567890',
            'message_id' => 'test_message_id_1'
        ];
        
        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertStatus(200);
        
        // Assert welcome message was sent
        $user = User::where('phone_number', '+1234567890')->first();
        $this->assertNotNull($user);
        // Initial user name is set to 'WhatsApp User' by the controller
        $this->assertEquals('WhatsApp User', $user->name);
        
        // Step 2: User sends their name
        $payload = [
            'message' => 'John Doe',
            'from' => '+1234567890',
            'message_id' => 'test_message_id_2'
        ];
        
        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertStatus(200);
        
        // Sleep briefly to ensure the database update completes
        usleep(100000); // 0.1 seconds
        
        // Assert name was saved
        $user->refresh();
        // Check that the name was updated in both the user record and preferences
        $this->assertEquals('John Doe', $user->name);
        $preferences = json_decode($user->preferences, true);
        $this->assertEquals('John Doe', $preferences['name']);
        
        // Step 3: User sends their interests
        $payload = [
            'message' => 'nutrition, fitness',
            'from' => '+1234567890',
            'message_id' => 'test_message_id_3'
        ];
        
        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertStatus(200);
        
        // Assert interests were saved
        $user->refresh();
        $preferences = json_decode($user->preferences, true);
        $this->assertEquals('nutrition, fitness', $preferences['interests']);
        
        // Step 4: User sends preferred time
        $payload = [
            'message' => '08:30',
            'from' => '+1234567890',
            'message_id' => 'test_message_id_4'
        ];
        
        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertStatus(200);
        
        // Assert preferred time was saved and scheduled message was created
        $user->refresh();
        $preferences = json_decode($user->preferences, true);
        $this->assertEquals('08:30', $preferences['preferred_time']);
        
        $this->assertDatabaseHas('scheduled_messages', [
            'user_id' => $user->id,
            'preferred_time' => '08:30',
            'is_active' => true
        ]);
    }
    
    public function test_invalid_time_format_during_onboarding()
    {
        // Mock the OpenRouterService
        $mockOpenRouterService = Mockery::mock(OpenRouterService::class);
        $mockOpenRouterService->shouldReceive('generateResponse')
            ->andReturn('This is a test response from the AI');
        $this->app->instance(OpenRouterService::class, $mockOpenRouterService);
        
        // Create a user with name and interests already set
        $user = User::create([
            'name' => 'Test User',
            'phone_number' => '+9876543210',
            'email' => '+9876543210@example.com',
            'password' => bcrypt('password'),
            'preferences' => json_encode([
                'name' => 'Test User',
                'interests' => 'sleep, mental health'
            ])
        ]);
        
        // User sends invalid time format
        $payload = [
            'message' => 'morning',  // Invalid time format
            'from' => '+9876543210',
            'message_id' => 'test_message_id_5'
        ];
        
        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertStatus(200);
        
        // Assert preferred time was not saved
        $user->refresh();
        $preferences = json_decode($user->preferences, true);
        $this->assertArrayNotHasKey('preferred_time', $preferences);
        
        // Assert no scheduled message was created
        $this->assertDatabaseMissing('scheduled_messages', [
            'user_id' => $user->id,
        ]);
    }
    
    public function test_existing_user_conversation()
    {
        // Mock the OpenRouterService
        $mockOpenRouterService = Mockery::mock(OpenRouterService::class);
        $mockOpenRouterService->shouldReceive('generateResponse')
            ->zeroOrMoreTimes()
            ->andReturn('This is a test response about nutrition');
        $this->app->instance(OpenRouterService::class, $mockOpenRouterService);
        
        // Create a fully onboarded user
        $user = User::create([
            'name' => 'Existing User',
            'phone_number' => '+5555555555',
            'email' => '+5555555555@example.com',
            'password' => bcrypt('password'),
            'preferences' => json_encode([
                'name' => 'Existing User',
                'interests' => 'nutrition',
                'preferred_time' => '09:00'
            ])
        ]);
        
        // Create a conversation for the user
        Conversation::create([
            'user_id' => $user->id,
            'prompt_history' => json_encode([]),
            'last_prompt_time' => now()
        ]);
        
        // User sends a message
        $payload = [
            'message' => 'Tell me about healthy eating',
            'from' => '+5555555555',
            'message_id' => 'test_message_id_6'
        ];
        
        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertStatus(200);
        
        // Assert conversation was updated
        $conversation = Conversation::where('user_id', $user->id)->first();
        $this->assertNotNull($conversation);
        
        $promptHistory = json_decode($conversation->prompt_history, true);
        $this->assertCount(2, $promptHistory);
        $this->assertEquals('user', $promptHistory[0]['role']);
        $this->assertEquals('Tell me about healthy eating', $promptHistory[0]['content']);
        $this->assertEquals('assistant', $promptHistory[1]['role']);
        $this->assertEquals('This is a test response about nutrition', $promptHistory[1]['content']);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}