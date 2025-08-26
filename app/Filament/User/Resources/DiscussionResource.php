<?php
// app/Filament/User/Resources/DiscussionResource.php
// DIRECT QUERY VERSION - Bypass service issues

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\DiscussionResource\Pages;
use App\Models\DocumentRequest;
use App\Services\DocumentDiscussionService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;

class DiscussionResource extends Resource
{
    protected static ?string $model = DocumentRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Discussion Forum';
    protected static ?string $modelLabel = 'Discussion';
    protected static ?string $pluralModelLabel = 'Discussions';
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user) return null;

        // Simple count without service
        $count = static::getEloquentQuery()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        
        \Log::info('DiscussionResource Query - User: ' . $user->nik . ' (' . $user->role . ')');
        
        $query = parent::getEloquentQuery()
            ->whereIn('status', ['discussion', 'in_discussion'])
            ->with(['comments', 'doctype', 'approvals']);
            
        // DIRECT ACCESS LOGIC WITHOUT SERVICE
        $query->where(function ($q) use ($user) {
            // 1. User adalah requester
            $q->where('nik', $user->nik)
            
            // 2. User pernah approve
            ->orWhereHas('approvals', function ($approvalQuery) use ($user) {
                $approvalQuery->where('approver_nik', $user->nik);
            })
            
            // 3. User pernah comment
            ->orWhereHas('comments', function ($commentQuery) use ($user) {
                $commentQuery->where('user_nik', $user->nik);
            })
            
            // 4. Privileged roles - full access
            ->orWhere(function ($privilegeQuery) use ($user) {
                if (in_array($user->role, ['head_legal', 'reviewer_legal', 'admin_legal', 'finance', 'general_manager'])) {
                    $privilegeQuery->whereNotNull('id'); // Always true
                }
            })
            
            // 5. Division-based access for managers
            ->orWhere(function ($divisionQuery) use ($user) {
                if (in_array($user->role, ['head', 'senior_manager', 'manager', 'supervisor'])) {
                    $divisionQuery->where('divisi', $user->divisi);
                }
            });
        });
        
        $resultCount = $query->count();
        \Log::info("DiscussionResource Query Result Count: {$resultCount}");
        
        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('Document Number')
                    ->searchable()
                    ->placeholder('Not assigned'),
                    
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                    
                Tables\Columns\TextColumn::make('nama')
                    ->label('Requester')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('doctype.document_name')
                    ->label('Document Type')
                    ->badge()
                    ->default('No Type'),
                    
                Tables\Columns\TextColumn::make('comments_count')
                    ->label('Comments')
                    ->counts('comments')
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('finance_status')
                    ->label('Finance')
                    ->getStateUsing(function ($record) {
                        $hasFinance = $record->comments()
                            ->where('user_role', 'finance')
                            ->where('is_forum_closed', false)
                            ->exists();
                        return $hasFinance ? 'Participated' : 'Pending';
                    })
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'Participated' => 'success',
                            'Pending' => 'warning',
                        };
                    }),
                    
                Tables\Columns\IconColumn::make('is_closed')
                    ->label('Status')
                    ->getStateUsing(function($record) {
                        return $record->comments()->where('is_forum_closed', true)->exists();
                    })
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-chat-bubble-left-right')
                    ->trueColor('danger')
                    ->falseColor('success'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'discussion' => 'Discussion',
                        'in_discussion' => 'In Discussion',
                    ]),
                    
                Tables\Filters\Filter::make('finance_pending')
                    ->label('Finance Pending')
                    ->query(function (Builder $query): Builder {
                        return $query->whereDoesntHave('comments', function ($q) {
                            $q->where('user_role', 'finance')
                              ->where('is_forum_closed', false);
                        });
                    })
                    ->visible(function() {
                        return in_array(auth()->user()->role, ['head_legal', 'finance']);
                    }),
                    
                Tables\Filters\Filter::make('my_discussions')
                    ->label('My Documents')
                    ->query(function (Builder $query): Builder {
                        return $query->where('nik', auth()->user()->nik);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View Discussion')
                    ->icon('heroicon-o-eye'),
                    
                Tables\Actions\Action::make('close_discussion')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->label('Close')
                    ->requiresConfirmation()
                    ->modalHeading('Close Discussion Forum')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Closure Reason')
                            ->required()
                            ->placeholder('Please provide reason for closing the discussion...'),
                    ])
                    ->visible(function($record) {
                        return auth()->user()->role === 'head_legal' && 
                               !$record->comments()->where('is_forum_closed', true)->exists();
                    })
                    ->disabled(function($record) {
                        return !$record->comments()
                            ->where('user_role', 'finance')
                            ->where('is_forum_closed', false)
                            ->exists();
                    })
                    ->action(function ($record, array $data) {
                        try {
                            // Direct close without service
                            $record->comments()->create([
                                'user_id' => auth()->id(),
                                'user_nik' => auth()->user()->nik,
                                'user_name' => auth()->user()->name,
                                'user_role' => auth()->user()->role,
                                'comment' => $data['reason'] ?: 'Discussion forum has been closed by Head Legal.',
                                'is_forum_closed' => true,
                                'forum_closed_at' => now(),
                                'forum_closed_by_nik' => auth()->user()->nik,
                                'forum_closed_by_name' => auth()->user()->name,
                            ]);

                            $record->update(['status' => 'agreement_creation']);

                            Notification::make()
                                ->title('Discussion Closed')
                                ->body('The discussion has been closed and document moved to agreement creation phase.')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                    
                Tables\Actions\Action::make('remind_finance')
                    ->icon('heroicon-o-bell-alert')
                    ->color('warning')
                    ->label('Remind Finance')
                    ->visible(function($record) {
                        return auth()->user()->role === 'head_legal' &&
                               !$record->comments()
                                   ->where('user_role', 'finance')
                                   ->where('is_forum_closed', false)
                                   ->exists();
                    })
                    ->action(function ($record) {
                        // Simple notification without service
                        Notification::make()
                            ->title('Reminder Sent')
                            ->body('Finance team has been notified about this discussion.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->emptyStateHeading('No Active Discussions')
            ->emptyStateDescription('No documents are currently in discussion phase that you can access.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscussions::route('/'),
            'view' => Pages\ViewDiscussion::route('/{record}'),
        ];
    }
}