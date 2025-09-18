<?php
// app/Filament/User/Resources/MyDocumentRequest/Pages/CreateMyAgreementOverview.php

namespace App\Filament\User\Resources\MyDocumentRequestResource\Pages\MyAgreementOverviews;

use App\Filament\User\Resources\MyDocumentRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Traits\DirectorManagementTrait;

class CreateMyAgreementOverview extends CreateRecord
{
    use DirectorManagementTrait;
    
    protected static string $resource = MyDocumentRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-fill user info
        $user = auth()->user();
        $data = array_merge($data, [
            'nik' => $user->nik ?? 'NIK_UNKNOWN',
            'nama' => $user->name ?? 'Name Unknown',
            'jabatan' => $user->jabatan ?? 'Position Unknown',
            'divisi' => $user->divisi ?? 'Division Unknown',
            'direktorat' => $user->direktorat ?? 'Directorate Unknown',
            'level' => $user->level ?? 'Level Unknown',
        ]);

        // Auto-fill director1 info
        $director1 = static::getDirector1FromDirektorat($user->direktorat ?? 'IT');
        $data['director1_nik'] = $director1['nik'];
        $data['director1_name'] = $director1['name'];

        // Fill director2_name if director2_nik is selected
        if (!empty($data['director2_nik'])) {
            $director2 = static::getDirector2Details($data['director2_nik']);
            $data['director2_name'] = $director2['name'];
        }

        // Auto-generate nomor_dokumen if empty
        if (empty($data['nomor_dokumen'])) {
            $data['nomor_dokumen'] = static::generateAONumber();
        }

        // Set default values
        $data['is_draft'] = true;
        $data['status'] = 'draft';
        $data['tanggal_ao'] = $data['tanggal_ao'] ?? now();

        // Ensure JSON fields have default values
        if (empty($data['parties'])) {
            $data['parties'] = [
                [
                    'name' => $data['counterparty'] ?? 'Counterparty Name',
                    'type' => 'company',
                    'address' => '',
                    'contact_person' => '',
                    'email' => '',
                    'phone' => ''
                ],
                [
                    'name' => 'PT Eka Cahaya Indopura',
                    'type' => 'company',
                    'address' => '',
                    'contact_person' => '',
                    'email' => '',
                    'phone' => ''
                ]
            ];
        }

        if (empty($data['terms'])) {
            $data['terms'] = [];
        }

        if (empty($data['risks'])) {
            $data['risks'] = [];
        }

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Agreement Overview Created')
            ->body('Your agreement overview has been created successfully as a draft.');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    // Helper methods
    public static function getDirector1FromDirektorat(string $direktorat): array
    {
        $directorMapping = [
            'IT' => [
                'nik' => '14070619',
                'name' => 'Wiradi',
                'title' => 'Finance & Admin IT Director',
                'direktorat' => 'IT'
            ],
            'LEGAL' => [
                'nik' => '20050037',
                'name' => 'Widi Satya Chitra',
                'title' => 'Corporate Secretary, Legal & Business Development Director',
                'direktorat' => 'Legal'
            ],
            'EXECUTIVE' => [
                'nik' => '710144',
                'name' => 'Lyvia Mariana',
                'title' => 'Direktur Utama',
                'direktorat' => 'Executive'
            ],
            'MARKETING' => [
                'nik' => '1201008',
                'name' => 'Michael Iskandar',
                'title' => 'Merchandising & Marketing Director',
                'direktorat' => 'Marketing'
            ],
            'SALES & LOGISTIC' => [
                'nik' => '16010005',
                'name' => 'FA Tri Agus Winarko HS',
                'title' => 'Retail Sales & Logistic Director',
                'direktorat' => 'Sales & Logistic'
            ],
            'WHOLESALES' => [
                'nik' => '20050036',
                'name' => 'Lenny Susilawaty Jamadi',
                'title' => 'Wholesales Director',
                'direktorat' => 'Wholesales'
            ],
        ];

        return $directorMapping[strtoupper($direktorat)] ?? $directorMapping['IT'];
    }

    public static function getDirector2Details($director2Selection): array
    {
        $user = \App\Models\User::where('nik', $director2Selection)->first();

        if ($user) {
            return [
                'nik' => $user->nik,
                'name' => $user->name,
                'jabatan' => $user->jabatan ?? 'Unknown Title',
                'direktorat' => $user->direktorat ?? 'Unknown'
            ];
        }

        return [
            'nik' => 'DIR_UNKNOWN',
            'name' => 'Unknown Director - Selection: ' . $director2Selection,
            'jabatan' => 'Unknown Title',
            'direktorat' => 'Unknown'
        ];
    }

    public static function generateAONumber(): string
    {
        try {
            $lastAO = \DB::table('agreement_overviews')->latest('id')->first();
            $partNumber = $lastAO ? ($lastAO->id + 1) : 1;

            $seqNumber = str_pad($partNumber, 4, '0', STR_PAD_LEFT);
            $month = date('m');
            $year = date('Y');

            return "AO/{$seqNumber}/{$month}/{$year}";
        } catch (\Exception $e) {
            $partNumber = rand(1000, 9999);
            $month = date('m');
            $year = date('Y');

            return "AO/{$partNumber}/{$month}/{$year}";
        }
    }
}