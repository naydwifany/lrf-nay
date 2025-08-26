<?php
namespace App\Filament\Admin\Resources\AgreementOverviewResource\Pages;

use App\Filament\Admin\Resources\AgreementOverviewResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\DocumentRequest;
use Filament\Notifications\Notification;

class CreateAgreementOverview extends CreateRecord
{
    protected static string $resource = AgreementOverviewResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-fill from selected document request
        if (isset($data['document_request_id'])) {
            $documentRequest = DocumentRequest::find($data['document_request_id']);
            if ($documentRequest) {
                // Auto-fill user information from document request
                $data['nik'] = $data['nik'] ?? $documentRequest->nik;
                $data['nama'] = $data['nama'] ?? $documentRequest->nama;
                $data['jabatan'] = $data['jabatan'] ?? $documentRequest->jabatan;
                $data['divisi'] = $data['divisi'] ?? $documentRequest->divisi;
                $data['direktorat'] = $data['direktorat'] ?? $documentRequest->direktorat;

                // Generate document number if not provided
                if (empty($data['nomor_dokumen'])) {
                    $data['nomor_dokumen'] = $this->generateAgreementNumber($documentRequest);
                }
            }
        }

        // Set default values
        $data['is_draft'] = true;
        $data['status'] = 'draft';
        $data['tanggal_ao'] = $data['tanggal_ao'] ?? now()->toDateString();

        return $data;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Agreement Overview created successfully')
            ->body('You can now edit and submit for approval.')
            ->success()
            ->send();
    }

    private function generateAgreementNumber(DocumentRequest $documentRequest): string
    {
        $prefix = 'AO';
        $divisiCode = strtoupper(substr($documentRequest->divisi ?? 'GEN', 0, 3));
        $year = date('Y');
        $month = date('m');
        
        // Get next sequence number
        $lastAgreement = \App\Models\AgreementOverview::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('nomor_dokumen', 'like', "{$prefix}/%")
            ->latest()
            ->first();
            
        $sequence = 1;
        if ($lastAgreement) {
            $lastNumber = explode('/', $lastAgreement->nomor_dokumen)[1] ?? '0000';
            $sequence = (int)$lastNumber + 1;
        }
        
        return sprintf('%s/%04d/%s/%s/%s', $prefix, $sequence, $divisiCode, $month, $year);
    }
}