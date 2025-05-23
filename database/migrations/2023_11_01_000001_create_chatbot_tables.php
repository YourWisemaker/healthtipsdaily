<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Extend users table with additional fields
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number')->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->nullable()->after('phone_number');
            }
            if (!Schema::hasColumn('users', 'language')) {
                $table->string('language')->default('en')->after('timezone');
            }
            if (!Schema::hasColumn('users', 'first_seen_at')) {
                $table->timestamp('first_seen_at')->nullable()->after('language');
            }
            if (!Schema::hasColumn('users', 'last_interaction_at')) {
                $table->timestamp('last_interaction_at')->nullable()->after('first_seen_at');
            }
            if (!Schema::hasColumn('users', 'opt_in_status')) {
                $table->boolean('opt_in_status')->default(true)->after('last_interaction_at');
            }
            if (!Schema::hasColumn('users', 'preferences')) {
                $table->json('preferences')->nullable()->after('opt_in_status');
            }
        });

        // Create message_logs table
        if (!Schema::hasTable('message_logs')) {
            Schema::create('message_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->text('message')->nullable();
                $table->text('response')->nullable();
                $table->enum('direction', ['incoming', 'outgoing']);
                $table->string('message_type')->default('text');
                $table->string('intent')->nullable();
                $table->string('whatsapp_message_id')->nullable();
                $table->timestamps();
            });
        }

        // Create daily_entries table for tracking user routines
        if (!Schema::hasTable('daily_entries')) {
            Schema::create('daily_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->date('entry_date');
                $table->string('entry_type'); // e.g., mood, task, gratitude
                $table->text('value');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // Create conversations table for AI context memory
        if (!Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->json('prompt_history')->nullable();
                $table->timestamp('last_prompt_time')->nullable();
                $table->timestamps();
            });
        }

        // Create topics table for daily content
        if (!Schema::hasTable('topics')) {
            Schema::create('topics', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Create scheduled_messages table
        if (!Schema::hasTable('scheduled_messages')) {
            Schema::create('scheduled_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
                $table->time('preferred_time');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Create settings table
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Create feedback table
        if (!Schema::hasTable('feedback')) {
            Schema::create('feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->integer('rating')->nullable();
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('scheduled_messages');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('daily_entries');
        Schema::dropIfExists('message_logs');
        
        // Remove added columns from users table
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'phone_number',
                'timezone',
                'language',
                'first_seen_at',
                'last_interaction_at',
                'opt_in_status',
                'preferences'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};