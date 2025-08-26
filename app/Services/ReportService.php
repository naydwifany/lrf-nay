<?php
// app/Services/ReportService.php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\AgreementOverview;
use App\Models\DocumentApproval;
use App\Models\AgreementApproval;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ReportService
{
    protected $notificationService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
    }

    /**
     * Generate document summary report
     */
    public function generateDocumentSummaryReport(array $parameters, User $requestedBy)
    {
        $startDate = Carbon::parse($parameters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($parameters['end_date'] ?? now()->endOfMonth());
        $format = $parameters['format'] ?? 'pdf'; // 'pdf' or 'excel'

        // Get data
        $documentRequests = DocumentRequest::whereBetween('created_at', [$startDate, $endDate])
            ->with(['user', 'doctype', 'approvals'])
            ->get();

        $agreementOverviews = AgreementOverview::whereBetween('created_at', [$startDate, $endDate])
            ->with(['user', 'approvals'])
            ->get();

        $data = [
            'document_requests' => $documentRequests,
            'agreement_overviews' => $agreementOverviews,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'generated_at' => now(),
            'generated_by' => $requestedBy->name,
            'summary' => [
                'total_documents' => $documentRequests->count(),
                'total_agreements' => $agreementOverviews->count(),
                'completed_documents' => $documentRequests->where('status', DocumentRequest::STATUS_COMPLETED)->count(),
                'pending_documents' => $documentRequests->whereNotIn('status', [DocumentRequest::STATUS_COMPLETED, DocumentRequest::STATUS_REJECTED])->count(),
                'rejected_documents' => $documentRequests->where('status', DocumentRequest::STATUS_REJECTED)->count(),
            ]
        ];

        if ($format === 'pdf') {
            $this->generatePdfReport('document-summary', $data, $requestedBy);
        } else {
            $this->generateExcelReport('document-summary', $data, $requestedBy);
        }
    }

    /**
     * Generate approval performance report
     */
    public function generateApprovalPerformanceReport(array $parameters, User $requestedBy)
    {
        $startDate = Carbon::parse($parameters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($parameters['end_date'] ?? now()->endOfMonth());

        // Get approval data
        $documentApprovals = DocumentApproval::whereBetween('created_at', [$startDate, $endDate])
            ->with(['documentRequest', 'approver'])
            ->get();

        $agreementApprovals = AgreementApproval::whereBetween('created_at', [$startDate, $endDate])
            ->with(['agreementOverview', 'approver'])
            ->get();

        // Calculate performance metrics
        $performanceData = [];
        $allApprovals = $documentApprovals->merge($agreementApprovals);

        foreach ($allApprovals->groupBy('approver_nik') as $approverNik => $approvals) {
            $approver = $approvals->first()->approver;
            $totalApprovals = $approvals->count();
            $approvedCount = $approvals->where('status', 'approved')->count();
            $rejectedCount = $approvals->where('status', 'rejected')->count();
            $pendingCount = $approvals->where('status', 'pending')->count();

            // Calculate average approval time
            $approvedApprovals = $approvals->where('status', 'approved')->whereNotNull('approved_at');
            $avgApprovalTime = 0;
            
            if ($approvedApprovals->count() > 0) {
                $totalHours = 0;
                foreach ($approvedApprovals as $approval) {
                    $totalHours += $approval->created_at->diffInHours($approval->approved_at);
                }
                $avgApprovalTime = round($totalHours / $approvedApprovals->count(), 2);
            }

            $performanceData[] = [
                'approver' => $approver,
                'total_approvals' => $totalApprovals,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'pending_count' => $pendingCount,
                'approval_rate' => $totalApprovals > 0 ? round(($approvedCount / $totalApprovals) * 100, 2) : 0,
                'avg_approval_time_hours' => $avgApprovalTime
            ];
        }

        $data = [
            'performance_data' => $performanceData,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'generated_at' => now(),
            'generated_by' => $requestedBy->name
        ];

        $this->generatePdfReport('approval-performance', $data, $requestedBy);
    }

    /**
     * Generate monthly summary report
     */
    public function generateMonthlySummaryReport(array $parameters, User $requestedBy)
    {
        $month = $parameters['month'] ?? now()->month;
        $year = $parameters['year'] ?? now()->year;
        
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $data = [
            'month' => $month,
            'year' => $year,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'document_stats' => $this->getDocumentStats($startDate, $endDate),
            'approval_stats' => $this->getApprovalStats($startDate, $endDate),
            'user_activity_stats' => $this->getUserActivityStats($startDate, $endDate),
            'generated_at' => now(),
            'generated_by' => $requestedBy->name
        ];

        $this->generatePdfReport('monthly-summary', $data, $requestedBy);
    }

    /**
     * Generate user activity report
     */
    public function generateUserActivityReport(array $parameters, User $requestedBy)
    {
        $startDate = Carbon::parse($parameters['start_date'] ?? now()->startOfMonth());
        $endDate = Carbon::parse($parameters['end_date'] ?? now()->endOfMonth());
        $userNik = $parameters['user_nik'] ?? null;

        $query = ActivityLog::whereBetween('created_at', [$startDate, $endDate]);
        
        if ($userNik) {
            $query->where('user_nik', $userNik);
        }

        $activities = $query->orderBy('created_at', 'desc')->get();

        $data = [
            'activities' => $activities,
            'user_nik' => $userNik,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'generated_at' => now(),
            'generated_by' => $requestedBy->name
        ];

        $this->generatePdfReport('user-activity', $data, $requestedBy);
    }

    /**
     * Generate PDF report
     */
    private function generatePdfReport(string $template, array $data, User $requestedBy)
    {
        try {
            $pdf = Pdf::loadView("reports.{$template}", $data);
            $filename = "{$template}-" . now()->format('Y-m-d-H-i-s') . '.pdf';
            $filepath = storage_path("app/reports/{$filename}");

            // Ensure directory exists
            if (!file_exists(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }

            $pdf->save($filepath);

            // Send notification with download link
            Notification::create([
                'recipient_nik' => $requestedBy->nik,
                'recipient_name' => $requestedBy->name,
                'sender_nik' => 'system',
                'sender_name' => 'System',
                'title' => 'Report Generated Successfully',
                'message' => "Your {$template} report has been generated. File: {$filename}",
                'type' => 'document_completed',
                'related_type' => null,
                'related_id' => null
            ]);

        } catch (\Exception $e) {
            \Log::error('PDF Report Generation Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate Excel report
     */
    private function generateExcelReport(string $template, array $data, User $requestedBy)
    {
        // Implementation for Excel reports using Maatwebsite/Excel
        // This would create Excel exports based on the template and data
    }

    /**
     * Get document statistics
     */
    private function getDocumentStats(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'total_created' => DocumentRequest::whereBetween('created_at', [$startDate, $endDate])->count(),
            'completed' => DocumentRequest::whereBetween('completed_at', [$startDate, $endDate])->count(),
            'pending' => DocumentRequest::whereNotIn('status', [DocumentRequest::STATUS_COMPLETED, DocumentRequest::STATUS_REJECTED])
                ->whereBetween('created_at', [$startDate, $endDate])->count(),
            'rejected' => DocumentRequest::where('status', DocumentRequest::STATUS_REJECTED)
                ->whereBetween('created_at', [$startDate, $endDate])->count(),
        ];
    }

    /**
     * Get approval statistics  
     */
    private function getApprovalStats(Carbon $startDate, Carbon $endDate): array
    {
        $documentApprovals = DocumentApproval::whereBetween('created_at', [$startDate, $endDate]);
        $agreementApprovals = AgreementApproval::whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total_document_approvals' => $documentApprovals->count(),
            'total_agreement_approvals' => $agreementApprovals->count(),
            'approved_documents' => $documentApprovals->where('status', 'approved')->count(),
            'approved_agreements' => $agreementApprovals->where('status', 'approved')->count(),
            'pending_documents' => $documentApprovals->where('status', 'pending')->count(),
            'pending_agreements' => $agreementApprovals->where('status', 'pending')->count(),
        ];
    }

    /**
     * Get user activity statistics
     */
    private function getUserActivityStats(Carbon $startDate, Carbon $endDate): array
    {
        $activities = ActivityLog::whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total_activities' => $activities->count(),
            'unique_users' => $activities->distinct('user_nik')->count(),
            'most_active_users' => $activities->groupBy('user_nik')
                ->selectRaw('user_nik, user_name, count(*) as activity_count')
                ->orderBy('activity_count', 'desc')
                ->limit(10)
                ->get()
        ];
    }
}