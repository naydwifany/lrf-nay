<?php
// app/Models/DocumentRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasActivityLog;

class DocumentRequest extends Model
{
    use HasFactory, HasActivityLog;

    protected $fillable = [
        'nik', 'nik_atasan', 'nama', 'jabatan', 'divisi', 'unit_bisnis', 'dept',
        'direktorat', 'seksi', 'subseksi', 'data', 'nomor_dokumen', 'title', 
        'description', 'tipe_dokumen', 'doc_filter', 'priority', 'status', 
        'is_draft', 'dokumen_utama', 'akta_pendirian', 'ktp_direktur', 
        'akta_perubahan', 'surat_kuasa', 'npwp', 'nib', 'lama_perjanjian_surat', 
        'syarat_ketentuan_pembayaran', 'kewajiban_mitra', 'hak_mitra', 
        'kewajiban_eci', 'hak_eci', 'pajak', 'ketentuan_lain', 'submitted_at', 
        'completed_at', 'metadata','created_by', 'submitted_by'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_draft' => 'boolean',
        'metadata' => 'json',
        'data' => 'json'
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_PENDING_SUPERVISOR = 'pending_supervisor';
    const STATUS_PENDING_GM = 'pending_gm';
    const STATUS_PENDING_LEGAL_ADMIN = 'pending_legal_admin';  // UBAH INI
    const STATUS_IN_DISCUSSION = 'in_discussion';               // UBAH INI
    const STATUS_AGREEMENT_CREATION = 'agreement_creation';
    const STATUS_AGREEMENT_APPROVAL = 'agreement_approval';
    const STATUS_COMPLETED = 'approved';                        // UBAH INI
    const STATUS_REJECTED = 'rejected';
    const STATUS_PENDING_SM = 'pending_senior_manager';
    const STATUS_DISCUSSION_CLOSED = 'discussion_closed';
    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'nik', 'nik');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'nik_atasan', 'nik');
    }

    public function doctype()
    {
        return $this->belongsTo(MasterDocument::class, 'tipe_dokumen');
    }

    public function approvals()
    {
        return $this->hasMany(DocumentApproval::class);
    }

    public function agreementOverview()
    {
        return $this->hasOne(AgreementOverview::class, 'document_request_id');
    }

    public function forumStatus()
    {
        return $this->hasOne(ForumDiskusiStatus::class, 'lrf_doc_id');
    }

    public function attachments()
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable');
    }

    

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('is_draft', true);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('is_draft', false);
    }

    public function scopeByUser($query, $nik)
    {
        return $query->where('nik', $nik);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }




public function activeComments(): HasMany
{
    return $this->hasMany(DocumentComment::class)->where('is_forum_closed', false);
}

public function closeDiscussionForum(): bool
{
    // Hanya Head Legal yang bisa close
    if (!auth()->user()->hasRole('head_legal')) {
        return false;
    }
    
    // Pastikan Finance sudah participate
    if (!$this->hasFinanceParticipated()) {
        return false;
    }
    
    // Close forum dengan update comments
    $this->comments()->update(['is_forum_closed' => true]);
    
    // Update status document ke agreement creation
    $this->update([
        'status' => self::STATUS_AGREEMENT_CREATION
    ]);
    
    return true;
}

public function canCreateAgreementOverview(): bool
{
    return $this->status === self::STATUS_AGREEMENT_CREATION;
}

// Tambahkan konstanta status baru jika belum ada

public function hasActiveDiscussion(): bool
{
    return $this->status === self::STATUS_IN_DISCUSSION && 
           $this->comments()->where('is_forum_closed', false)->exists();
}

    

    public function canStartDiscussion(): bool
    {
        return $this->status === self::STATUS_IN_DISCUSSION && !$this->hasActiveDiscussion();
    }

public function getCurrentApprover()
{
    $approval = $this->approvals()
        ->where('status', DocumentApproval::STATUS_PENDING)
        ->orderBy('order')
        ->first();
        
    if (!$approval) return null;
    
    return User::where('nik', $approval->approver_nik)->first();
}

public function getNextApprovalStep()
{
    switch ($this->status) {
        case self::STATUS_SUBMITTED:
        case self::STATUS_PENDING_SUPERVISOR:
            return self::STATUS_PENDING_GM;
        case self::STATUS_PENDING_GM:
            return self::STATUS_PENDING_LEGAL_ADMIN;
        case self::STATUS_PENDING_LEGAL_ADMIN:
            return self::STATUS_IN_DISCUSSION;
        default:
            return null;
    }
}

public function canBeApprovedBy($user): bool
{
    if (!$user) return false;
    
    // Check if user has pending approval for this document
    return $this->approvals()
        ->where('approver_nik', $user->nik ?? '')
        ->where('status', DocumentApproval::STATUS_PENDING)
        ->exists();
}

public function canBeRejectedBy($user): bool
{
    return $this->canBeApprovedBy($user);
}

public function isDiscussionActive()
{
    return $this->status === self::STATUS_IN_DISCUSSION && 
           !$this->comments()->where('is_forum_closed', true)->exists();
}

public function canCreateAgreement()
{
    return $this->status === self::STATUS_AGREEMENT_CREATION;
}

// Scopes tambahan
public function scopePendingApproval($query, $approverNik)
{
    return $query->whereHas('approvals', function ($q) use ($approverNik) {
        $q->where('approver_nik', $approverNik)
          ->where('status', DocumentApproval::STATUS_PENDING);
    });
}

