<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterDiscordCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:register-commands';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register slash commands with Discord';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Registering Discord slash commands...');
        
        $botToken = config('services.discord.bot_token');
        $applicationId = config('services.discord.application_id', '');
        
        if (empty($botToken) || empty($applicationId)) {
            $this->error('Discord bot token or application ID not configured.');
            return Command::FAILURE;
        }
        
        // Define the commands to register
        $commands = [
            [
                'name' => 'healthtip',
                'description' => 'Get a personalized health tip',
                'type' => 1, // CHAT_INPUT
            ],
            [
                'name' => 'subscribe',
                'description' => 'Subscribe to daily health tips',
                'type' => 1, // CHAT_INPUT
                'options' => [
                    [
                        'name' => 'time',
                        'description' => 'Preferred time to receive tips (HH:MM format)',
                        'type' => 3, // STRING
                        'required' => true,
                    ],
                ],
            ],
        ];
        
        try {
            // Register global commands
            $response = Http::withHeaders([
                'Authorization' => 'Bot ' . $botToken,
                'Content-Type' => 'application/json',
            ])->put(
                "https://discord.com/api/v10/applications/{$applicationId}/commands",
                $commands
            );
            
            if ($response->successful()) {
                $this->info('Discord commands registered successfully!');
                return Command::SUCCESS;
            } else {
                $this->error('Failed to register Discord commands: ' . $response->body());
                Log::error('Discord command registration error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Exception while registering Discord commands: ' . $e->getMessage());
            Log::error('Discord command registration exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
