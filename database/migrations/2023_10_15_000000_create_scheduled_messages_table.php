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
        // Skip if the table will be created by the chatbot_tables migration
        if (!Schema::hasTable('scheduled_messages')) {
            Schema::create('scheduled_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->unsignedBigInteger('topic_id')->nullable(); // Remove foreign key constraint
                $table->time('preferred_time')->comment('Time of day when the user wants to receive messages');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_messages');
    }
};