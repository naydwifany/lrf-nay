<?php
// app/Filament/User/Resources/MyAgreementOverviewResource/Pages/ListMyAgreementOverviews.php

namespace App\Filament\User\Resources\MyAgreementOverviewResource\Pages;

use App\Filament\User\Resources\MyAgreementOverviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMyAgreementOverviews extends ListRecords
{
    protected static string $resource = MyAgreementOverviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create New Agreement Overview')
                ->icon('heroicon-o-plus-circle'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // You can add widgets here if needed
        ];
    }

    public function getTitle(): string
    {
        return 'My Agreement Overviews';
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No Agreement Overviews Found';
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Create your first agreement overview from approved document requests.';
    }

    protected function getTableEmptyStateIcon(): ?string
    {
        return 'heroicon-o-document-duplicate';
    }
}