<?php
// app/Filament/User/Resources/MyApprovalResource/Pages/ViewPendingAO.php

namespace App\Filament\User\Resources\MyApprovalResource\Pages;

use App\Filament\User\Resources\MyApprovalResource;
use App\Models\AgreementOverview;
use App\Services\DocumentWorkflowService;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class ViewPendingAO extends Page
{
    protected static string $resource = MyApprovalResource::class;
    protected static ?string $model = AgreementOverview::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_list')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MyApprovalResource::getUrl()),
        ];
    }

    public function getTitle(): string
    {
        return "Review: {$this->record->nomor_dokumen}";
    }

    public function getHeading(): string
    {
        return 'Agreement Overview Review';
    }

    public function getSubheading(): string
    {
        $workflowService = app(DocumentWorkflowService::class);
        $progress = $workflowService->getAgreementOverviewProgress($this->record);
        $statusLabel = AgreementOverview::getStatusOptions()[$this->record->status] ?? $this->record->status;

        return "Status: {$statusLabel} | Progress: {$progress}% | Submitted: {$this->record->submitted_at?->diffForHumans()}";
    }

    /**
     * @return \Filament\Infolists\Infolist
     */
    public function getInfolist()
    {
        // Memanggil infolist AO dari MyApprovalResource
        return MyApprovalResource::infolist($this->record, 'ao');
    }
}