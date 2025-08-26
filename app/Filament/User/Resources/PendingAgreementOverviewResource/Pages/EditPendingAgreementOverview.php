<?php

namespace App\Filament\User\Resources\PendingAgreementOverviewResource\Pages;

use App\Filament\User\Resources\PendingAgreementOverviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPendingAgreementOverview extends EditRecord
{
    protected static string $resource = PendingAgreementOverviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
