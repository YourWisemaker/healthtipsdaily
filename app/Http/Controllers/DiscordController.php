<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\MessageLog;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\OpenRouterService;
use App\Services\DiscordService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscordController extends Controller
{
    protected $openRouterService;
    protected $discordService;

    public function __construct(OpenRouterService $openRouterService, DiscordService $discordService)
    {
        $this->openRouterService = $openRouterService;
        $this->discordService = $discordService;
    }

    /**
     * Handle incoming Discord webhook
     */
    public function webhook(Request $request)
    {
        Log::info('Discord webhook received', ['payload' => $request->all()]);

        try {
            // Verify Discord interaction
            if ($request->has('type')) {
                // Handle Discord Interactions API
                $type = $request->input('type');
                
                // Respond to Discord's ping challenge
                if ($type === 1) {
                    return response()->json(['type' => 1]);
                }
                
                // Handle commands (type 2)
                if ($type === 2) {
                    return $this->handleCommand($request);
                }
                
                // Handle message components (type 3)
                if ($type === 3) {
                    return $this->handleComponent($request);
                }
            }
            
            // Extract message data from the webhook payload
            $messageData = $this->extractMessageData($request);
            
            if (empty($messageData)) {
                return response()->json(['status' => 'success']);
            }

            // Find or create user by Discord ID
            $user = $this->findOrCreateUser($messageData['user_id'], $messageData['username']);
            
            // Log the incoming message
            $messageLog = $this->logMessage($user->id, $messageData['message'], 'incoming', $messageData['message_id']);
            
            // Process the message and generate a response
            $response = $this->processMessage($user, $messageData['message']);
            
            // Log the outgoing response
            $this->logMessage($user->id, $response, 'outgoing');
            
            // Send the response back to Discord
            $this->sendDiscordResponse($messageData['channel_id'], $response);
            
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Error processing Discord webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Discord slash commands
     */
    protected function handleCommand(Request $request)
    {
        $data = $request->all();
        $commandName = $data['data']['name'] ?? '';
        
        if ($commandName === 'healthtip') {
            // Get user info
            $userId = $data['member']['user']['id'] ?? null;
            $username = $data['member']['user']['username'] ?? 'Discord User';
            
            // Find or create user
            $user = $this->findOrCreateUser($userId, $username);
            
            // Generate a health tip
            $healthTip = $this->generateHealthTip($user);
            
            // Return ephemeral response (only visible to the user who triggered it)
            return response()->json([
                'type' => 4, // Channel message with source
                'data' => [
                    'content' => $healthTip,
                    'flags' => 64 // Ephemeral flag
                ]
            ]);
        }
        
        if ($commandName === 'subscribe') {
            // Get user info
            $userId = $data['member']['user']['id'] ?? null;
            $username = $data['member']['user']['username'] ?? 'Discord User';
            
            // Get options
            $options = $data['data']['options'] ?? [];
            $time = null;
            
            foreach ($options as $option) {
                if ($option['name'] === 'time') {
                    $time = $option['value'];
                    break;
                }
            }
            
            if (!$time || !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
                return response()->json([
                    'type' => 4,
                    'data' => [
                        'content' => 'Please provide a valid time in HH:MM format (e.g., 08:00 or 18:30).',
                        'flags' => 64
                    ]
                ]);
            }
            
            // Find or create user
            $user = $this->findOrCreateUser($userId, $username);
            
            // Create or update scheduled message
            $scheduledMessage = ScheduledMessage::updateOrCreate(
                ['user_id' => $user->id],
                ['preferred_time' => $time, 'is_active' => true]
            );
            
            // Update user preferences
            $preferences = json_decode($user->preferences, true) ?: [];
            $preferences['preferred_time'] = $time;
            $user->update(['preferences' => json_encode($preferences)]);
            
            return response()->json([
                'type' => 4,
                'data' => [
                    'content' => "You've been subscribed to daily health tips at {$time}! You'll receive your first tip tomorrow.",
                    'flags' => 64
                ]
            ]);
        }
        
        return response()->json([
            'type' => 4,
            'data' => [
                'content' => 'Unknown command.',
                'flags' => 64
            ]
        ]);
    }

    /**
     * Handle Discord message components (buttons, select menus, etc.)
     */
    protected function handleComponent(Request $request)
    {
        // Implementation for handling components if needed
        return response()->json([
            'type' => 6 // ACK, no response
        ]);
    }

    /**
     * Extract message data from the webhook payload
     */
    protected function extractMessageData(Request $request): array
    {
        $data = $request->all();
        
        // Return empty array if this is not a message event or is from a bot
        if (!isset($data['content']) || 
            !isset($data['author']) || 
            ($data['author']['bot'] ?? false)) {
            return [];
        }
        
        return [
            'message' => $data['content'] ?? '',
            'user_id' => $data['author']['id'] ?? '',
            'username' => $data['author']['username'] ?? 'Discord User',
            'message_id' => $data['id'] ?? null,
            'channel_id' => $data['channel_id'] ?? null,
        ];
    }

    /**
     * Find or create a user by Discord ID
     */
    protected function findOrCreateUser(string $discordId, string $username): User
    {
        $user = User::where('discord_id', $discordId)->first();
        
        if (!$user) {
            $user = User::create([
                'name' => $username,
                'email' => $discordId . '@discord.example.com', // Generate a placeholder email
                'password' => bcrypt(Str::random(16)), // Generate a random password
                'discord_id' => $discordId,
                'first_seen_at' => now(),
                'last_interaction_at' => now(),
            ]);
            
            // Create a conversation record for the new user
            Conversation::create([
                'user_id' => $user->id,
                'prompt_history' => json_encode([]),
                'last_prompt_time' => now(),
            ]);
        } else {
            $user->update(['last_interaction_at' => now()]);
        }
        
        return $user;
    }

    /**
     * Log a message in the database
     */
    protected function logMessage(int $userId, string $message, string $direction, ?string $discordMessageId = null): MessageLog
    {
        return MessageLog::create([
            'user_id' => $userId,
            'message' => $direction === 'incoming' ? $message : null,
            'response' => $direction === 'outgoing' ? $message : null,
            'direction' => $direction,
            'message_type' => 'text',  // Default to text
            'discord_message_id' => $discordMessageId,
        ]);
    }

    /**
     * Process the message and generate a response using OpenRouter AI
     */
    protected function processMessage(User $user, string $message): string
    {
        // Check if this is a new user that needs onboarding
        if ($this->isNewUser($user)) {
            return $this->handleOnboarding($user, $message);
        }
        
        // Get the user's conversation history
        $conversation = Conversation::where('user_id', $user->id)->first();
        
        if (!$conversation) {
            $conversation = Conversation::create([
                'user_id' => $user->id,
                'prompt_history' => json_encode([]),
                'last_prompt_time' => now(),
            ]);
        }
        
        // Update conversation history
        $promptHistory = json_decode($conversation->prompt_history, true) ?: [];
        
        // Add the user's message to the history
        $promptHistory[] = ['role' => 'user', 'content' => $message];
        
        // Prepare the messages for OpenRouter
        $messages = [
            ['role' => 'system', 'content' => $this->getSystemPrompt($user)],
        ];
        
        // Add the last few messages from history (limit to 5 for context window)
        $recentHistory = array_slice($promptHistory, -5);
        $messages = array_merge($messages, $recentHistory);
        
        // Generate response using OpenRouter
        $response = $this->openRouterService->generateResponse($messages);
        
        // Add the assistant's response to history
        $promptHistory[] = ['role' => 'assistant', 'content' => $response];
        
        // Update the conversation in the database
        $conversation->update([
            'prompt_history' => json_encode($promptHistory),
            'last_prompt_time' => now(),
        ]);
        
        return $response;
    }

    /**
     * Send a Discord response to the user
     */
    protected function sendDiscordResponse(string $channelId, string $message): void
    {
        // This method would integrate with the Discord API
        // For now, we'll just log the message that would be sent
        Log::info('Sending Discord message', [
            'channel_id' => $channelId,
            'message' => $message
        ]);
        
        // Use Discord service to send the message
        try {
            $botToken = config('services.discord.bot_token');
            
            if (empty($botToken)) {
                Log::error('Discord bot token not configured');
                return;
            }
            
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bot ' . $botToken,
                'Content-Type' => 'application/json',
            ])->post("https://discord.com/api/v10/channels/{$channelId}/messages", [
                'content' => $message,
            ]);
            
            if (!$response->successful()) {
                Log::error('Discord message error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Discord message exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check if this is a new user that needs onboarding
     */
    protected function isNewUser(User $user): bool
    {
        // Check if the user has preferences set
        if (empty($user->preferences)) {
            return true;
        }
        
        // Check if this is their first interaction (created within the last minute)
        if ($user->created_at->diffInMinutes(now()) < 1) {
            return true;
        }
        
        return false;
    }

    /**
     * Handle the onboarding process for new users
     */
    protected function handleOnboarding(User $user, string $message): string
    {
        // Get current preferences or initialize empty array
        $preferences = json_decode($user->preferences, true) ?: [];
        
        // If this is the very first message, send welcome message
        if (empty($preferences)) {
            return "Welcome to HealthTipsDaily! 🌿 I'm your personal health assistant. "
                . "I can provide daily health tips, answer questions, and help you track your wellness journey. "
                . "To get started, please tell me your name.";
        }
        
        // If we don't have the name yet, save it
        if (!isset($preferences['name'])) {
            $preferences['name'] = $message;
            $user->update([
                'name' => $message,
                'preferences' => json_encode($preferences)
            ]);
            
            return "Nice to meet you, {$message}! What health topics are you most interested in? "
                . "For example: nutrition, fitness, mental health, sleep, etc.";
        }
        
        // If we don't have interests yet, save them
        if (!isset($preferences['interests'])) {
            $preferences['interests'] = $message;
            $user->update(['preferences' => json_encode($preferences)]);
            
            return "Great! I'll focus on {$message}. What time would you prefer to receive daily tips? "
                . "Please specify in 24-hour format (e.g., 08:00 or 18:30).";
        }
        
        // If we don't have preferred time yet, save it
        if (!isset($preferences['preferred_time'])) {
            // Validate time format
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $message)) {
                return "Sorry, I didn't understand that time format. Please use HH:MM format (e.g., 08:00 or 18:30).";
            }
            
            $preferences['preferred_time'] = $message;
            $user->update(['preferences' => json_encode($preferences)]);
            
            // Create a scheduled message for this user
            ScheduledMessage::create([
                'user_id' => $user->id,
                'preferred_time' => $message,
                'is_active' => true
            ]);
            
            return "Perfect! I'll send you daily health tips at {$message}. You're all set up now! "
                . "Feel free to ask me any health-related questions anytime.";
        }
        
        // If we get here, onboarding is complete
        return $this->processMessage($user, $message);
    }

    /**
     * Generate a personalized health tip for the user
     */
    protected function generateHealthTip(User $user): string
    {
        $preferences = json_decode($user->preferences, true) ?: [];
        $name = $preferences['name'] ?? $user->name ?? 'there';
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
     * Get the system prompt for the AI based on user preferences
     */
    protected function getSystemPrompt(User $user): string
    {
        $preferences = json_decode($user->preferences, true) ?: [];
        $name = $preferences['name'] ?? $user->name ?? 'there';
        $interests = $preferences['interests'] ?? 'health topics';
        
        return "You are HealthTipsDaily, a friendly and knowledgeable health assistant. "
            . "You're chatting with {$name}, who is interested in {$interests}. "
            . "Provide helpful, evidence-based health information in a conversational tone. "
            . "Keep responses concise (under 3 paragraphs) and easy to understand. "
            . "If asked about serious medical concerns, remind the user to consult a healthcare professional. "
            . "Current date: " . now()->toDateString();
    }
}
