<?php

namespace Tests\Unit;

use App\Services\OpenRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OpenRouterServiceTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        
        // Use RefreshDatabase trait instead of manual migrations
        // This ensures a clean database state for each test
        
        // Set test environment variables
        Config::set('services.openrouter.api_key', 'test_api_key');
        Config::set('services.openrouter.base_url', 'https://test.openrouter.ai/api/v1');
        Config::set('services.openrouter.model', 'test_model');
    }

    public function test_generate_response_success()
    {
        // Mock successful HTTP response
        Http::fake([
            'https://test.openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'This is a test AI response'
                        ]
                    ]
                ]
            ], 200)
        ]);
        
        $openRouterService = new OpenRouterService();
        
        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant'],
            ['role' => 'user', 'content' => 'Hello, how are you?']
        ];
        
        $response = $openRouterService->generateResponse($messages);
        
        $this->assertEquals('This is a test AI response', $response);
        
        // Assert that the request was sent with the correct data
        Http::assertSent(function ($request) {
            return $request->url() == 'https://test.openrouter.ai/api/v1/chat/completions' &&
                   $request->hasHeader('Authorization', 'Bearer test_api_key') &&
                   $request['model'] == 'test_model' &&
                   $request['messages'][0]['role'] == 'system' &&
                   $request['messages'][0]['content'] == 'You are a helpful assistant' &&
                   $request['messages'][1]['role'] == 'user' &&
                   $request['messages'][1]['content'] == 'Hello, how are you?';
        });
    }

    public function test_generate_response_error()
    {
        // Mock error HTTP response
        Http::fake([
            'https://test.openrouter.ai/api/v1/chat/completions' => Http::response([
                'error' => 'Invalid API key'
            ], 401)
        ]);
        
        $openRouterService = new OpenRouterService();
        
        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant'],
            ['role' => 'user', 'content' => 'Hello, how are you?']
        ];
        
        $response = $openRouterService->generateResponse($messages);
        
        // Should return the error message
        $this->assertEquals('Sorry, there was an error processing your request.', $response);
    }

    public function test_generate_response_exception()
    {
        // Mock exception
        Http::fake(function () {
            throw new \Exception('Connection error');
        });
        
        $openRouterService = new OpenRouterService();
        
        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant'],
            ['role' => 'user', 'content' => 'Hello, how are you?']
        ];
        
        $response = $openRouterService->generateResponse($messages);
        
        // Should return the exception message
        $this->assertEquals('Sorry, there was an error connecting to the AI service.', $response);
    }
}