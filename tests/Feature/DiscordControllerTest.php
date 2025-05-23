<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DiscordService;
use App\Services\OpenRouterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class DiscordControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $openRouterMock;
    protected $discordServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the OpenRouter service
        $this->openRouterMock = Mockery::mock(OpenRouterService::class);
        $this->app->instance(OpenRouterService::class, $this->openRouterMock);
        
        // Mock the Discord service
        $this->discordServiceMock = Mockery::mock(DiscordService::class);
        $this->app->instance(DiscordService::class, $this->discordServiceMock);
        
        // Set up default mock behavior
        $this->openRouterMock->shouldReceive('generateResponse')
            ->andReturn('This is a test response about health.')
            ->byDefault();
            
        $this->discordServiceMock->shouldReceive('sendDirectMessage')
            ->andReturn(true)
            ->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_discord_interaction_ping_challenge(): void
    {
        // Create a Discord interaction ping challenge
        $payload = [
            'type' => 1, // PING type
            'id' => 'interaction123456',
            'application_id' => 'app123456'
        ];

        // Send the webhook request
        $response = $this->postJson('/api/discord/webhook', $payload);

        // Assert the response is a pong
        $response->assertStatus(200)
                ->assertJson(['type' => 1]);
    }

    public function test_discord_webhook_processes_incoming_message(): void
    {
        // Create a sample Discord webhook payload
        $payload = [
            'content' => 'Hello from Discord',
            'author' => [
                'id' => '123456789',
                'username' => 'TestUser',
                'bot' => false
            ],
            'id' => 'msg123456',
            'channel_id' => 'channel123456'
        ];

        // Send the webhook request
        $response = $this->postJson('/api/discord/webhook', $payload);

        // Assert the response
        $response->assertStatus(200)
                ->assertJson(['status' => 'success']);

        // Assert that a user was created
        $this->assertDatabaseHas('users', [
            'discord_id' => '123456789',
            'name' => 'TestUser'
        ]);

        // Assert that a message log was created
        $this->assertDatabaseHas('message_logs', [
            'message' => 'Hello from Discord',
            'direction' => 'incoming',
            'discord_message_id' => 'msg123456'
        ]);
    }

    public function test_discord_slash_command_healthtip(): void
    {
        // Create a Discord slash command interaction
        $payload = [
            'type' => 2, // APPLICATION_COMMAND type
            'id' => 'interaction123456',
            'application_id' => 'app123456',
            'data' => [
                'name' => 'healthtip',
                'id' => 'cmd123456'
            ],
            'member' => [
                'user' => [
                    'id' => '123456789',
                    'username' => 'TestUser'
                ]
            ]
        ];

        // Send the webhook request
        $response = $this->postJson('/api/discord/webhook', $payload);

        // Assert the response is a channel message with source
        $response->assertStatus(200)
                ->assertJson([
                    'type' => 4,
                    'data' => [
                        'flags' => 64 // Ephemeral flag
                    ]
                ]);
    }

    public function test_discord_slash_command_subscribe(): void
    {
        // Create a Discord slash command interaction for subscribe
        $payload = [
            'type' => 2, // APPLICATION_COMMAND type
            'id' => 'interaction123456',
            'application_id' => 'app123456',
            'data' => [
                'name' => 'subscribe',
                'id' => 'cmd123456',
                'options' => [
                    [
                        'name' => 'time',
                        'value' => '08:00'
                    ]
                ]
            ],
            'member' => [
                'user' => [
                    'id' => '123456789',
                    'username' => 'TestUser'
                ]
            ]
        ];

        // Send the webhook request
        $response = $this->postJson('/api/discord/webhook', $payload);

        // Assert the response is a channel message with source
        $response->assertStatus(200)
                ->assertJson([
                    'type' => 4,
                    'data' => [
                        'flags' => 64 // Ephemeral flag
                    ]
                ]);
        
        // Assert that a scheduled message was created
        $user = User::where('discord_id', '123456789')->first();
        $this->assertDatabaseHas('scheduled_messages', [
            'user_id' => $user->id,
            'preferred_time' => '08:00',
            'is_active' => 1
        ]);
    }
}
