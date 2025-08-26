<?php
// app/Jobs/GenerateDocumentReport.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Services\ReportService;

class GenerateDocumentReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reportType;
    protected $parameters;
    protected $requestedBy;

    public function __construct(string $reportType, array $parameters, User $requestedBy)
    {
        $this->reportType = $reportType;
        $this->parameters = $parameters;
        $this->requestedBy = $requestedBy;
    }

    public function handle(ReportService $reportService): void
    {
        switch ($this->reportType) {
            case 'document_summary':
                $reportService->generateDocumentSummaryReport($this->parameters, $this->requestedBy);
                break;
            case 'approval_performance':
                $reportService->generateApprovalPerformanceReport($this->parameters, $this->requestedBy);
                break;
            case 'monthly_summary':
                $reportService->generateMonthlySummaryReport($this->parameters, $this->requestedBy);
                break;
            case 'user_activity':
                $reportService->generateUserActivityReport($this->parameters, $this->requestedBy);
                break;
            default:
                throw new \InvalidArgumentException("Unknown report type: {$this->reportType}");
        }
    }

    public function failed(\Exception $exception)
    {
        \Log::error('Failed to generate report: ' . $exception->getMessage());
        
        // Send notification to user about failed report
        \App\Models\Notification::create([
            'recipient_nik' => $this->requestedBy->nik,
            'recipient_name' => $this->requestedBy->name,
            'sender_nik' => 'system',
            'sender_name' => 'System',
            'title' => 'Report Generation Failed',
            'message' => "Failed to generate {$this->reportType} report. Please try again or contact administrator.",
            'type' => 'system_error',
            'related_type' => null,
            'related_id' => null
        ]);
    }
}