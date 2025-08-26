<?php
// app/Models/DocumentApproval.php (Updated)

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasActivityLog;

class DocumentApproval extends Model
{
    use HasActivityLog;

    protected $fillable = [
        'document_request_id', 'approver_nik', 'approver_name', 'approval_type', 
        'status', 'comments', 'approved_at', 'order', 'division_level', 'is_division_approval'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'is_division_approval' => 'boolean'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // Type constants - Updated with division hierarchy
    const TYPE_DIVISION_MANAGER = 'division_manager';
    const TYPE_DIVISION_SENIOR_MANAGER = 'division_senior_manager';
    const TYPE_DIVISION_GENERAL_MANAGER = 'division_general_manager';
    const TYPE_SUPERVISOR = 'supervisor';
    const TYPE_MANAGER = 'manager';
    const TYPE_SENIOR_MANAGER = 'senior_manager';
    const TYPE_GENERAL_MANAGER = 'general_manager';
    const TYPE_ADMIN_LEGAL = 'admin_legal';
    const TYPE_LEGAL = 'legal';
    const TYPE_HEAD_LEGAL = 'head_legal';
    const TYPE_FINANCE = 'finance';
    const TYPE_HEAD_FINANCE = 'head_finance';
    const TYPE_DIRECTOR = 'director';

    // Relationships
    public function documentRequest()
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_nik', 'nik');
    }

    public function divisionGroup()
    {
        return $this->belongsTo(DivisionApprovalGroup::class, 'division_level', 'division_code');
    }

    // Helper methods
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected()
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isDivisionApproval()
    {
        return $this->is_division_approval === true;
    }

    public function getApprovalTypeLabel()
    {
        $labels = [
            self::TYPE_DIVISION_MANAGER => 'Division Manager',
            self::TYPE_DIVISION_SENIOR_MANAGER => 'Division Senior Manager',
            self::TYPE_DIVISION_GENERAL_MANAGER => 'Division General Manager',
            self::TYPE_SUPERVISOR => 'Supervisor',
            self::TYPE_MANAGER => 'Manager',
            self::TYPE_SENIOR_MANAGER => 'Senior Manager',
            self::TYPE_GENERAL_MANAGER => 'General Manager',
            self::TYPE_ADMIN_LEGAL => 'Admin Legal',
            self::TYPE_LEGAL => 'Legal Team',
            self::TYPE_HEAD_LEGAL => 'Head Legal',
            self::TYPE_FINANCE => 'Finance Team',
            self::TYPE_HEAD_FINANCE => 'Head Finance',
            self::TYPE_DIRECTOR => 'Director'
        ];

        return $labels[$this->approval_type] ?? ucfirst(str_replace('_', ' ', $this->approval_type));
    }

    public function getStatusBadgeColor()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            default => 'secondary'
        };
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeByApprover($query, $nik)
    {
        return $query->where('approver_nik', $nik);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('approval_type', $type);
    }

    public function scopeDivisionApprovals($query)
    {
        return $query->where('is_division_approval', true);
    }

    public function scopeNonDivisionApprovals($query)
    {
        return $query->where('is_division_approval', false);
    }

    public function scopeCurrentPending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->orderBy('order');
    }

    public function scopeByDivision($query, $divisionCode)
    {
        return $query->where('division_level', $divisionCode);
    }

    // Static methods
    public static function getApprovalTypesByCategory()
    {
        return [
            'division' => [
                self::TYPE_DIVISION_MANAGER,
                self::TYPE_DIVISION_SENIOR_MANAGER,
                self::TYPE_DIVISION_GENERAL_MANAGER
            ],
            'hierarchy' => [
                self::TYPE_SUPERVISOR,
                self::TYPE_MANAGER,
                self::TYPE_SENIOR_MANAGER,
                self::TYPE_GENERAL_MANAGER
            ],
            'legal' => [
                self::TYPE_ADMIN_LEGAL,
                self::TYPE_LEGAL,
                self::TYPE_HEAD_LEGAL
            ],
            'finance' => [
                self::TYPE_FINANCE,
                self::TYPE_HEAD_FINANCE
            ],
            'executive' => [
                self::TYPE_DIRECTOR
            ]
        ];
    }

    public static function isDivisionApprovalType($type)
    {
        return in_array($type, [
            self::TYPE_DIVISION_MANAGER,
            self::TYPE_DIVISION_SENIOR_MANAGER,
            self::TYPE_DIVISION_GENERAL_MANAGER
        ]);
    }
}