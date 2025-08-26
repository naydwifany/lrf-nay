<?php
namespace App\Http\Controllers;

use App\Models\DocumentCommentAttachment;
use App\Services\DocumentDiscussionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscussionAttachmentController extends Controller
{
    protected DocumentDiscussionService $discussionService;

    public function __construct(DocumentDiscussionService $discussionService)
    {
        $this->discussionService = $discussionService;
    }

    public function download(DocumentCommentAttachment $attachment): StreamedResponse
    {
        try {
            return $this->discussionService->downloadAttachment($attachment->id, auth()->user());
        } catch (\Exception $e) {
            abort(403, $e->getMessage());
        }
    }

    public function preview(DocumentCommentAttachment $attachment)
    {
        try {
            // Check if user can access this attachment
            if (!$this->discussionService->canUserAccessAttachment($attachment, auth()->user())) {
                abort(403, 'You do not have permission to preview this file.');
            }

            if (!$attachment->exists()) {
                abort(404, 'File not found.');
            }

            // Only allow preview for images and PDFs
            if (!$attachment->canPreview()) {
                return redirect()->route('discussion.attachment.download', $attachment);
            }

            $file = Storage::disk('public')->get($attachment->file_path);
            
            return response($file, 200, [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="' . $attachment->original_filename . '"',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            \Log::error('Error previewing attachment', [
                'attachment_id' => $attachment->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            abort(500, 'Error loading file preview.');
        }
    }

    /**
     * Get attachment info (for AJAX requests)
     */
    public function info(int $attachmentId)
    {
        try {
            $attachment = DocumentCommentAttachment::findOrFail($attachmentId);
            
            if (!$this->discussionService->canUserAccessAttachment($attachment, auth()->user())) {
                abort(403, 'Access denied');
            }

            return response()->json([
                'id' => $attachment->id,
                'original_filename' => $attachment->original_filename,
                'file_size' => $attachment->file_size,
                'mime_type' => $attachment->mime_type,
                'uploaded_by' => $attachment->uploaded_by_name,
                'created_at' => $attachment->created_at->format('Y-m-d H:i:s'),
                'download_url' => route('discussion.attachment.download', $attachment->id),
                'preview_url' => route('discussion.attachment.preview', $attachment->id),
                'is_image' => str_starts_with($attachment->mime_type, 'image/'),
                'is_pdf' => $attachment->mime_type === 'application/pdf',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 404);
        }
    }
}