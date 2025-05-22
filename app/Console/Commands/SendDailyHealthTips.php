<?php

namespace App\Console\Commands;

use App\Models\ScheduledMessage;
use App\Models\User;
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
    protected $signature = 'app:send-daily-health-tips';

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
     * Create a new command instance.
     */
    public function __construct(OpenRouterService $openRouterService)
    {
        parent::__construct();
        $this->openRouterService = $openRouterService;
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
        
        // Find all scheduled messages that should be sent at this time
        $scheduledMessages = ScheduledMessage::with('user')
            ->where('is_active', true)
            ->where('preferred_time', $currentTime)
            ->whereNull('last_sent_at')
            ->orWhere('last_sent_at', '<', $now->subDay())
            ->get();
        
        $this->info("Found {$scheduledMessages->count()} messages to send.");
        
        foreach ($scheduledMessages as $scheduledMessage) {
            $user = $scheduledMessage->user;
            
            if (!$user) {
                continue;
            }
            
            $this->info("Generating health tip for user: {$user->name}");
            
            // Generate a personalized health tip using OpenRouter
            $healthTip = $this->generateHealthTip($user);
            
            // Send the health tip via WhatsApp
            $this->sendWhatsAppMessage($user->phone_number, $healthTip);
            
            // Update the last sent time
            $scheduledMessage->update([
                'last_sent_at' => now(),
            ]);
        }
        
        $this->info('Daily health tips sent successfully!');
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
        
        // Create a system prompt for generating the health tip
        $systemPrompt = "You are HealthTipsDaily, a friendly health assistant. ";
        $systemPrompt .= "Create a short, personalized daily health tip for {$name} who is interested in {$interests}. ";
        $systemPrompt .= "The tip should be evidence-based, practical, and actionable. ";
        $systemPrompt .= "Keep it under 3 paragraphs and make it motivational. ";
        $systemPrompt .= "Today's date is " . now()->toDateString() . ".";
        
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
}