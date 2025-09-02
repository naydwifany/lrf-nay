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
    const STATUS_PENDING = 'pending';

    // Approval type constants
    const TYPE_SUPERVISOR       = 'supervisor';
    const TYPE_MANAGER          = 'manager';
    const TYPE_SENIOR_MANAGER   = 'senior_manager';
    const TYPE_GENERAL_MANAGER  = 'general_manager';
    const TYPE_ADMIN_LEGAL      = 'admin_legal';
    const TYPE_LEGAL            = 'legal';
    const TYPE_HEAD_LEGAL       = 'head_legal';
    const TYPE_FINANCE          = 'finance';
    const TYPE_HEAD_FINANCE     = 'head_finance';
    const TYPE_DIVISION_MANAGER = 'division_manager';
    const TYPE_DIVISION_SENIOR_MANAGER  = 'division_senior_manager';
    const TYPE_DIVISION_GENERAL_MANAGER = 'division_general_manager';
    const TYPE_DIRECTOR_SUPERVISOR  = 'director_supervisor';
    const TYPE_SELECTED_DIRECTOR    = 'selected_director';

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

    /**
     * Get approval order mapping
     *
     * @param string|null $previousApprovalType
     * @return array
     */
    public static function getApprovalOrder(?string $previousApprovalType = null): array
    {
        $orders = [
            self::TYPE_MANAGER          => 1,
            self::TYPE_SENIOR_MANAGER   => 1,
            self::TYPE_GENERAL_MANAGER  => 2,
            self::TYPE_HEAD_FINANCE     => 3,
            self::TYPE_FINANCE          => 3,
            self::TYPE_HEAD_LEGAL       => 4,
            self::TYPE_LEGAL            => 4,
            self::TYPE_ADMIN_LEGAL      => 4,
            self::TYPE_SELECTED_DIRECTOR=> 5,
        ];

        // Rule khusus: kalau step 1 di-approve oleh Senior Manager → skip GM
        if ($previousApprovalType === self::TYPE_SENIOR_MANAGER) {
            unset($orders[self::TYPE_GENERAL_MANAGER]);
        }

        return $orders;
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