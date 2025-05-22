<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'message',
        'response',
        'direction',
        'message_type',
        'intent',
        'whatsapp_message_id',
    ];

    /**
     * Get the user that owns the message log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}