<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DocumentCommentAttachment extends Model
{
    protected $fillable = [
        'document_comment_id',
        'filename',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by_nik',
        'uploaded_by_name',
    ];

    // Relationships
    public function comment(): BelongsTo
    {
        return $this->belongsTo(DocumentComment::class, 'document_comment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_nik', 'nik');
    }

    // Helper methods
    public function getDownloadUrl(): string
    {
        return route('discussion.attachment.download', $this->id);
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

    // Storage methods
    public function exists(): bool
    {
        return Storage::disk('private')->exists($this->file_path);
    }

    public function delete(): bool
    {
        // Delete file from storage
        if ($this->exists()) {
            Storage::disk('private')->delete($this->file_path);
        }
        
        // Delete record from database
        return parent::delete();
    }

    // Scopes
    public function scopeByComment($query, $commentId)
    {
        return $query->where('document_comment_id', $commentId);
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
}