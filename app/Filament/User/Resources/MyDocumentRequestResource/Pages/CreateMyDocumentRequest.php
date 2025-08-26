<?php
namespace App\Filament\User\Resources\MyDocumentRequestResource\Pages;

use App\Filament\User\Resources\MyDocumentRequestResource;
use App\Models\DocumentRequest; // FIXED: Add missing import
use App\Services\DocumentWorkflowService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateMyDocumentRequest extends CreateRecord
{
    protected static string $resource = MyDocumentRequestResource::class;

    // FIXED: Add submitType property
    public $submitType = 'draft'; // Track which button was clicked

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            // Save as Draft button
            Action::make('create')
                ->label('Save as Draft')
                ->keyBindings(['mod+s'])
                ->color('gray')
                ->action(function () {
                    $this->submitType = 'draft';
                    $this->create();
                }),
            
            // Submit button    
            Action::make('submit')
                ->label('Submit')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Submit Document Request')
                ->modalDescription('Are you sure you want to submit this document request? Once submitted, you cannot edit it.')
                ->action(function () {
                    $this->submitType = 'submit';
                    $this->create();
                }),
            
            // Cancel button
            Action::make('cancel')
                ->label('Cancel')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate unique document number
        $data['nomor_dokumen'] = $this->generateUniqueDocumentNumber();
        
        // Set status based on which button was clicked
        if ($this->submitType === 'submit') {
            $data['is_draft'] = false;
            $data['status'] = 'submitted';
            $data['submitted_at'] = now();
        } else {
            $data['is_draft'] = true;
            $data['status'] = 'draft';
        }
        
        // Auto-fill user data
        $data['nik'] = auth()->user()->nik ?? '';
        $data['nama'] = auth()->user()->name ?? '';
        $data['jabatan'] = auth()->user()->jabatan ?? '';
        $data['divisi'] = auth()->user()->divisi ?? '';
        $data['dept'] = auth()->user()->department ?? '';
        $data['direktorat'] = auth()->user()->direktorat ?? '';
        $data['nik_atasan'] = auth()->user()->supervisor_nik ?? '';
        
        return $data;
    }

    private function generateUniqueDocumentNumber(): string
    {
        $divisi = auth()->user()->divisi ?? 'GEN';
        $initial = preg_replace('/[^A-Z]/', '', $divisi);
        if (empty($initial)) {
            $initial = 'GEN';
        }
        
        $month = $this->getRomawi(date('n'));
        $year = date("Y");
        
        // Get the latest document number for this pattern
        $latestDoc = DocumentRequest::where('nomor_dokumen', 'LIKE', "%/LRF/{$initial}/{$month}/{$year}")
            ->orderBy('id', 'desc') // Use ID instead of nomor_dokumen for better sorting
            ->first();
        
        if ($latestDoc && $latestDoc->nomor_dokumen) {
            // Extract sequence number from existing document
            $parts = explode('/', $latestDoc->nomor_dokumen);
            $lastSequence = intval($parts[0]);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }
        
        $seqNumber = str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
        $documentNumber = $seqNumber . "/LRF/" . $initial . "/" . $month . "/" . $year;
        
        // Double check for uniqueness (in case of race condition)
        $attempts = 0;
        while (DocumentRequest::where('nomor_dokumen', $documentNumber)->exists() && $attempts < 10) {
            $nextSequence++;
            $seqNumber = str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
            $documentNumber = $seqNumber . "/LRF/" . $initial . "/" . $month . "/" . $year;
            $attempts++;
        }
        
        return $documentNumber;
    }

    private function getRomawi($month): string
    {
        $romawi = [
            1 => "I", 2 => "II", 3 => "III", 4 => "IV", 5 => "V", 6 => "VI",
            7 => "VII", 8 => "VIII", 9 => "IX", 10 => "X", 11 => "XI", 12 => "XII"
        ];
        
        return $romawi[$month] ?? "I";
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        
        if ($this->submitType === 'submit') {
            // Trigger workflow for submitted documents
            try {
                if (class_exists(DocumentWorkflowService::class)) {
                    app(DocumentWorkflowService::class)->submitDocument($record,auth()->user());
                }
                
                Notification::make()
                    ->title('Document submitted successfully')
                    ->body('Your document has been submitted for approval.')
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                \Log::error('Error in workflow after create', [
                    'document_id' => $record->id,
                    'error' => $e->getMessage()
                ]);
                
                Notification::make()
                    ->title('Document submitted but workflow failed')
                    ->body('Please contact administrator: ' . $e->getMessage())
                    ->warning()
                    ->send();
            }
        } else {
            Notification::make()
                ->title('Document saved as draft')
                ->body('You can edit and submit it later.')
                ->success()
                ->send();
        }
    }

    // FIXED: Override these methods to prevent Filament default behaviors
    protected function getCreateFormAction(): Action
    {
        return Action::make('create')
            ->visible(false); // Hide default create button
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return Action::make('createAnother')
            ->visible(false); // Hide default create another button
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->visible(false); // Hide default cancel button - we have custom one
    }
}