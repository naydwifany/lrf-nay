<?php
// app/Models/ActivityLog.php (Updated for existing migration)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_nik',
        'user_name', 
        'user_role',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties'
    ];

    protected $casts = [
        'properties' => 'json',
        'subject_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function subject()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_nik', 'nik');
    }

    // Scopes
    public function scopeByUser($query, $userNik)
    {
        return $query->where('user_nik', $userNik);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeBySubject($query, $subjectType, $subjectId = null)
    {
        $query = $query->where('subject_type', $subjectType);
        
        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }
        
        return $query;
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function getFormattedPropertiesAttribute()
    {
        if (!$this->properties) {
            return [];
        }

        return is_string($this->properties) 
            ? json_decode($this->properties, true) 
            : $this->properties;
    }

    public function getActionLabelAttribute()
    {
        $labels = [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'submitted' => 'Submitted',
            'assigned' => 'Assigned',
            'closed' => 'Closed',
            'opened' => 'Opened'
        ];

        return $labels[$this->action] ?? ucfirst($this->action);
    }

    public function getSubjectNameAttribute()
    {
        $subjectNames = [
            'App\Models\DocumentRequest' => 'Document Request',
            'App\Models\AgreementOverview' => 'Agreement Overview',
            'App\Models\DocumentApproval' => 'Document Approval',
            'App\Models\AgreementApproval' => 'Agreement Approval',
            'App\Models\DivisionApprovalGroup' => 'Division Approval Group',
            'App\Models\User' => 'User'
        ];

        return $subjectNames[$this->subject_type] ?? class_basename($this->subject_type);
    }

    public static function logActivity(array $data)
    {
        try {
            // Ensure all required fields are present
            $requiredFields = ['user_nik', 'user_name', 'user_role', 'action', 'description', 'subject_type', 'subject_id'];
            
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new \InvalidArgumentException("Required field '{$field}' is missing");
                }
            }

            // Sanitize properties
            if (isset($data['properties']) && is_array($data['properties'])) {
                $data['properties'] = static::sanitizeProperties($data['properties']);
            } else {
                $data['properties'] = [];
            }

            return static::create($data);

        } catch (\Exception $e) {
            \Log::error('Failed to create activity log: ' . $e->getMessage(), $data);
            return null;
        }
    }

    protected static function sanitizeProperties(array $properties): array
    {
        $sanitized = [];
        
        foreach ($properties as $key => $value) {
            if (is_null($value)) {
                $sanitized[$key] = null;
            } elseif (is_scalar($value)) {
                $sanitized[$key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$key] = static::sanitizeProperties($value);
            } elseif (is_object($value)) {
                if (method_exists($value, 'toArray')) {
                    $sanitized[$key] = static::sanitizeProperties($value->toArray());
                } elseif ($value instanceof \JsonSerializable) {
                    $sanitized[$key] = $value->jsonSerialize();
                } else {
                    $sanitized[$key] = (string) $value;
                }
            } else {
                $sanitized[$key] = (string) $value;
            }
        }
        
        return $sanitized;
    }

    // Override save to ensure properties is properly JSON encoded
    public function save(array $options = [])
    {
        // Ensure properties is properly formatted before saving
        if (isset($this->attributes['properties']) && is_array($this->attributes['properties'])) {
            $this->attributes['properties'] = json_encode($this->attributes['properties']);
        }

        return parent::save($options);
    }

    // Override setAttribute for properties to handle arrays
    public function setAttribute($key, $value)
    {
        if ($key === 'properties' && is_array($value)) {
            $this->attributes[$key] = json_encode($value);
            return $this;
        }

        return parent::setAttribute($key, $value);
    }
}