<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'user_id', 'title', 'mdm_vendor', 'data_platform',
        'financial_model', 'domains', 'extensions', 'pii_redacted', 'pinned',
    ];

    protected $casts = [
        'domains' => 'array',
        'extensions' => 'array',
        'pii_redacted' => 'boolean',
        'pinned' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** The locked stack as a structured array, for retrieval filtering and prompts. */
    public function lockedStack(): array
    {
        return [
            'mdm_vendor' => $this->mdm_vendor,
            'data_platform' => $this->data_platform,
            'financial_model' => $this->financial_model,
            'domains' => $this->domains ?: ['general'],
            // Included extensions (verticals/add-ons). null = not chosen, [] = core-only.
            'extensions' => $this->extensions,
        ];
    }
}
