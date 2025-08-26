<?php
// ========================================
// app/Models/Notification.php - Updated sesuai migration
// ========================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    protected $fillable = [
        'recipient_nik',
        'recipient_name',
        'sender_nik', 
        'sender_name',
        'title',
        'message',
        'type',
        'related_type',
        'related_id',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // Type constants sesuai migration
    const TYPE_APPROVAL_REQUEST = 'approval_request';
    const TYPE_APPROVAL_APPROVED = 'approval_approved';
    const TYPE_APPROVAL_REJECTED = 'approval_rejected';
    const TYPE_DISCUSSION_STARTED = 'discussion_started';
    const TYPE_DISCUSSION_CLOSED = 'discussion_closed';
    const TYPE_AGREEMENT_CREATED = 'agreement_created';
    const TYPE_DOCUMENT_COMPLETED = 'document_completed';

    // Relationships
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_nik', 'nik');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_nik', 'nik');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeByRecipient($query, $nik)
    {
        return $query->where('recipient_nik', $nik);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Helper methods
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    public function isRead(): bool
    {
        return $this->is_read;
    }

    public function getTypeLabel(): string
    {
        $labels = [
            self::TYPE_APPROVAL_REQUEST => 'Approval Request',
            self::TYPE_APPROVAL_APPROVED => 'Approval Approved',
            self::TYPE_APPROVAL_REJECTED => 'Approval Rejected',
            self::TYPE_DISCUSSION_STARTED => 'Discussion Started',
            self::TYPE_DISCUSSION_CLOSED => 'Discussion Closed',
            self::TYPE_AGREEMENT_CREATED => 'Agreement Created',
            self::TYPE_DOCUMENT_COMPLETED => 'Document Completed',
        ];

        return $labels[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function getTypeBadgeColor(): string
    {
        return match($this->type) {
            self::TYPE_APPROVAL_REQUEST => 'warning',
            self::TYPE_APPROVAL_APPROVED => 'success',
            self::TYPE_APPROVAL_REJECTED => 'danger',
            self::TYPE_DISCUSSION_STARTED => 'info',
            self::TYPE_DISCUSSION_CLOSED => 'primary',
            self::TYPE_AGREEMENT_CREATED => 'success',
            self::TYPE_DOCUMENT_COMPLETED => 'success',
            default => 'gray'
        };
    }
}