public function scopeInDiscussion($query)
{
    return $query->where('status', self::STATUS_IN_DISCUSSION);
}

public function scopeCanCreateAgreement($query)
{
    return $query->where('status', self::STATUS_AGREEMENT_CREATION);
}

public function comments(): HasMany
{
    return $this->hasMany(DocumentComment::class)->orderBy('created_at');
}

public function discussion(): HasOne
{
    return $this->hasOne(DocumentDiscussion::class);
}

public function topLevelComments(): HasMany
{
    return $this->hasMany(DocumentComment::class)->whereNull('parent_id')->orderBy('created_at');
}

public function hasFinanceParticipated(): bool
{
    return $this->comments()->where('user_role', 'finance')->exists();
}

public function isDiscussionClosed(): bool
{
    return $this->status === self::STATUS_AGREEMENT_CREATION || 
           $this->comments()->where('is_forum_closed', true)->exists();
}

public function canCloseDiscussion(?User $user = null): bool
    {
        // If no user provided, use auth user
        if (!$user) {
            $user = auth()->user();
        }

        // If still no user, return false
        if (!$user) {
            return false;
        }

        // Check user role
        if ($user->role !== 'head_legal') {
            return false;
        }

        // Check document status
        if ($this->status !== 'in_discussion') {
            return false;
        }

        // Check if discussion is already closed
        if ($this->isDiscussionClosed()) {
            return false;
        }

        // Check if finance participated (if required)
        if (!$this->hasFinanceParticipated()) {
            return false;
        }

        return true;
    }

    public function canBeClosed(): bool
    {
        // Check document status
        if ($this->status !== 'in_discussion') {
            return false;
        }

        // Check if discussion is already closed
        if ($this->isDiscussionClosed()) {
            return false;
        }

        // Check if finance participated (if required)
        if (!$this->hasFinanceParticipated()) {
            return false;
        }

        return true;
    }

    public function canUserAccessDiscussion(User $user): bool
    {
        $service = app(\App\Services\DocumentDiscussionService::class);
        return $service->canUserAccessDiscussion($this, $user);
    }

    public function debugUserAccess(User $user): array
    {
        $service = app(\App\Services\DocumentDiscussionService::class);
        
        return [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'user_nik' => $user->nik,
            'user_divisi' => $user->divisi ?? 'null',
            'document_id' => $this->id,
            'document_status' => $this->status,
            'document_requester' => $this->nik,
            'document_divisi' => $this->divisi,
            'can_participate_general' => $service->canUserParticipate($user),
            'can_access_this_document' => $service->canUserAccessDiscussion($this, $user),
            'is_requester' => $this->nik === $user->nik,
            'has_approved' => $this->approvals()->where('approver_nik', $user->nik)->exists(),
            'has_commented' => $this->comments()->where('user_nik', $user->nik)->exists(),
            'is_full_access_role' => in_array($user->role, [
                'head_legal', 'general_manager', 'senior_manager',
                'reviewer_legal', 'admin_legal', 'legal_admin', 'finance'
            ]),
            'same_division' => $this->divisi === $user->divisi,
            'allowed_roles' => [
                'head_legal', 'general_manager', 'senior_manager', 
                'manager', 'supervisor', 'head', 'reviewer_legal', 
                'admin_legal', 'legal_admin', 'finance'
            ]
        ];
    }

    public function isSeniorManager(): bool
    {
        return $this->role === 'senior_manager';
    }

    public function isLegal(): bool
    {
        $legalRoles = ['admin_legal', 'reviewer_legal', 'head_legal', 'legal'];
        return in_array($this->role, $legalRoles);
    }

    public function isHeadLegal(): bool
    {
        return $this->role === 'head_legal';
    }

    public function canApprove(DocumentRequest $documentRequest): bool
    {
        return $documentRequest->approvals()
            ->where('approver_nik', $this->nik)
            ->where('status', DocumentApproval::STATUS_PENDING)
            ->exists();
    }

    public function getComputedStatusAttribute(): string
    {
        // Kalau status masih 'submitted', ubah ke 'pending_supervisor'
        if ($this->status === 'submitted') {
            return 'pending_supervisor';
        }

        // kalau ada status AO → pakai itu
        if ($this->ao_status) {
            return $this->ao_status;
        }
        return app(\App\Services\DocumentWorkflowService::class)
            ->getDocumentStatus($this);
    }

    public function approvalHistory()
    {
        // Ambil approvals
        $approvals = $this->approvals()->get()->map(function ($a) {
            return [
                'type' => 'approval',
                'role' => $a->approval_type,
                'name' => $a->approver?->name ?? $a->approver_nik,
                'status' => $a->status,
                'date' => $a->approved_at ?? $a->created_at,
                'comments' => $a->comments,
            ];
        });

        // Ambil closure events
        $closures = $this->comments()
            ->where('is_forum_closed', true)
            ->get()
            ->map(function ($c) {
                return [
                    'type' => 'closure',
                    'role' => $c->user_role,
                    'name' => $c->user_name ?? $c->user_nik,
                    'status' => 'discussion_closed',
                    'date' => $c->forum_closed_at ?? $c->created_at,
                    'comments' => $c->comment,
                ];
            });

        // Gabungkan dan urutkan berdasarkan tanggal
        return $approvals->merge($closures)->sortBy('date')->values();
    }
}