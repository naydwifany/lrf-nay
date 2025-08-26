<?php
// app/Models/AgreementApproval.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgreementApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'agreement_overview_id',
        'approver_nik',
        'approver_name',
        'approval_type',
        'status',
        'comments',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    // Status constants
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_REVISION_REQUESTED = 'revision_requested';

    // Approval type constants
    const TYPE_HEAD = 'Head Approval';
    const TYPE_GM = 'GM Approval';
    const TYPE_FINANCE = 'Finance Approval';
    const TYPE_LEGAL = 'Legal Approval';
    const TYPE_DIRECTOR1 = 'Director 1 Approval';
    const TYPE_DIRECTOR2 = 'Director 2 Approval';

    // Relationships
    public function agreementOverview(): BelongsTo
    {
        return $this->belongsTo(AgreementOverview::class, 'agreement_overview_id');
    }

    // Accessors
    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_REVISION_REQUESTED => 'warning',
            default => 'gray'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_REVISION_REQUESTED => 'Revision Requested',
            default => 'Unknown'
        };
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeRevisionRequested($query)
    {
        return $query->where('status', self::STATUS_REVISION_REQUESTED);
    }

    public function scopeByApprover($query, $approverNik)
    {
        return $query->where('approver_nik', $approverNik);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('approval_type', $type);
    }
}