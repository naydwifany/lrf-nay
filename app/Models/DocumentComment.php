<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_request_id',
        'user_id',
        'user_nik',
        'user_name', 
        'user_role',
        'comment',
        'parent_id',
        'is_forum_closed',
        'forum_closed_at',
        'forum_closed_by_nik',
        'forum_closed_by_name',
        'is_resolved',
        'resolved_at',
        'resolved_by_nik',
        // Note: 'attachments' column exists in table but we use relationship instead
    ];

    protected $casts = [
        'forum_closed_at' => 'datetime',
        'resolved_at' => 'datetime',
        'is_forum_closed' => 'boolean',
        'is_resolved' => 'boolean',
    ];

    // CRITICAL: Use specific name to avoid conflict with 'attachments' column
    public function attachmentFiles(): HasMany
    {
        return $this->hasMany(DocumentCommentAttachment::class, 'document_comment_id', 'id');
    }

    // Alternative accessor to maintain compatibility
    public function getAttachmentsAttribute()
    {
        // If relationship is loaded, return it; otherwise lazy load
        if ($this->relationLoaded('attachmentFiles')) {
            return $this->attachmentFiles;
        }
        
        return $this->attachmentFiles()->get();
    }

    // Other relationships
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class, 'document_request_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class, 'document_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_nik', 'nik');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DocumentComment::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DocumentComment::class, 'parent_id');
    }

    // Scopes
    public function scopeRootComments($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeNotClosed($query)
    {
        return $query->where('is_forum_closed', false);
    }

    // Helper methods
    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function hasReplies(): bool
    {
        return $this->replies()->exists();
    }

    public function getRepliesCount(): int
    {
        return $this->replies()->count();
    }

    public function hasAttachments(): bool
    {
        return $this->attachmentFiles()->exists();
    }

    public function getAttachmentsCount(): int
    {
        return $this->attachmentFiles()->count();
    }
}