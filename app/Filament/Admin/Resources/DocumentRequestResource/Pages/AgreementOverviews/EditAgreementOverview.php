<?php
// app/Filament/Admin/Resources/AgreementOverviewResource/Pages/EditAgreementOverview.php

namespace App\Filament\Admin\Resources\AgreementOverviewResource\Pages;

use App\Filament\Admin\Resources\AgreementOverviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgreementOverview extends EditRecord
{
    protected static string $resource = AgreementOverviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->is_draft),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}