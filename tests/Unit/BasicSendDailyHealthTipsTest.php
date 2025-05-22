<?php

namespace Tests\Unit;

use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\OpenRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Mockery;

class BasicSendDailyHealthTipsTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set test environment variables
        Config::set('services.openrouter.api_key', 'test_api_key');
        Config::set('services.openrouter.model', 'test_model');
    }
    
    public function test_command_runs_without_errors()
    {
        // Create a user
        $user = User::factory()->create([
            'name' => 'Test User',
            'phone_number' => '+1234567890',
            'preferences' => json_encode([
                'name' => 'Test User',
                'interests' => 'nutrition, fitness',
                'preferred_time' => '08:00'
            ])
        ]);
        
        // Create a scheduled message for the user
        ScheduledMessage::create([
            'user_id' => $user->id,
            'preferred_time' => '08:00',
            'is_active' => true
        ]);
        
        // Mock the OpenRouterService
        $mockOpenRouterService = Mockery::mock(OpenRouterService::class);
        $mockOpenRouterService->shouldReceive('generateResponse')
            ->zeroOrMoreTimes()
            ->andReturn('Here is your daily health tip: Drink plenty of water throughout the day.');
        $this->app->instance(OpenRouterService::class, $mockOpenRouterService);
        
        // Run the command
        try {
            Artisan::call('app:send-daily-health-tips');
            $this->assertTrue(true); // If we get here, the command ran without errors
        } catch (\Exception $e) {
            $this->fail('Command failed with exception: ' . $e->getMessage());
        }
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
