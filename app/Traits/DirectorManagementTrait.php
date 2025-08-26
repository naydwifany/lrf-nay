<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Log;

trait DirectorManagementTrait
{
    public static function getAvailableDirectors(): array
    {
        try {
            return User::where(function($query) {
                    $query->where('role', 'like', '%director%')
                          ->orWhere('jabatan', 'like', '%direktur%');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'nik')
                ->toArray();
        } catch (\Exception $e) {
            return [
                'DIR001' => 'Director 1',
                'DIR002' => 'Director 2'
            ];
        }
    }

    public static function getDirectorDetails(?string $nik): array
    {
        if (empty($nik)) {
            return ['name' => '', 'direktorat' => ''];
        }

        try {
            $director = User::where('nik', $nik)->first();
            return [
                'name' => $director->name ?? 'Unknown Director',
                'direktorat' => $director->direktorat ?? ''
            ];
        } catch (\Exception $e) {
            return ['name' => 'Error Getting Director', 'direktorat' => ''];
        }
    }
}