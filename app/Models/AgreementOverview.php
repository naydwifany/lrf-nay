<?php
// app/Models/AgreementOverview.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgreementOverview extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_request_id',
        'nomor_dokumen',
        'tanggal_ao',
        'pic',
        'counterparty',
        'deskripsi',
        'resume',
        'ketentuan_dan_mekanisme',
        'start_date_jk',
        'end_date_jk',
        'nik',
        'nama',
        'jabatan',
        'divisi',
        'direktorat',
        'level',
        'director1_nik',
        'director1_name',
        'director2_nik',
        'director2_name',
        'status',
        'is_draft',
        'parties',
        'terms',
        'risks',
        'submitted_at',
        'completed_at'
    ];

    protected $casts = [
        'tanggal_ao' => 'date',
        'start_date_jk' => 'date',
        'end_date_jk' => 'date',
        'is_draft' => 'boolean',
        'parties' => 'array',
        'terms' => 'array',
        'risks' => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_HEAD = 'pending_head';
    const STATUS_PENDING_GM = 'pending_gm';
    const STATUS_PENDING_FINANCE = 'pending_finance';
    const STATUS_PENDING_LEGAL = 'pending_legal';
    const STATUS_PENDING_DIRECTOR1 = 'pending_director1';
    const STATUS_PENDING_DIRECTOR2 = 'pending_director2';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_REDISCUSS = 'rediscuss';

    // FIXED: Add missing methods
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_HEAD => 'Pending Head',
            self::STATUS_PENDING_GM => 'Pending GM',
            self::STATUS_PENDING_FINANCE => 'Pending Finance',
            self::STATUS_PENDING_LEGAL => 'Pending Legal',
            self::STATUS_PENDING_DIRECTOR1 => 'Pending Director 1',
            self::STATUS_PENDING_DIRECTOR2 => 'Pending Director 2',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_REDISCUSS => 'Back to Discussion',
        ];
    }

    public static function getStatusColors(): array
    {
        return [
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PENDING_HEAD => 'warning',
            self::STATUS_PENDING_GM => 'warning',
            self::STATUS_PENDING_FINANCE => 'info',
            self::STATUS_PENDING_LEGAL => 'info',
            self::STATUS_PENDING_DIRECTOR1 => 'primary',
            self::STATUS_PENDING_DIRECTOR2 => 'primary',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_REDISCUSS => 'gray',
        ];
    }

    // Business logic methods
    public function canBeEdited(): bool
    {
        return $this->is_draft || $this->status === self::STATUS_DRAFT;
    }

    public function canBeSubmitted(): bool
    {
        return $this->is_draft && $this->status === self::STATUS_DRAFT;
    }

    public function isDraft(): bool
    {
        return $this->is_draft;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    // Relationships
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AgreementApproval::class, 'agreement_overview_id');
    }

    // Accessor for duration calculation
    public function getDurationAttribute(): ?string
    {
        if (!$this->start_date_jk || !$this->end_date_jk) {
            return null;
        }

        $days = $this->start_date_jk->diffInDays($this->end_date_jk);
        
        if ($days < 30) {
            return $days . ' days';
        } elseif ($days < 365) {
            $months = round($days / 30, 1);
            return $months . ' months';
        } else {
            $years = round($days / 365, 1);
            return $years . ' years';
        }
    }

    // Scope for filtering
    public function scopeDraft($query)
    {
        return $query->where('is_draft', true);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('is_draft', false);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByUser($query, $nik)
    {
        return $query->where('nik', $nik);
    }

    // Get current approval step info
public function getCurrentApprovalStep(): string
{
    return match($this->status) {
        'draft' => 'Not submitted',
        'pending_head' => 'Waiting for Head approval',
        'pending_gm' => 'Waiting for GM approval', 
        'pending_finance' => 'Waiting for Finance approval',
        'pending_legal' => 'Waiting for Legal approval',
        'pending_director1' => 'Waiting for Director 1 approval',
        'pending_director2' => 'Waiting for Director 2 approval',
        'approved' => 'Fully approved',
        'rejected' => 'Rejected',
        default => 'Unknown status'
    };
}

// Get approval progress percentage
public function getApprovalProgress(): int
{
    $steps = [
        'draft' => 0,
        'pending_head' => 10,
        'pending_gm' => 25,
        'pending_finance' => 45,
        'pending_legal' => 65,
        'pending_director1' => 80,
        'pending_director2' => 90,
        'approved' => 100,
        'rejected' => 0,
    ];
    
    return $steps[$this->status] ?? 0;
}

// Check if user can approve at current step
public function canUserApproveAtCurrentStep($userNik, $userRole): bool
{
    $approvalRules = [
        'pending_head' => ['HEAD', 'MANAGER', 'KEPALA'],
        'pending_gm' => ['GM', 'GENERAL MANAGER'],
        'pending_finance' => ['FINANCE', 'CFO'],
        'pending_legal' => ['LEGAL', 'HUKUM'],
        'pending_director1' => [$this->director1_nik],
        'pending_director2' => [$this->director2_nik],
    ];

    if (!isset($approvalRules[$this->status])) {
        return false;
    }

    $allowedApprovers = $approvalRules[$this->status];

    // Check NIK match
    if (in_array($userNik, $allowedApprovers)) {
        return true;
    }

    // Check role match
    foreach ($allowedApprovers as $role) {
        if (stripos($userRole, $role) !== false) {
            return true;
        }
    }

    return false;
}

// Get approval history with details
public function getApprovalHistory(): array
{
    $approvals = $this->approvals()->orderBy('approved_at')->get();
    
    return $approvals->map(function ($approval) {
        return [
            'step' => $approval->approval_type,
            'approver' => $approval->approver_name,
            'status' => $approval->status,
            'comments' => $approval->comments,
            'date' => $approval->approved_at,
        ];
    })->toArray();
}
}