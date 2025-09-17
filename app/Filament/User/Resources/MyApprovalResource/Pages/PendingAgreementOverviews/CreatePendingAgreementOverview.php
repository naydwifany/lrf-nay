<?php

namespace App\Filament\User\Resources\MyApprovalResource\Pages\PendingAgreementOverviews;

use App\Filament\User\Resources\MyApprovalResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePendingAgreementOverview extends CreateRecord
{
    protected static string $resource = MyApprovalResource::class;
}
