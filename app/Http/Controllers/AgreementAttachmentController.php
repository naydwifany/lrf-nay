<?php
namespace App\Http\Controllers;

use App\Models\AgreementOverview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AgreementAttachmentController extends Controller
{
    public function download(Request $request, $agreementId, $attachmentId)
    {
        try {
            $user = auth()->user();
            $agreement = AgreementOverview::findOrFail($agreementId);
            
            // Check permission
            if ($agreement->nik !== $user->nik && !in_array($user->role, ['admin', 'head_legal'])) {
                abort(403);
            }
            
            // Find attachment in JSON
            $attachmentInfo = collect($agreement->attachment_info)
                ->where('id', $attachmentId)
                ->first();
                
            if (!$attachmentInfo) {
                abort(404, 'Attachment not found');
            }
            
            if (!Storage::disk('public')->exists($attachmentInfo['file_path'])) {
                abort(404, 'File not found');
            }
            
            return Storage::disk('public')->download(
                $attachmentInfo['file_path'],
                $attachmentInfo['original_filename']
            );
            
        } catch (\Exception $e) {
            abort(500, 'Error downloading file');
        }
    }
}