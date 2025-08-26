<?php
// app/Services/ActivityLogService.php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * Log activity
     */
    public function log(string $userNik, string $userName, string $userRole, string $action, string $description, string $subjectType, int $subjectId, array $properties = [])
    {
        return ActivityLog::create([
            'user_nik' => $userNik,
            'user_name' => $userName,
            'user_role' => $userRole,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $properties
        ]);
    }

    /**
     * Log with current user
     */
    public function logWithCurrentUser(string $action, string $description, string $subjectType, int $subjectId, array $properties = [])
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->log('system', 'System', 'system', $action, $description, $subjectType, $subjectId, $properties);
        }

        return $this->log($user->nik, $user->name, $user->role, $action, $description, $subjectType, $subjectId, $properties);
    }

    /**
     * Get activity logs for a subject
     */
    public function getSubjectLogs(string $subjectType, int $subjectId, int $limit = 50)
    {
        return ActivityLog::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get user activity logs
     */
    public function getUserLogs(string $userNik, int $limit = 50)
    {
        return ActivityLog::where('user_nik', $userNik)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent activities for dashboard
     */
    public function getRecentActivities(int $limit = 10)
    {
        return ActivityLog::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean old activity logs (older than specified days)
     */
    public function cleanOldLogs(int $daysToKeep = 365)
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return ActivityLog::where('created_at', '<', $cutoffDate)->delete();
    }
}