<?php
// app/Http/Controllers/AgreementOverviewController.php

namespace App\Http\Controllers;

use App\Models\AgreementOverview;
use App\Models\DocumentCommentAttachment;
use App\Services\DocumentWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class AgreementOverviewController extends Controller
{
    public function __construct(
        private DocumentWorkflowService $workflowService
    ) {}

    /**
     * Download attachment file
     */
    public function downloadAttachment(DocumentCommentAttachment $attachment)
    {
        // Check if user has permission to access this attachment
        if (!$this->canUserAccessAttachment($attachment)) {
            abort(403, 'You do not have permission to access this file.');
        }

        // Check if file exists
        if (!Storage::disk('local')->exists($attachment->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->download(
            $attachment->file_path,
            $attachment->original_filename
        );
    }

    /**
     * Export Agreement Overview to PDF
     */
    public function exportPdf(AgreementOverview $agreementOverview)
    {
        // Check if user has permission to view this AO
        if (!$this->canUserViewAgreementOverview($agreementOverview)) {
            abort(403, 'You do not have permission to access this agreement overview.');
        }

        $pdf = Pdf::loadView('pdf.agreement-overview', [
            'agreementOverview' => $agreementOverview->load([
                'lrfDocument.doctype',
                'selectedDirector',
                'creator'
            ]),
            'workflowService' => $this->workflowService
        ]);

        $filename = "AO_{$agreementOverview->nomor_dokumen}_{$agreementOverview->tanggal_ao->format('Y-m-d')}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Quick approve via email link (with security token)
     */
    public function quickApprove(AgreementOverview $agreementOverview, string $token, Request $request)
    {
        // Verify token (implement your token verification logic)
        if (!$this->verifyQuickApproveToken($agreementOverview, $token)) {
            abort(403, 'Invalid or expired approval link.');
        }

        $user = auth()->user();
        
        // Check if user can approve
        if (!$this->workflowService->canUserApproveAgreementOverview($user, $agreementOverview)) {
            abort(403, 'You do not have permission to approve this agreement overview.');
        }

        // Approve the AO
        $this->workflowService->approveAgreementOverview(
            $agreementOverview, 
            $user, 
            'Quick approved via email link'
        );

        return redirect()
            ->route('filament.user.resources.pending-agreement-overviews.index')
            ->with('success', 'Agreement Overview approved successfully!');
    }

    /**
     * Check if user can access attachment
     */
    private function canUserAccessAttachment(DocumentCommentAttachment $attachment): bool
    {
        $user = auth()->user();
        
        // Get the related document/AO through the comment
        $comment = $attachment->comment;
        if (!$comment) return false;

        // If it's related to Agreement Overview
        if ($comment->agreement_overview_id) {
            $ao = AgreementOverview::find($comment->agreement_overview_id);
            return $this->canUserViewAgreementOverview($ao);
        }

        // If it's related to Document Request
        if ($comment->document_request_id) {
            $doc = $comment->documentRequest;
            return $user->nik === $doc->nik || 
                   in_array($user->role, ['head', 'general_manager', 'legal_admin', 'head_legal', 'director']);
        }

        return false;
    }

    /**
     * Check if user can view Agreement Overview
     */
    private function canUserViewAgreementOverview(AgreementOverview $agreementOverview): bool
    {
        $user = auth()->user();
        
        // Owner can always view
        if ($user->nik === $agreementOverview->nik) {
            return true;
        }

        // Approvers can view if it's in their approval stage or beyond
        if ($this->workflowService->canUserApproveAgreementOverview($user, $agreementOverview)) {
            return true;
        }

        // Head Legal and Directors can view all
        if (in_array($user->role, ['head_legal', 'director'])) {
            return true;
        }

        // Users in approval chain can view
        $approvalRoles = ['head', 'general_manager', 'finance', 'legal_admin'];
        if (in_array($user->role, $approvalRoles)) {
            return true;
        }

        return false;
    }

    /**
     * Verify quick approve token
     */
    private function verifyQuickApproveToken(AgreementOverview $agreementOverview, string $token): bool
    {
        // Implement your token verification logic here
        // This could use signed URLs, JWT tokens, or database-stored tokens
        
        // Example implementation with signed URLs:
        // return URL::hasValidSignature(request());
        
        // For now, return true (implement proper security later)
        return true;
    }
}