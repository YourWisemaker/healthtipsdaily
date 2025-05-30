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
        // Check if columns don't already exist to avoid conflicts with chatbot_tables migration
        if (!Schema::hasColumn('users', 'phone_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone_number')->nullable()->unique()->after('email');
            });
        }
        
        if (!Schema::hasColumn('users', 'preferences')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('preferences')->nullable()->after('phone_number')->comment('User preferences for health topics and notifications');
            });
        }
        
        if (!Schema::hasColumn('users', 'first_seen_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('first_seen_at')->nullable()->after(Schema::hasColumn('users', 'preferences') ? 'preferences' : 'phone_number');
            });
        }
        
        if (!Schema::hasColumn('users', 'last_interaction_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_interaction_at')->nullable()->after('first_seen_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop columns for MySQL database
        Schema::table('users', function (Blueprint $table) {
            // Only drop columns if they exist
            $columns = [];
            
            if (Schema::hasColumn('users', 'phone_number')) {
                $columns[] = 'phone_number';
            }
            
            if (Schema::hasColumn('users', 'preferences')) {
                $columns[] = 'preferences';
            }
            
            if (Schema::hasColumn('users', 'first_seen_at')) {
                $columns[] = 'first_seen_at';
            }
            
            if (Schema::hasColumn('users', 'last_interaction_at')) {
                $columns[] = 'last_interaction_at';
            }
            
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};