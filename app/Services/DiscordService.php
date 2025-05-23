<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordService
{
    protected $webhookUrl;
    protected $botToken;

    public function __construct()
    {
        $this->webhookUrl = config('services.discord.webhook_url');
        $this->botToken = config('services.discord.bot_token');
    }

    /**
     * Send a message to a Discord channel using a webhook
     *
     * @param string $message The message to send
     * @param string|null $webhookUrl Optional custom webhook URL
     * @return bool Whether the message was sent successfully
     */
    public function sendWebhookMessage(string $message, ?string $webhookUrl = null): bool
    {
        $url = $webhookUrl ?? $this->webhookUrl;
        
        if (empty($url)) {
            Log::error('Discord webhook URL not configured');
            return false;
        }
        
        try {
            $response = Http::post($url, [
                'content' => $message,
            ]);
            
            if ($response->successful()) {
                Log::info('Discord webhook message sent successfully');
                return true;
            } else {
                Log::error('Discord webhook error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Discord webhook exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Send a direct message to a Discord user using the bot API
     *
     * @param string $userId The Discord user ID to send the message to
     * @param string $message The message to send
     * @return bool Whether the message was sent successfully
     */
    public function sendDirectMessage(string $userId, string $message): bool
    {
        if (empty($this->botToken)) {
            Log::error('Discord bot token not configured');
            return false;
        }
        
        try {
            // First create a DM channel with the user
            $channelResponse = Http::withHeaders([
                'Authorization' => 'Bot ' . $this->botToken,
                'Content-Type' => 'application/json',
            ])->post('https://discord.com/api/v10/users/@me/channels', [
                'recipient_id' => $userId,
            ]);
            
            if (!$channelResponse->successful()) {
                Log::error('Discord DM channel creation error', [
                    'status' => $channelResponse->status(),
                    'body' => $channelResponse->body(),
                ]);
                return false;
            }
            
            $channelData = $channelResponse->json();
            $channelId = $channelData['id'];
            
            // Then send the message to the DM channel
            $messageResponse = Http::withHeaders([
                'Authorization' => 'Bot ' . $this->botToken,
                'Content-Type' => 'application/json',
            ])->post("https://discord.com/api/v10/channels/{$channelId}/messages", [
                'content' => $message,
            ]);
            
            if ($messageResponse->successful()) {
                Log::info('Discord direct message sent successfully');
                return true;
            } else {
                Log::error('Discord direct message error', [
                    'status' => $messageResponse->status(),
                    'body' => $messageResponse->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Discord direct message exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}
