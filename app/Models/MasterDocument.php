<?php
// app/Models/MasterDocument.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MasterDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_name',
        'document_code', 
        'description',
        'is_active',
        'required_fields',
        'optional_fields',
        'notification_settings',
        'enable_notifications',
        'warning_days',
        'urgent_days', 
        'critical_days',
        'notification_recipients',
        'notification_message_template'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'required_fields' => 'json',
        'optional_fields' => 'json',
        'notification_settings' => 'json',
        'enable_notifications' => 'boolean',
        'notification_recipients' => 'array',
    ];

    // Relationships
    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class, 'tipe_dokumen');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithNotifications($query)
    {
        return $query->where('enable_notifications', true);
    }

    // Helper methods
    public function getNotificationLevels()
    {
        return [
            'warning' => $this->warning_days,
            'urgent' => $this->urgent_days,
            'critical' => $this->critical_days,
        ];
    }

    public function getNotificationLevel($daysRemaining)
    {
        // Handle negative days (overdue)
        if ($daysRemaining < 0) {
            return 'overdue';
        }
        
        if ($daysRemaining <= $this->critical_days) {
            return 'critical';
        } elseif ($daysRemaining <= $this->urgent_days) {
            return 'urgent';
        } elseif ($daysRemaining <= $this->warning_days) {
            return 'warning';
        }
        
        return null;
    }

// Add method to check if notifications table exists
public function notificationsTableExists()
{
    return \Schema::hasTable('notifications');
}

    public function getDefaultNotificationRecipients()
    {
        return $this->notification_recipients ?? [
            'requester' => true,
            'supervisor' => true,
            'legal_team' => false,
            'custom_emails' => []
        ];
    }

    public function getNotificationMessageTemplate()
    {
        return $this->notification_message_template ?? 
            'Your document "{document_title}" of type "{document_type}" is due in {days_remaining} days. Please take necessary action.';
    }
}