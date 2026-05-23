<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'role', 'content', 'citations',
        'confidence', 'model', 'usage',
    ];

    protected $casts = [
        'content' => 'array',
        'citations' => 'array',
        'usage' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
