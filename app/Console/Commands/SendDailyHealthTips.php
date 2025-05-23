<?php

namespace App\Console\Commands;

use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\DiscordService;
use App\Services\OpenRouterService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyHealthTips extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-daily-health-tips
                            {--all : Send to all users regardless of their preferred time}
                            {--limit= : Limit the number of messages to send when using --all}
                            {--force : Send even if the message was already sent today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily health tips to users based on their preferred time';

    /**
     * The OpenRouter service instance.
     */
    protected $openRouterService;
    
    /**
     * The Discord service instance.
     */
    protected $discordService;

    /**
     * Create a new command instance.
     */
    public function __construct(OpenRouterService $openRouterService, DiscordService $discordService)
    {
        parent::__construct();
        $this->openRouterService = $openRouterService;
        $this->discordService = $discordService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting to send daily health tips...');
        
        // Get current time rounded to the nearest hour:minute
        $now = Carbon::now();
        $currentTime = $now->format('H:i');
        
        if ($this->option('all')) {
            // Send to all users with active scheduled messages
            $query = ScheduledMessage::with('user')
                ->where('is_active', true);
                
            // Only apply the last_sent_at filter if not forcing
            if (!$this->option('force')) {
                $query->where(function($q) use ($now) {
                    $q->whereNull('last_sent_at')
                      ->orWhere('last_sent_at', '<', $now->copy()->subDay());
                });
            }
            
            // Apply limit if specified
            if ($this->option('limit')) {
                $limit = (int) $this->option('limit');
                $query->limit($limit);
            }
            
            $scheduledMessages = $query->get();
            $this->info("Found {$scheduledMessages->count()} active users to send messages to.");
        } else {
            // Find scheduled messages that should be sent at this time
            $query = ScheduledMessage::with('user')
                ->where('is_active', true)
                ->where('preferred_time', $currentTime);
                
            // Only apply the last_sent_at filter if not forcing
            if (!$this->option('force')) {
                $query->where(function($q) use ($now) {
                    $q->whereNull('last_sent_at')
                      ->orWhere('last_sent_at', '<', $now->copy()->subDay());
                });
            }
            
            $scheduledMessages = $query->get();
            $this->info("Found {$scheduledMessages->count()} messages to send for the current time ({$currentTime}).");
        }
        
        $sentCount = 0;
        foreach ($scheduledMessages as $scheduledMessage) {
            $user = $scheduledMessage->user;
            
            if (!$user) {
                $this->warn("Skipping scheduled message ID {$scheduledMessage->id} - user not found.");
                continue;
            }
            
            $this->info("Generating health tip for user: {$user->name}");
            
            // Generate a personalized health tip using OpenRouter
            $healthTip = $this->generateHealthTip($user);
            $messageSent = false;
            
            // Send the health tip via the appropriate channel
            if (!empty($user->phone_number)) {
                $this->sendWhatsAppMessage($user->phone_number, $healthTip);
                $messageSent = true;
                $this->info("Sent WhatsApp message to {$user->name} ({$user->phone_number}).");
            }
            
            if (!empty($user->discord_id)) {
                $this->sendDiscordMessage($user->discord_id, $healthTip);
                $messageSent = true;
                $this->info("Sent Discord message to {$user->name} (Discord ID: {$user->discord_id}).");
            }
            
            if ($messageSent) {
                // Update the last sent time
                $scheduledMessage->update([
                    'last_sent_at' => now(),
                ]);
                $sentCount++;
            } else {
                $this->warn("No message sent for user {$user->name} - no contact methods available.");
            }
        }
        
        if ($sentCount > 0) {
            $this->info("Successfully sent {$sentCount} health tips!");
        } else {
            $this->info('No health tips were sent.');
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Generate a personalized health tip for the user
     */
    protected function generateHealthTip(User $user): string
    {
        $preferences = json_decode($user->preferences, true) ?: [];
        $name = $preferences['name'] ?? 'there';
        $interests = $preferences['interests'] ?? 'health topics';
        
        // Get current day of week
        $currentDayOfWeek = now()->format('l'); // Returns full day name (e.g., "Monday")
        
        // Create a system prompt for generating the health tip
        $systemPrompt = "You are HealthTipsDaily, a friendly health assistant. ";
        $systemPrompt .= "Create a short, personalized daily health tip for {$name} who is interested in {$interests}. ";
        $systemPrompt .= "The tip should be evidence-based, practical, and actionable. ";
        $systemPrompt .= "Keep it under 3 paragraphs and make it motivational. ";
        $systemPrompt .= "Today is {$currentDayOfWeek}, " . now()->toDateString() . ". Make sure to reference the correct day of the week if you mention it.";
        
        // Prepare the messages for OpenRouter
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Please send me today's health tip."],
        ];
        
        // Generate the health tip
        $healthTip = $this->openRouterService->generateResponse($messages);
        
        return $healthTip;
    }
    
    /**
     * Send a WhatsApp message to the user
     */
    protected function sendWhatsAppMessage(string $phoneNumber, string $message): void
    {
        // This would integrate with your WhatsApp provider's API
        // For now, we'll just log the message
        Log::info('Sending daily health tip via WhatsApp', [
            'to' => $phoneNumber,
            'message' => $message,
        ]);
        
        // In a real implementation, you would make an API call here
        // Example with HTTP client:
        /*
        Http::post('your-whatsapp-provider-url', [
            'to' => $phoneNumber,
            'message' => $message,
            // Other required parameters for your provider
        ]);
        */
    }
    
    /**
     * Send a Discord direct message to the user
     */
    protected function sendDiscordMessage(string $discordId, string $message): void
    {
        Log::info('Sending daily health tip via Discord', [
            'to' => $discordId,
            'message' => $message,
        ]);
        
        // Use the Discord service to send a direct message
        $this->discordService->sendDirectMessage($discordId, $message);
    }
}