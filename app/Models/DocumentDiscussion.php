<?php

// app/Models/DocumentDiscussion.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentDiscussion extends Model
{
    protected $fillable = [
        'document_request_id',
        'status',
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
        'closure_reason',
        'requires_finance_input',
        'finance_participated',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'requires_finance_input' => 'boolean',
        'finance_participated' => 'boolean',
    ];

    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class, 'document_request_id', 'document_request_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function canBeClosed(): bool
    {
        // Check if finance has participated (if required)
        if ($this->requires_finance_input && !$this->finance_participated) {
            return false;
        }

        return $this->isOpen();
    }

    public function getParticipantsAttribute(): array
    {
        return $this->comments()
            ->select('user_role', 'user_name', 'user_nik')
            ->distinct()
            ->get()
            ->groupBy('user_role')
            ->map(function ($participants) {
                return $participants->pluck('user_name')->unique()->values();
            })
            ->toArray();
    }

    public function getTotalCommentsAttribute(): int
    {
        return $this->comments()->count();
    }

    public function getUnresolvedCommentsAttribute(): int
    {
        return $this->comments()->unresolved()->count();
    }
}

// Update DocumentRequest.php - Add relationships
// Add these methods to existing DocumentRequest model:

