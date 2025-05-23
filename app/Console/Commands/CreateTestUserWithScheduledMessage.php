<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\ScheduledMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTestUserWithScheduledMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-test-user 
                            {--discord-id= : Discord user ID for the test user}
                            {--current-time : Set the scheduled message time to the current time}
                            {--time= : Specific time for the scheduled message (format: HH:MM)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test user with a scheduled message';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Creating test user with scheduled message...');
        
        // Get the Discord ID
        $discordId = $this->option('discord-id');
        if (empty($discordId)) {
            $discordId = 'test_' . Str::random(8);
            $this->info("No Discord ID provided, using generated ID: {$discordId}");
        }
        
        // Determine the scheduled time
        $now = Carbon::now();
        if ($this->option('current-time')) {
            $scheduledTime = $now->format('H:i');
        } elseif ($this->option('time')) {
            $scheduledTime = $this->option('time');
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $scheduledTime)) {
                $this->error('Invalid time format. Please use HH:MM format.');
                return Command::FAILURE;
            }
        } else {
            // Default to current time + 1 minute
            $scheduledTime = $now->addMinute()->format('H:i');
        }
        
        // Create or update the user
        $user = User::where('discord_id', $discordId)->first();
        
        if ($user) {
            $this->info("Updating existing user with Discord ID: {$discordId}");
            $user->update([
                'last_interaction_at' => now(),
            ]);
        } else {
            $this->info("Creating new user with Discord ID: {$discordId}");
            $user = User::create([
                'name' => 'Test User',
                'email' => $discordId . '@discord.example.com',
                'password' => bcrypt(Str::random(16)),
                'discord_id' => $discordId,
                'first_seen_at' => now(),
                'last_interaction_at' => now(),
                'preferences' => json_encode([
                    'name' => 'Test User',
                    'interests' => 'health, fitness, nutrition',
                    'preferred_time' => $scheduledTime
                ])
            ]);
            
            // Create a conversation record for the new user
            Conversation::create([
                'user_id' => $user->id,
                'prompt_history' => json_encode([]),
                'last_prompt_time' => now(),
            ]);
        }
        
        // Create or update the scheduled message
        $scheduledMessage = ScheduledMessage::updateOrCreate(
            ['user_id' => $user->id],
            [
                'preferred_time' => $scheduledTime,
                'is_active' => true,
                'last_sent_at' => null // Set to null to ensure it will be sent
            ]
        );
        
        $this->info("Created scheduled message for user ID {$user->id} at time {$scheduledTime}");
        $this->info("Run 'php artisan app:send-daily-health-tips' to test sending the message");
        
        return Command::SUCCESS;
    }
}
