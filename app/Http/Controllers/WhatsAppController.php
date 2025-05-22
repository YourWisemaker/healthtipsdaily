<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\MessageLog;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\OpenRouterService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    protected $openRouterService;

    public function __construct(OpenRouterService $openRouterService)
    {
        $this->openRouterService = $openRouterService;
    }

    /**
     * Handle incoming WhatsApp webhook
     */
    public function webhook(Request $request)
    {
        Log::info('WhatsApp webhook received', ['payload' => $request->all()]);

        try {
            // Extract message data from the webhook payload
            // This structure will depend on your WhatsApp provider (Twilio, Meta, etc.)
            $messageData = $this->extractMessageData($request);
            
            if (empty($messageData)) {
                return response()->json(['status' => 'success']);
            }

            // Find or create user by phone number
            $user = $this->findOrCreateUser($messageData['phone']);
            
            // Log the incoming message
            $messageLog = $this->logMessage($user->id, $messageData['message'], 'incoming', $messageData['message_id']);
            
            // Process the message and generate a response
            $response = $this->processMessage($user, $messageData['message']);
            
            // Log the outgoing response
            $this->logMessage($user->id, $response, 'outgoing');
            
            // Send the response back to WhatsApp
            // This would typically call your WhatsApp provider's API
            $this->sendWhatsAppResponse($messageData['phone'], $response);
            
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Error processing WhatsApp webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Verify the WhatsApp webhook URL (required by some providers)
     */
    public function verifyWebhook(Request $request): Response
    {
        // This method handles the webhook verification challenge
        // The implementation depends on your WhatsApp provider
        
        // Example for Meta/Facebook WhatsApp Business API
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');
        
        $verifyToken = config('services.whatsapp.verify_token');
        
        if ($mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200);
        }
        
        return response('Verification failed', 403);
    }

    /**
     * Extract message data from the webhook payload
     */
    protected function extractMessageData(Request $request): array
    {
        // This method needs to be customized based on your WhatsApp provider
        // Example for a generic webhook structure
        $data = $request->all();
        
        // Return empty array if this is not a message event
        if (!isset($data['message']) || !isset($data['from'])) {
            return [];
        }
        
        return [
            'message' => $data['message'] ?? '',
            'phone' => $data['from'] ?? '',
            'message_id' => $data['message_id'] ?? null,
        ];
    }

    /**
     * Find or create a user by phone number
     */
    protected function findOrCreateUser(string $phoneNumber): User
    {
        $user = User::where('phone_number', $phoneNumber)->first();
        
        if (!$user) {
            $user = User::create([
                'name' => 'WhatsApp User',  // Default name
                'phone_number' => $phoneNumber,
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
    protected function logMessage(int $userId, string $message, string $direction, ?string $whatsappMessageId = null): MessageLog
    {
        return MessageLog::create([
            'user_id' => $userId,
            'message' => $direction === 'incoming' ? $message : null,
            'response' => $direction === 'outgoing' ? $message : null,
            'direction' => $direction,
            'message_type' => 'text',  // Default to text
            'whatsapp_message_id' => $whatsappMessageId,
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
     * Send a WhatsApp response to the user
     */
    protected function sendWhatsAppResponse(string $phoneNumber, string $message): void
    {
        // This method would integrate with your WhatsApp provider's API
        // For example, using Twilio, Meta Business API, etc.
        
        // For now, we'll just log the message that would be sent
        Log::info('Sending WhatsApp message', [
            'to' => $phoneNumber,
            'message' => $message
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
     * Get the system prompt for the AI based on user preferences
     */
    protected function getSystemPrompt(User $user): string
    {
        $preferences = json_decode($user->preferences, true) ?: [];
        $name = $preferences['name'] ?? 'there';
        $interests = $preferences['interests'] ?? 'health topics';
        
        return "You are HealthTipsDaily, a friendly and knowledgeable health assistant. "
            . "You're chatting with {$name}, who is interested in {$interests}. "
            . "Provide helpful, evidence-based health information in a conversational tone. "
            . "Keep responses concise (under 3 paragraphs) and easy to understand. "
            . "If asked about serious medical concerns, remind the user to consult a healthcare professional. "
            . "Current date: " . now()->toDateString();
    }
}