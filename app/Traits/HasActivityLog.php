<?php
// app/Traits/HasActivityLog.php (Updated for existing migration)

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait HasActivityLog
{
    protected static function bootHasActivityLog()
    {
        // Skip activity logging during console operations (seeding, migrations, etc.)
        if (app()->runningInConsole()) {
            return;
        }

        static::created(function ($model) {
            $model->logActivity('created', "Created {$model->getTable()} record");
        });

        static::updated(function ($model) {
            $model->logActivity('updated', "Updated {$model->getTable()} record");
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted', "Deleted {$model->getTable()} record");
        });
    }

    public function logActivity(string $action, string $description, array $properties = [])
    {
        try {
            $user = Auth::user();
            
            // Prepare safe data for activity log
            $activityData = [
                'user_nik' => $user->nik ?? 'system',
                'user_name' => $user->name ?? 'System',
                'user_role' => $user->role ?? 'system',
                'action' => $action,
                'description' => $description,
                'subject_type' => get_class($this),
                'subject_id' => $this->getKey() ?? 0,
                'properties' => $this->sanitizeProperties($properties)
            ];

            ActivityLog::create($activityData);

        } catch (\Exception $e) {
            // Log the error but don't break the main operation
            Log::error('Failed to log activity: ' . $e->getMessage(), [
                'model' => get_class($this),
                'model_id' => $this->getKey(),
                'action' => $action,
                'user' => Auth::user()->nik ?? 'system',
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function sanitizeProperties(array $properties): array
    {
        if (empty($properties)) {
            return [];
        }

        $sanitized = [];
        
        foreach ($properties as $key => $value) {
            // Handle different data types safely
            if (is_null($value)) {
                $sanitized[$key] = null;
            } elseif (is_scalar($value)) {
                // String, int, float, bool
                $sanitized[$key] = $value;
            } elseif (is_array($value)) {
                // Recursively sanitize arrays
                $sanitized[$key] = $this->sanitizeArrayValues($value);
            } elseif (is_object($value)) {
                // Convert objects to arrays
                if (method_exists($value, 'toArray')) {
                    $sanitized[$key] = $this->sanitizeArrayValues($value->toArray());
                } elseif ($value instanceof \JsonSerializable) {
                    $sanitized[$key] = $value->jsonSerialize();
                } else {
                    // Convert to string for other objects
                    $sanitized[$key] = (string) $value;
                }
            } elseif (is_resource($value)) {
                // Skip resources as they can't be JSON encoded
                continue;
            } else {
                // Fallback to string conversion
                $sanitized[$key] = (string) $value;
            }
        }
        
        return $sanitized;
    }

    protected function sanitizeArrayValues(array $array): array
    {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            if (is_null($value)) {
                $sanitized[$key] = null;
            } elseif (is_scalar($value)) {
                $sanitized[$key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArrayValues($value);
            } elseif (is_object($value)) {
                if (method_exists($value, 'toArray')) {
                    $sanitized[$key] = $this->sanitizeArrayValues($value->toArray());
                } else {
                    $sanitized[$key] = (string) $value;
                }
            } else {
                $sanitized[$key] = (string) $value;
            }
        }
        
        return $sanitized;
    }

    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function getRecentActivity($days = 30)
    {
        return $this->activityLogs()
                   ->where('created_at', '>=', now()->subDays($days))
                   ->orderBy('created_at', 'desc')
                   ->get();
    }

    public function logCustomActivity(string $action, string $description, array $customData = [])
    {
        // Get only fillable attributes to avoid issues with relationships
        $modelData = [];
        foreach ($this->getFillable() as $field) {
            if (isset($this->attributes[$field])) {
                $modelData[$field] = $this->attributes[$field];
            }
        }

        $properties = array_merge($customData, [
            'model_data' => $modelData,
            'timestamp' => now()->toISOString()
        ]);

        $this->logActivity($action, $description, $properties);
    }

    /**
     * Log activity with manual data (for use outside model events)
     */
    public static function logManualActivity(array $data)
    {
        try {
            $user = Auth::user();
            
            ActivityLog::create([
                'user_nik' => $data['user_nik'] ?? ($user->nik ?? 'system'),
                'user_name' => $data['user_name'] ?? ($user->name ?? 'System'),
                'user_role' => $data['user_role'] ?? ($user->role ?? 'system'),
                'action' => $data['action'],
                'description' => $data['description'],
                'subject_type' => $data['subject_type'] ?? null,
                'subject_id' => $data['subject_id'] ?? 0,
                'properties' => $data['properties'] ?? []
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to log manual activity: ' . $e->getMessage(), $data);
        }
    }

    protected function getModelName(): string
    {
        return class_basename(get_class($this));
    }
}