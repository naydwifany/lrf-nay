<?php
// app/Filament/User/Resources/PendingAgreementOverviewResource/Pages/ListPendingAgreementOverviews.php

namespace App\Filament\User\Resources\PendingAgreementOverviewResource\Pages;

use App\Filament\User\Resources\PendingAgreementOverviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPendingAgreementOverviews extends ListRecords
{
    protected static string $resource = PendingAgreementOverviewResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->redirect(request()->header('Referer'))),
                
            Actions\Action::make('view_all_ao')
                ->label('View All My AOs')
                ->icon('heroicon-o-document-duplicate')
                ->url(fn () => route('filament.user.resources.my-agreement-overviews.index'))
                ->color('gray'),
        ];
    }

    public function getTitle(): string
    {
        $count = $this->getTableQuery()->count();
        return "Pending Agreement Overviews ({$count})";
    }

    public function getHeading(): string
    {
        return 'Agreement Overviews Pending Your Approval';
    }

    public function getSubheading(): string
    {
        $user = auth()->user();
        $roleDescription = match($user->role) {
            'head' => 'Head Level Approval',
            'general_manager' => 'General Manager Approval', 
            'finance' => 'Finance Review & Approval',
            'legal_admin' => 'Legal Admin Review',
            'director' => 'Director Approval',
            default => 'Approval Required'
        };
        
        return "Role: {$roleDescription} | Auto-refresh every 30 seconds";
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::getResource()::getEloquentQuery();
    }


}