<?php
// app/Models/DivisionApprovalGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasActivityLog;

class DivisionApprovalGroup extends Model
{
    use HasFactory, HasActivityLog;

    protected $fillable = [
        'division_code',
        'division_name',
        'direktorat',
        'manager_nik',
        'manager_name',
        'senior_manager_nik',
        'senior_manager_name',
        'general_manager_nik',
        'general_manager_name',
        'is_active',
        'approval_settings',
        'last_sync'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'approval_settings' => 'json',
        'last_sync' => 'datetime'
    ];

    // Relationships
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_nik', 'nik');
    }

    public function seniorManager()
    {
        return $this->belongsTo(User::class, 'senior_manager_nik', 'nik');
    }

    public function generalManager()
    {
        return $this->belongsTo(User::class, 'general_manager_nik', 'nik');
    }

    public function divisionMembers()
    {
        return $this->hasMany(User::class, 'divisi', 'division_name');
    }

    // Helper methods
    public function getApprovalChain($userLevel = null)
    {
        $chain = [];
        
        if ($this->manager_nik && $userLevel !== 'Manager') {
            $chain[] = [
                'nik' => $this->manager_nik,
                'name' => $this->manager_name,
                'level' => 'Manager',
                'order' => 1
            ];
        }

        if ($this->senior_manager_nik && !in_array($userLevel, ['Manager', 'Senior Manager'])) {
            $chain[] = [
                'nik' => $this->senior_manager_nik,
                'name' => $this->senior_manager_name,
                'level' => 'Senior Manager',
                'order' => 2
            ];
        }

        if ($this->general_manager_nik && !in_array($userLevel, ['Manager', 'Senior Manager', 'General Manager'])) {
            $chain[] = [
                'nik' => $this->general_manager_nik,
                'name' => $this->general_manager_name,
                'level' => 'General Manager',
                'order' => 3
            ];
        }

        return collect($chain);
    }

    public function getNextApprover($currentUserLevel)
    {
        $chain = $this->getApprovalChain($currentUserLevel);
        return $chain->first();
    }

    public function hasValidApprovers()
    {
        return $this->manager_nik || $this->senior_manager_nik || $this->general_manager_nik;
    }

    public function getApprovalSettings()
    {
        return $this->approval_settings ?? [
            'require_manager' => true,
            'require_senior_manager' => true,
            'require_general_manager' => true,
            'skip_if_same_person' => true,
            'allow_self_approval' => false
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDivision($query, $divisionName)
    {
        return $query->where('division_name', $divisionName);
    }

    public function scopeByDirectorate($query, $direktorat)
    {
        return $query->where('direktorat', $direktorat);
    }

    // Static methods
    public static function findByDivision($divisionName)
    {
        return static::where('division_name', $divisionName)
                    ->where('is_active', true)
                    ->first();
    }

    public static function syncFromApi($apiData)
    {
        $divisionName = $apiData['pegawai']['divisi'] ?? null;
        $direktorat = $apiData['pegawai']['direktorat'] ?? null;
        
        if (!$divisionName) {
            return null;
        }

        $divisionCode = strtolower(str_replace([' ', '-', '.'], '_', $divisionName));
        
        $approvalGroup = static::updateOrCreate(
            ['division_code' => $divisionCode],
            [
                'division_name' => $divisionName,
                'direktorat' => $direktorat,
                'last_sync' => now()
            ]
        );

        // Update approvers based on API data
        $pegawai = $apiData['pegawai'];
        $atasan = $apiData['atasan'] ?? null;

        // Set approvers based on level
        if ($pegawai['level'] === 'Manager' && !$approvalGroup->manager_nik) {
            $approvalGroup->update([
                'manager_nik' => $pegawai['nik'],
                'manager_name' => $pegawai['nama']
            ]);
        }

        if ($atasan && $atasan['level'] === 'Senior Manager' && !$approvalGroup->senior_manager_nik) {
            $approvalGroup->update([
                'senior_manager_nik' => $atasan['nik'],
                'senior_manager_name' => $atasan['nama']
            ]);
        }

        return $approvalGroup;
    }
}