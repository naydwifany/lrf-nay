<?php
// app/Models/AgreementAttachment.php - NEW MODEL FOR ATTACHMENTS

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AgreementAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'agreement_overview_id',
        'filename',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'copied_from_comment_id',
        'uploaded_by_nik',
        'uploaded_by_name',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    // Relationships
    public function agreementOverview(): BelongsTo
    {
        return $this->belongsTo(AgreementOverview::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_nik', 'nik');
    }

    public function sourceComment(): BelongsTo
    {
        return $this->belongsTo(DocumentComment::class, 'copied_from_comment_id');
    }

    // Helper methods
    public function getDownloadUrl(): string
    {
        return route('agreement.attachment.download', $this->id);
    }

    public function getFileIcon(): string
    {
        return match(true) {
            str_contains($this->mime_type, 'image/') => 'heroicon-o-photo',
            str_contains($this->mime_type, 'pdf') => 'heroicon-o-document',
            str_contains($this->mime_type, 'word') => 'heroicon-o-document-text',
            str_contains($this->mime_type, 'excel') || str_contains($this->mime_type, 'spreadsheet') => 'heroicon-o-table-cells',
            default => 'heroicon-o-paper-clip'
        };
    }

    public function getFormattedFileSize(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    public function isImage(): bool
    {
        return str_contains($this->mime_type, 'image/');
    }

    public function canPreview(): bool
    {
        return $this->isImage() || str_contains($this->mime_type, 'pdf');
    }

    public function isCopiedFromDiscussion(): bool
    {
        return !is_null($this->copied_from_comment_id);
    }

    // Storage methods
    public function exists(): bool
    {
        return Storage::disk('public')->exists($this->file_path);
    }

    public function delete(): bool
    {
        // Delete file from storage
        if ($this->exists()) {
            Storage::disk('public')->delete($this->file_path);
        }
        
        // Delete record from database
        return parent::delete();
    }

    // Scopes
    public function scopeByAgreement($query, $agreementId)
    {
        return $query->where('agreement_overview_id', $agreementId);
    }

    public function scopeByUploader($query, $nik)
    {
        return $query->where('uploaded_by_nik', $nik);
    }

    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    public function scopeDocuments($query)
    {
        return $query->where('mime_type', 'not like', 'image/%');
    }

    public function scopeCopiedFromDiscussion($query)
    {
        return $query->whereNotNull('copied_from_comment_id');
    }

    public function scopeDirectUploads($query)
    {
        return $query->whereNull('copied_from_comment_id');
    }
}