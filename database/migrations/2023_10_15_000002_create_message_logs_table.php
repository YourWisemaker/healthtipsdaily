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
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('message')->nullable()->comment('Incoming message from user');
            $table->text('response')->nullable()->comment('Outgoing response to user');
            $table->string('direction')->comment('incoming or outgoing');
            $table->string('message_type')->default('text')->comment('Type of message (text, image, etc.)');
            $table->string('intent')->nullable()->comment('Detected intent of the message');
            $table->string('whatsapp_message_id')->nullable()->comment('ID from WhatsApp provider');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};