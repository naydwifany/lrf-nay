<?php
// app/Filament/Admin/Resources/AgreementOverviewResource/Pages/ViewAgreementOverview.php

namespace App\Filament\Admin\Resources\AgreementOverviewResource\Pages;

use App\Filament\Admin\Resources\AgreementOverviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewAgreementOverview extends ViewRecord
{
    protected static string $resource = AgreementOverviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn() => $this->record->is_draft),
                
            Actions\Action::make('submit_for_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Submit Agreement Overview')
                ->modalDescription('Are you sure you want to submit this agreement overview for approval? You won\'t be able to edit it after submission.')
                ->action(function () {
                    try {
                        $this->record->update([
                            'is_draft' => false,
                            'status' => 'submitted',
                            'submitted_at' => now(),
                        ]);
                        
                        Notification::make()
                            ->title('Agreement Overview submitted successfully')
                            ->body('Your agreement overview has been submitted for approval.')
                            ->success()
                            ->send();
                            
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error submitting agreement overview')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn() => $this->record->is_draft && $this->record->status === 'draft'),
                
            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    // PDF generation logic here
                    return response()->download(
                        storage_path('app/agreements/' . $this->record->id . '.pdf'),
                        'Agreement_Overview_' . $this->record->nomor_dokumen . '.pdf'
                    );
                })
                ->visible(fn() => !$this->record->is_draft),
        ];
    }
}