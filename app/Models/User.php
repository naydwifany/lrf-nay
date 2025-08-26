<?php
// app/Models/User.php - COMPLETE VERSION

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nik',
        'name',
        'email',
        'password',
        'role',
        'jabatan',
        'divisi',
        'department',
        'direktorat',
        'level',
        'supervisor_nik',
        'is_active',
        'can_access_admin_panel',
        'last_login_at',
        'login_attempts',
        'notes',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'can_access_admin_panel' => 'boolean',
        'level' => 'integer',
        'login_attempts' => 'integer',
        'password' => 'hashed', // Laravel 10+ automatic hashing
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function hasRole(string $roleName): bool
    {
        // Cek berdasarkan field role langsung
        return $this->role === $roleName;
    }

    // Atau jika role disimpan dalam field lain
    public function isHeadLegal(): bool
    {
        return $this->role === 'head_legal' || 
               $this->jabatan === 'Head Legal' || 
               stripos($this->jabatan, 'head legal') !== false;
    }

    

    // General method untuk cek role
    public function checkRole(string $role): bool
    {
        return match($role) {
            'head_legal' => $this->isHeadLegal(),
            'finance' => $this->isFinance(),
            'general_manager' => $this->role === 'general_manager' || stripos($this->jabatan, 'general manager') !== false,
            'head' => $this->role === 'head' || stripos($this->jabatan, 'head') !== false,
            default => $this->role === $role
        };
    }
    /**
     * Get the supervisor of this user
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_nik', 'nik');
    }

    /**
     * Get all subordinates of this user
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'supervisor_nik', 'nik');
    }

    /**
     * Get all document requests created by this user
     */
    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class, 'nik', 'nik');
    }

    /**
     * Get all document approvals by this user
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class, 'approver_nik', 'nik');
    }

    /**
     * Get all agreement overviews created by this user
     */
    public function agreementOverviews(): HasMany
    {
        return $this->hasMany(AgreementOverview::class, 'nik', 'nik');
    }

    /**
     * Get all notifications for this user
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_nik', 'nik');
    }

    /**
     * Get all document comments by this user
     */
    public function documentComments(): HasMany
    {
        return $this->hasMany(DocumentComment::class, 'user_nik', 'nik');
    }

    /**
     * Get division approval group for this user's division
     */
    public function divisionApprovalGroup()
    {
        return $this->hasOne(DivisionApprovalGroup::class, 'division_name', 'divisi');
    }

    // ========================================
    // FILAMENT PANEL ACCESS
    // ========================================

    /**
     * Determine if user can access a specific Filament panel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Admin panel access
        if ($panel->getId() === 'admin') {
            return $this->canAccessAdminPanel();
        }

        // User panel access - all active users
        return true; // Default allow for user panel
    }

    // ========================================
    // PERMISSION HELPER METHODS
    // ========================================

    /**
     * Check if user can access admin panel
     */
    public function canAccessAdminPanel(): bool
    {
        // Must be active first - THIS IS CRITICAL
        if (!$this->is_active) {
            return false;
        }

        // Check explicit permission flag
        if ($this->can_access_admin_panel) {
            return true;
        }

        // Check by role - Legal team and Management can access
        return $this->isLegal() || $this->isManagement();
    }

    /**
     * Check if user is part of legal team
     */
    public function isLegal(): bool
    {
        return in_array($this->role, [
            'admin_legal',
            'reviewer_legal', 
            'head_legal',
            'legal'
        ]);
    }

    /**
     * Check if user is management level
     */
    public function isManagement(): bool
    {
        return in_array($this->role, [
            'general_manager',
            'director',
            'head_finance'
        ]) || ($this->level && $this->level >= 6);
    }

    /**
     * Check if user is supervisor level
     */
    public function isSupervisor(): bool
    {
        return in_array($this->role, [
            'supervisor',
            'manager',
            'senior_manager'
        ]) || ($this->level && $this->level >= 4);
    }

    /**
     * Check if user is general manager level
     */
    public function isGeneralManager(): bool
    {
        return in_array($this->role, [
            'general_manager',
            'director'
        ]) || ($this->level && $this->level >= 6);
    }

    /**
     * Check if user is finance team
     */
    public function isFinance(): bool
    {
        return in_array($this->role, [
            'finance',
            'head_finance'
        ]);
    }

    /**
     * Check if user can approve documents
     */
    public function canApproveDocuments(): bool
    {
        return $this->isSupervisor() || $this->isManagement() || $this->isLegal() || $this->isFinance();
    }

    /**
     * Get user's approval level for workflow
     */
    public function getApprovalLevel(): int
    {
        return match($this->role) {
            'supervisor', 'manager' => 1,
            'senior_manager' => 2,
            'general_manager' => 3,
            'admin_legal', 'reviewer_legal', 'legal' => 4,
            'head_legal' => 5,
            'finance' => 6,
            'head_finance' => 7,
            'director' => 8,
            default => 0
        };
    }

    /**
     * Check if user can create document requests
     */
    public function canCreateDocuments(): bool
    {
        return $this->is_active;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }
    

    // Alternative method jika menggunakan single role field
    public function hasRoleByField(string $roleName): bool
    {
        // Jika User model punya field 'role' langsung
        return $this->role === $roleName;
    }

    // Method untuk cek multiple roles
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }
    /**
     * Check if user can participate in discussions
     */
    public function canParticipateInDiscussions(): bool
    {
        $allowedRoles = ['head', 'general_manager', 'reviewer_legal', 'finance', 'head_legal', 'legal', 'admin_legal'];
        return $this->is_active && in_array($this->role, $allowedRoles);
    }



    /**
     * Check if user can close discussion forums
     */
    public function canCloseDiscussions(): bool
    {
        return $this->role === 'head_legal';
    }

    /**
     * Check if user can create agreement overviews
     */
    public function canCreateAgreements(): bool
    {
        return $this->is_active;
    }

    // ========================================
    // DISPLAY HELPER METHODS
    // ========================================

    /**
     * Get user's display name with NIK
     */
    public function getDisplayName(): string
    {
        return $this->name . ' (' . $this->nik . ')';
    }

    /**
     * Get user's full title with position and division
     */
    public function getFullTitle(): string
    {
        $parts = array_filter([
            $this->name,
            $this->jabatan,
            $this->divisi
        ]);
        return implode(' - ', $parts);
    }

    /**
     * Get role display name
     */
    public function getRoleDisplayAttribute(): string
    {
        return match($this->role) {
            'user' => 'User',
            'supervisor' => 'Supervisor',
            'manager' => 'Manager',
            'senior_manager' => 'Senior Manager',
            'general_manager' => 'General Manager',
            'director' => 'Director',
            'admin_legal' => 'Admin Legal',
            'reviewer_legal' => 'Reviewer Legal',
            'head_legal' => 'Head Legal',
            'legal' => 'Legal',
            'finance' => 'Finance',
            'head_finance' => 'Head Finance',
            default => ucfirst(str_replace('_', ' ', $this->role))
        };
    }

    /**
     * Get level display with description
     */
    public function getLevelDisplayAttribute(): string
    {
        if (!$this->level) return 'N/A';
        
        return match(true) {
            $this->level >= 8 => "Level {$this->level} (Director)",
            $this->level >= 6 => "Level {$this->level} (Executive)",
            $this->level >= 4 => "Level {$this->level} (Management)",
            default => "Level {$this->level}"
        };
    }

    /**
     * Get user status badge color
     */
    public function getStatusColor(): string
    {
        if (!$this->is_active) return 'danger';
        if ($this->login_attempts >= 3) return 'warning';
        return 'success';
    }

    /**
     * Get role badge color
     */
    public function getRoleBadgeColor(): string
    {
        return match($this->role) {
            'user' => 'gray',
            'supervisor', 'manager' => 'primary',
            'senior_manager', 'general_manager' => 'success',
            'director' => 'warning',
            'head_legal', 'head_finance' => 'danger',
            'admin_legal', 'reviewer_legal', 'legal', 'finance' => 'info',
            default => 'gray'
        };
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope to get only active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get users by role
     */
    public function scopeByRole($query, $role)
    {
        if (is_array($role)) {
            return $query->whereIn('role', $role);
        }
        return $query->where('role', $role);
    }

    /**
     * Scope to get users by division
     */
    public function scopeByDivision($query, $division)
    {
        return $query->where('divisi', $division);
    }

    /**
     * Scope to get users who can access admin panel
     */
    public function scopeCanAccessAdmin($query)
    {
        return $query->where(function($q) {
            $q->where('can_access_admin_panel', true)
              ->orWhereIn('role', [
                  'admin_legal', 'reviewer_legal', 'head_legal', 'legal',
                  'general_manager', 'director', 'head_finance'
              ]);
        });
    }

    /**
     * Scope to get supervisors
     */
    public function scopeSupervisors($query)
    {
        return $query->whereIn('role', [
            'supervisor', 'manager', 'senior_manager', 
            'general_manager', 'director'
        ])->orWhere('level', '>=', 4);
    }

    /**
     * Scope to get legal team members
     */
    public function scopeLegalTeam($query)
    {
        return $query->whereIn('role', [
            'admin_legal', 'reviewer_legal', 'head_legal', 'legal'
        ]);
    }

    /**
     * Scope to get management team
     */
    public function scopeManagement($query)
    {
        return $query->whereIn('role', [
            'general_manager', 'director', 'head_finance'
        ])->orWhere('level', '>=', 6);
    }

    /**
     * Scope to get users by minimum level
     */
    public function scopeMinimumLevel($query, $level)
    {
        return $query->where('level', '>=', $level);
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if user can approve a specific document
     */
    public function canApproveDocument(DocumentRequest $document): bool
    {
        if (!$this->canApproveDocuments()) {
            return false;
        }

        // Check if user is in the approval workflow for this document
        $pendingApproval = $document->approvals()
            ->where('approver_nik', $this->nik)
            ->where('status', 'pending')
            ->exists();

        if ($pendingApproval) {
            return true;
        }

        // Check status-based approval rights
        return match($document->status) {
            'pending_supervisor' => $this->isSupervisor() && $document->nik_atasan === $this->nik,
            'pending_gm' => $this->isGeneralManager(),
            'pending_legal' => $this->isLegal(),
            'pending_finance' => $this->isFinance(),
            default => false
        };
    }

    /**
     * Get documents pending approval by this user
     */
    public function getPendingApprovals()
    {
        return DocumentRequest::where(function ($query) {
            $query->whereHas('approvals', function ($q) {
                $q->where('approver_nik', $this->nik)
                  ->where('status', 'pending');
            })
            ->orWhere(function ($q) {
                if ($this->isSupervisor()) {
                    $q->where('status', 'pending_supervisor')
                      ->where('nik_atasan', $this->nik);
                }
                if ($this->isGeneralManager()) {
                    $q->orWhere('status', 'pending_gm');
                }
                if ($this->isLegal()) {
                    $q->orWhere('status', 'pending_legal');
                }
                if ($this->isFinance()) {
                    $q->orWhere('status', 'pending_finance');
                }
            });
        })->whereNotNull('submitted_at');
    }

    /**
     * Reset login attempts
     */
    public function resetLoginAttempts(): void
    {
        $this->update(['login_attempts' => 0]);
    }

    /**
     * Increment login attempts
     */
    public function incrementLoginAttempts(): void
    {
        $this->increment('login_attempts');
        
        // Lock account after 5 failed attempts
        if ($this->login_attempts >= 5) {
            $this->update(['is_active' => false]);
        }
    }

    /**
     * Check if account is locked due to failed attempts
     */
    public function isLocked(): bool
    {
        return !$this->is_active || $this->login_attempts >= 5;
    }

    /**
     * Unlock user account
     */
    public function unlock(): void
    {
        $this->update([
            'is_active' => true,
            'login_attempts' => 0
        ]);
    }

    // ========================================
    // MUTATORS & ACCESSORS
    // ========================================

    /**
     * Set password with automatic hashing
     */
    public function setPasswordAttribute($value): void
    {
        if ($value) {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    /**
     * Get user's unread notifications count
     */
    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->notifications()->where('is_read', false)->count();
    }

    /**
     * Get user's pending approvals count
     */
    public function getPendingApprovalsCountAttribute(): int
    {
        return $this->getPendingApprovals()->count();
    }

    /**
     * Get user's created documents count
     */
    public function getDocumentsCountAttribute(): int
    {
        return $this->documentRequests()->count();
    }

    /**
     * Get user's completed approvals count
     */
    public function getCompletedApprovalsCountAttribute(): int
    {
        return $this->approvals()->where('status', 'approved')->count();
    }

    // ========================================
    // BOOT METHOD
    // ========================================

    protected static function boot()
    {
        parent::boot();

        // Set default values when creating new user
        static::creating(function ($user) {
            $user->is_active = $user->is_active ?? true;
            $user->login_attempts = $user->login_attempts ?? 0;
            $user->role = $user->role ?? 'user';
        });

        // Log user activities
        static::created(function ($user) {
            \Log::info('User created', [
                'nik' => $user->nik,
                'name' => $user->name,
                'role' => $user->role
            ]);
        });

        static::updated(function ($user) {
            if ($user->wasChanged('is_active')) {
                \Log::info('User status changed', [
                    'nik' => $user->nik,
                    'is_active' => $user->is_active
                ]);
            }
        });
    }
}