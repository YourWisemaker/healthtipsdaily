<?php

namespace App\Console\Commands;

use App\Services\DiscordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestDiscordMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:test-message 
                            {--webhook : Send a test message using the webhook}
                            {--dm : Send a test direct message}
                            {--user-id= : The Discord user ID to send a direct message to}
                            {--channel-id= : The Discord channel ID to send a message to}
                            {--message= : The message to send}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test message to Discord';

    /**
     * The Discord service instance.
     */
    protected $discordService;

    /**
     * Create a new command instance.
     */
    public function __construct(DiscordService $discordService)
    {
        parent::__construct();
        $this->discordService = $discordService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Sending test message to Discord...');
        
        $message = $this->option('message') ?? 'This is a test message from HealthTipsDaily at ' . now()->format('Y-m-d H:i:s');
        
        // Test webhook message
        if ($this->option('webhook')) {
            $this->info('Sending webhook message...');
            $success = $this->discordService->sendWebhookMessage($message);
            
            if ($success) {
                $this->info('Webhook message sent successfully!');
            } else {
                $this->error('Failed to send webhook message.');
                return Command::FAILURE;
            }
        }
        
        // Test direct message
        if ($this->option('dm')) {
            $userId = $this->option('user-id');
            
            if (empty($userId)) {
                $this->error('You must provide a user ID with --user-id option to send a direct message.');
                return Command::FAILURE;
            }
            
            $this->info("Sending direct message to user ID: {$userId}...");
            $success = $this->discordService->sendDirectMessage($userId, $message);
            
            if ($success) {
                $this->info('Direct message sent successfully!');
            } else {
                $this->error('Failed to send direct message.');
                return Command::FAILURE;
            }
        }
        
        // Test channel message
        if ($this->option('channel-id')) {
            $channelId = $this->option('channel-id');
            
            $this->info("Sending message to channel ID: {$channelId}...");
            
            try {
                $botToken = config('services.discord.bot_token');
                
                if (empty($botToken)) {
                    $this->error('Discord bot token not configured.');
                    return Command::FAILURE;
                }
                
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bot ' . $botToken,
                    'Content-Type' => 'application/json',
                ])->post("https://discord.com/api/v10/channels/{$channelId}/messages", [
                    'content' => $message,
                ]);
                
                if ($response->successful()) {
                    $this->info('Channel message sent successfully!');
                } else {
                    $this->error('Failed to send channel message: ' . $response->body());
                    return Command::FAILURE;
                }
            } catch (\Exception $e) {
                $this->error('Exception while sending channel message: ' . $e->getMessage());
                Log::error('Discord channel message exception', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return Command::FAILURE;
            }
        }
        
        // If no options were provided, show usage
        if (!$this->option('webhook') && !$this->option('dm') && !$this->option('channel-id')) {
            $this->info('No message type specified. Please use one of the following options:');
            $this->info('--webhook: Send a test message using the webhook');
            $this->info('--dm --user-id=<user_id>: Send a test direct message to a user');
            $this->info('--channel-id=<channel_id>: Send a test message to a channel');
            $this->info('--message="Your message": Specify a custom message (optional)');
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}
