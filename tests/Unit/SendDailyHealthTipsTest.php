<?php

namespace Tests\Unit;

use App\Console\Commands\SendDailyHealthTips;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\OpenRouterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class SendDailyHealthTipsTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set test environment variables
        Config::set('services.openrouter.api_key', 'test_api_key');
        Config::set('services.openrouter.model', 'test_model');
    }
    
    public function test_send_daily_health_tips_command()
    {
        // Mock the current time to a specific hour
        Carbon::setTestNow(Carbon::create(2023, 1, 1, 8, 0, 0));
        
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
        $scheduledMessage = ScheduledMessage::create([
            'user_id' => $user->id,
            'preferred_time' => '08:00',
            'is_active' => true,
            'last_sent_at' => Carbon::now()->subDay() // Set to yesterday
        ]);
        
        // Mock the OpenRouterService
        $mockOpenRouterService = Mockery::mock(OpenRouterService::class);
        $mockOpenRouterService->shouldReceive('generateResponse')
            ->once()
            ->andReturn('Here is your daily health tip: Drink plenty of water throughout the day.');
        $this->app->instance(OpenRouterService::class, $mockOpenRouterService);
        
        // Mock the Log facade
        Log::shouldReceive('info')->atLeast()->once();
        
        // Run the command
        Artisan::call('health:send-daily-tips');
        
        // Refresh the scheduled message from the database
        $scheduledMessage->refresh();
        
        // Assert that last_sent_at was updated
        $this->assertEquals(
            Carbon::now()->toDateTimeString(),
            $scheduledMessage->last_sent_at->toDateTimeString()
        );
    }
    
    public function test_no_messages_sent_outside_preferred_time()
    {
        // Mock the current time to 9:00
        Carbon::setTestNow(Carbon::create(2023, 1, 1, 9, 0, 0));
        
        // Create a user with preferred time of 8:00
        $user = User::factory()->create([
            'preferences' => json_encode([
                'name' => 'Test User',
                'interests' => 'nutrition',
                'preferred_time' => '08:00'
            ])
        ]);
        
        // Create a scheduled message for the user
        ScheduledMessage::create([
            'user_id' => $user->id,
            'preferred_time' => '08:00',
            'is_active' => true,
            'last_sent_at' => Carbon::now()->subDay() // Set to yesterday
        ]);
        
        // Mock the OpenRouterService
        $mockOpenRouterService = Mockery::mock(OpenRouterService::class);
        $mockOpenRouterService->shouldReceive('generateResponse')
            ->never(); // Expect no calls
        $this->app->instance(OpenRouterService::class, $mockOpenRouterService);
        
        // Run the command
        Artisan::call('health:send-daily-tips');
    }
    
    public function test_inactive_scheduled_messages_not_sent()
    {
        // Mock the current time
        Carbon::setTestNow(Carbon::create(2023, 1, 1, 8, 0, 0));
        
        // Create a user
        $user = User::factory()->create();
        
        // Create an inactive scheduled message
        ScheduledMessage::create([
            'user_id' => $user->id,
            'preferred_time' => '08:00',
            'is_active' => false, // Inactive
            'last_sent_at' => Carbon::now()->subDay()
        ]);
        
        // Mock the OpenRouterService
        $mockOpenRouterService = Mockery::mock(OpenRouterService::class);
        $mockOpenRouterService->shouldReceive('generateResponse')
            ->never(); // Expect no calls
        $this->app->instance(OpenRouterService::class, $mockOpenRouterService);
        
        // Run the command
        Artisan::call('health:send-daily-tips');
    }
    
    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Clear mock time
        Mockery::close();
        parent::tearDown();
    }
}