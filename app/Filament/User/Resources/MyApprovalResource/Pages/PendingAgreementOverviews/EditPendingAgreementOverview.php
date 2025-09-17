<?php

namespace App\Filament\User\Resources\MyApprovalResource\Pages\PendingAgreementOverviews;

use App\Filament\User\Resources\MyApprovalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPendingAgreementOverview extends EditRecord
{
    protected static string $resource = MyApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
