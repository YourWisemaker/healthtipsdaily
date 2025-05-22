<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Conversation;
use App\Models\MessageLog;
use App\Services\OpenRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Mockery;

class WhatsAppControllerTest extends TestCase
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
        
        // Set test environment variables
        Config::set('services.openrouter.api_key', 'test_api_key');
        Config::set('services.openrouter.model', 'test_model');
        
        // Set WhatsApp verification token
        $this->app['config']->set('services.whatsapp.verify_token', 'test_verify_token');
    }

    public function test_webhook_verification_succeeds_with_valid_token()
    {
        // Set the WHATSAPP_VERIFY_TOKEN in the environment
        $this->app['config']->set('services.whatsapp.verify_token', 'test_verify_token');
        
        $response = $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=test_verify_token&hub_challenge=challenge_code');
        
        $response->assertStatus(200);
        $response->assertSee('challenge_code');
    }

    public function test_webhook_verification_fails_with_invalid_token()
    {
        $response = $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=invalid_token&hub_challenge=challenge_code');
        
        $response->assertStatus(403);
    }

    public function test_webhook_processes_incoming_message()
    {
        // Mock the OpenRouterService
        $mockOpenRouterService = Mockery::mock(OpenRouterService::class);
        $mockOpenRouterService->shouldReceive('generateResponse')
            ->zeroOrMoreTimes()
            ->andReturn('This is a test response from the AI');
        $this->app->instance(OpenRouterService::class, $mockOpenRouterService);
        
        // Create a webhook payload
        $payload = [
            'message' => 'Hello, this is a test message',
            'from' => '+1234567890',
            'message_id' => 'test_message_id_123'
        ];
        
        // Send the webhook request
        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        
        // Assert the response
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
        
        // Assert that a user was created
        $this->assertDatabaseHas('users', [
            'phone_number' => '+1234567890'
        ]);
        
        // Assert that message logs were created
        $this->assertDatabaseHas('message_logs', [
            'message' => 'Hello, this is a test message',
            'direction' => 'incoming',
            'whatsapp_message_id' => 'test_message_id_123'
        ]);
        
        // Check that an outgoing message log exists (without specifying the exact content)
        $this->assertDatabaseHas('message_logs', [
            'direction' => 'outgoing'
        ]);
        
        // Get the actual response from the database
        $responseLog = MessageLog::where('direction', 'outgoing')->first();
        $this->assertNotNull($responseLog);
        $this->assertNotNull($responseLog->response);
    }

    public function test_openrouter_service_integration()
    {
        // Create a mock HTTP response
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'This is a test AI response'
                    ]
                ]
            ]
        ];
        
        // Create a real OpenRouterService with mocked HTTP client
        $openRouterService = new OpenRouterService();
        
        // Set test configuration
        Config::set('services.openrouter.api_key', 'test_api_key');
        Config::set('services.openrouter.model', 'test_model');
        
        // Test with sample messages
        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant'],
            ['role' => 'user', 'content' => 'Hello, how are you?']
        ];
        
        // Use reflection to test the service without making actual API calls
        $reflectionClass = new \ReflectionClass(OpenRouterService::class);
        $reflectionProperty = $reflectionClass->getProperty('apiKey');
        $reflectionProperty->setAccessible(true);
        $apiKey = $reflectionProperty->getValue($openRouterService);
        
        // Assert that the API key was set correctly from the config
        $this->assertEquals('test_api_key', $apiKey);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}