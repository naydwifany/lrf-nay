<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ApiOnlyLoginController;
use App\Http\Controllers\DiscussionAttachmentController;
use App\Http\Controllers\Auth\HybridLoginController;
use App\Http\Controllers\DiscussionController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    // Route::get('/login', [HybridLoginController::class, 'showLoginForm'])->name('login');
    // Route::post('/login', [HybridLoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    // Route::post('/logout', [HybridLoginController::class, 'logout'])->name('logout');
    // Discussion routes
    Route::get('/discussion/forum/{document_id}', [DiscussionController::class, 'show'])->name('discussion.forum');
    
    Route::get('/download/{filename}', [App\Http\Controllers\FileController::class, 'download'])
        ->name('file.download');
        
    // Attachment routes - FIXED
    Route::get('/discussion/attachment/{attachment}/download', function ($attachmentId) {
        try {
            $attachment = \App\Models\DocumentCommentAttachment::findOrFail($attachmentId);
            $user = auth()->user();
            
            // Check permission
            $service = app(\App\Services\DocumentDiscussionService::class);
            if (!$service->canUserAccessAttachment($attachment, $user)) {
                abort(403, 'Access denied');
            }
            
            // Check if file exists
            if (!\Illuminate\Support\Facades\Storage::disk('private')->exists($attachment->file_path)) {
                abort(404, 'File not found');
            }
            
            // Download file
            return \Illuminate\Support\Facades\Storage::disk('private')->download(
                $attachment->file_path,
                $attachment->original_filename
            );
            
        } catch (\Exception $e) {
            \Log::error('Download attachment error: ' . $e->getMessage());
            abort(500, 'Download failed');
        }
    })->name('discussion.attachment.download');
    
    // FIXED: Simple attachment preview route
    Route::get('/discussion/attachment/{attachment}/preview', function ($attachmentId) {
        try {
            $attachment = \App\Models\DocumentCommentAttachment::findOrFail($attachmentId);
            $user = auth()->user();
            
            // Check permission
            $service = app(\App\Services\DocumentDiscussionService::class);
            if (!$service->canUserAccessAttachment($attachment, $user)) {
                abort(403, 'Access denied');
            }
            
            // Check if file exists
            if (!\Illuminate\Support\Facades\Storage::disk('private')->exists($attachment->file_path)) {
                abort(404, 'File not found');
            }
            
            // Only allow preview for images and PDFs
            $previewableMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'application/pdf'];
            if (!in_array($attachment->mime_type, $previewableMimes)) {
                return redirect()->route('discussion.attachment.download', $attachment->id);
            }
            
            $file = \Illuminate\Support\Facades\Storage::disk('private')->get($attachment->file_path);
            
            return response($file, 200, [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="' . $attachment->original_filename . '"',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Preview attachment error: ' . $e->getMessage());
            abort(500, 'Preview failed');
        }
    })->name('discussion.attachment.preview');

    Route::get('/agreement-overview/attachment/{attachment}/download', [AgreementOverviewController::class, 'downloadAttachment'])
        ->name('agreement-overview.attachment.download');
    
    // Export AO to PDF (future enhancement)
    Route::get('/agreement-overview/{agreementOverview}/export-pdf', [AgreementOverviewController::class, 'exportPdf'])
        ->name('agreement-overview.export.pdf');
        
    // Quick approve via email link (future enhancement)
    Route::get('/agreement-overview/{agreementOverview}/quick-approve/{token}', [AgreementOverviewController::class, 'quickApprove'])
        ->name('agreement-overview.quick-approve');

    Route::get('/debug/discussion/{document}', function(\App\Models\DocumentRequest $document) {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'Not authenticated']);
        }
        
        $debugInfo = $document->debugUserAccess($user);
        
        return response()->json($debugInfo, 200, [], JSON_PRETTY_PRINT);
    })->name('debug.discussion.permissions');
});

// API routes
Route::prefix('api')->group(function () {
    Route::post('/login', [ApiOnlyLoginController::class, 'apiLogin']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [ApiOnlyLoginController::class, 'apiLogout']);
        Route::get('/me', [ApiOnlyLoginController::class, 'me']);
    });
});