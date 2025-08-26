<?php

// app/Filament/User/Resources/DiscussionResource/Pages/ListDiscussions.php

namespace App\Filament\User\Resources\DiscussionResource\Pages;

use App\Filament\User\Resources\DiscussionResource;
use App\Services\DocumentDiscussionService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListDiscussions extends ListRecords
{
    protected static string $resource = DiscussionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    // Simple refresh without redirect
                    $this->resetTable();
                    
                    Notification::make()
                        ->title('Refreshed')
                        ->body('Discussion list has been updated.')
                        ->success()
                        ->send();
                }),
        ];
    }

    // Show notification untuk finance users yang belum participate
    public function mount(): void
    {
        parent::mount();
        
        $user = auth()->user();
        $service = app(DocumentDiscussionService::class);
        
        // Finance user notification
        if ($user->role === 'finance') {
            $pendingCount = \App\Models\DocumentRequest::where('status', 'discussion')
                ->whereDoesntHave('comments', function ($query) {
                    $query->where('user_role', 'finance')
                          ->where('is_forum_closed', false);
                })
                ->get()
                ->filter(function ($document) use ($service, $user) {
                    return $service->canUserAccessDiscussion($document, $user);
                })
                ->count();
            
            if ($pendingCount > 0) {
                Notification::make()
                    ->title('Finance Input Required')
                    ->body("You have {$pendingCount} discussions awaiting your participation.")
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }
        
        // General unread notification
        $unreadCount = $service->getUnreadDiscussionsCount($user);
        if ($unreadCount > 0) {
            Notification::make()
                ->title('New Discussion Activity')
                ->body("You have {$unreadCount} discussions with new comments.")
                ->info()
                ->send();
        }
    }

    // Auto refresh table
    protected function getTablePollingInterval(): ?string
    {
        return '30s';
    }

    // Dynamic title with unread count
    public function getTitle(): string
    {
        $user = auth()->user();
        $service = app(DocumentDiscussionService::class);
        $unreadCount = $service->getUnreadDiscussionsCount($user);
        
        $baseTitle = 'Discussion Forum';
        return $unreadCount > 0 ? "{$baseTitle} ({$unreadCount})" : $baseTitle;
    }
}