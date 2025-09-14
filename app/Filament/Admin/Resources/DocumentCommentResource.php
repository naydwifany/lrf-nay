<?php

// app/Filament/Admin/Resources/DocumentCommentResource.php - UPDATED

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DocumentCommentResource\Pages;
use App\Models\DocumentComment;
use App\Models\DocumentRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;

class DocumentCommentResource extends Resource
{
    protected static ?string $model = DocumentComment::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    /* protected static ?string $navigationLabel = 'Discussion Comments'; */
    protected static ?string $navigationGroup = 'Document Management';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Comment Details')
                    ->schema([
                        Forms\Components\Select::make('document_request_id')
                            ->relationship('documentRequest', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Document'),
                            
                        Forms\Components\TextInput::make('user_nik')
                            ->label('User NIK')
                            ->required(),
                            
                        Forms\Components\TextInput::make('user_name')
                            ->label('User Name')
                            ->required(),
                            
                        Forms\Components\Select::make('user_role')
                            ->label('User Role')
                            ->options([
                                'head_legal' => 'Head Legal',
                                'general_manager' => 'General Manager',
                                'senior_manager' => 'Senior Manager',
                                'manager' => 'Manager',
                                'supervisor' => 'Supervisor',
                                'reviewer_legal' => 'Legal Reviewer',
                                'admin_legal' => 'Admin Legal',
                                'finance' => 'Finance',
                                'head' => 'Head/Manager',
                                'system' => 'System',
                            ])
                            ->required(),
                            
                        Forms\Components\Select::make('parent_id')
                            ->relationship('parent', 'comment')
                            ->searchable()
                            ->preload()
                            ->label('Reply To (Optional)')
                            ->placeholder('Select if this is a reply'),
                    ])->columns(2),

                Forms\Components\Section::make('Comment Content')
                    ->schema([
                        Forms\Components\Textarea::make('comment')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull()
                            ->label('Message Content'),
                    ]),

                Forms\Components\Section::make('Forum Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_forum_closed')
                            ->label('Close Forum')
                            ->helperText('Mark this comment as forum closure (only for Head Legal)'),
                            
                        Forms\Components\DateTimePicker::make('forum_closed_at')
                            ->label('Closed At')
                            ->visible(fn (Forms\Get $get) => $get('is_forum_closed')),
                            
                        Forms\Components\TextInput::make('forum_closed_by_nik')
                            ->label('Closed By NIK')
                            ->visible(fn (Forms\Get $get) => $get('is_forum_closed')),
                            
                        Forms\Components\TextInput::make('forum_closed_by_name')
                            ->label('Closed By Name')
                            ->visible(fn (Forms\Get $get) => $get('is_forum_closed')),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('documentRequest.nomor_dokumen')
                    ->label('Doc Number')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Not assigned'),
                    
                Tables\Columns\TextColumn::make('documentRequest.title')
                    ->label('Document Title')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(function ($record) {
                        return $record->documentRequest?->title;
                    }),
                    
                Tables\Columns\TextColumn::make('user_name')
                    ->label('Author')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->user_nik),
                    
                Tables\Columns\BadgeColumn::make('user_role')
                    ->label('Role')
                    ->colors([
                        'danger' => 'head_legal',
                        'warning' => ['general_manager', 'senior_manager', 'manager'],
                        'success' => 'finance',
                        'primary' => ['reviewer_legal', 'admin_legal'],
                        'secondary' => 'supervisor',
                        'gray' => 'system',
                    ]),
                    
                Tables\Columns\TextColumn::make('comment')
                    ->label('Message')
                    ->limit(60)
                    ->searchable()
                    ->tooltip(function ($record) {
                        return strip_tags($record->comment);
                    }),
                    
                Tables\Columns\TextColumn::make('parent.user_name')
                    ->label('Reply To')
                    ->placeholder('Root message')
                    ->description(fn ($record) => $record->parent ? 'Reply' : 'Original'),
                    
                Tables\Columns\TextColumn::make('attachments_count')
                    ->label('Files')
                    ->counts('attachments')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\IconColumn::make('is_forum_closed')
                    ->label('Closed Forum')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-chat-bubble-left')
                    ->trueColor('danger')
                    ->falseColor('success'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Posted At')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('document_request_id')
                    ->relationship('documentRequest', 'title')
                    ->searchable()
                    ->preload()
                    ->label('Document'),
                    
                SelectFilter::make('user_role')
                    ->options([
                        'head_legal' => 'Head Legal',
                        'general_manager' => 'General Manager',
                        'senior_manager' => 'Senior Manager',
                        'manager' => 'Manager',
                        'supervisor' => 'Supervisor',
                        'reviewer_legal' => 'Legal Reviewer',
                        'admin_legal' => 'Admin Legal',
                        'finance' => 'Finance',
                        'head' => 'Head/Manager',
                        'system' => 'System',
                    ])
                    ->label('User Role'),
                    
                SelectFilter::make('has_attachments')
                    ->options([
                        'yes' => 'With Files',
                        'no' => 'Text Only',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match($data['value'] ?? null) {
                            'yes' => $query->has('attachments'),
                            'no' => $query->doesntHave('attachments'),
                            default => $query,
                        };
                    }),
                    
                Tables\Filters\Filter::make('is_forum_closed')
                    ->label('Forum Closure Comments')
                    ->query(fn (Builder $query): Builder => $query->where('is_forum_closed', true)),
                    
                Tables\Filters\Filter::make('root_comments')
                    ->label('Root Messages Only')
                    ->query(fn (Builder $query): Builder => $query->whereNull('parent_id')),
                    
                Tables\Filters\Filter::make('replies')
                    ->label('Replies Only')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('parent_id')),
                    
                Tables\Filters\Filter::make('finance_comments')
                    ->label('Finance Participation')
                    ->query(fn (Builder $query): Builder => $query->where('user_role', 'finance')),
                    
                Tables\Filters\Filter::make('today')
                    ->label('Today\'s Comments')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => !$record->is_forum_closed),
                    
                Tables\Actions\Action::make('view_discussion')
                    ->label('View Discussion')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn ($record) => route('filament.user.resources.discussions.view', $record->document_request_id))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('close_forum')
                    ->label('Close Forum')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => 
                        auth()->user()->role === 'head_legal' && 
                        !$record->documentRequest?->isDiscussionClosed()
                    )
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Closure Reason')
                            ->required()
                            ->placeholder('Please provide reason for closing the discussion...')
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(\App\Services\DocumentDiscussionService::class)->closeDiscussion(
                                $record->documentRequest,
                                auth()->user(),
                                $data['reason']
                            );
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Discussion Closed')
                                ->body('Discussion has been closed successfully.')
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                    
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => !$record->is_forum_closed),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('export_discussion')
                        ->label('Export Discussion')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function ($records) {
                            // Implementation for exporting discussion
                            \Filament\Notifications\Notification::make()
                                ->title('Export Started')
                                ->body('Discussion export will be processed.')
                                ->info()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s') // Auto-refresh every minute
            ->emptyStateHeading('No Discussion Comments')
            ->emptyStateDescription('No comments found in any discussions.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Comment Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('documentRequest.nomor_dokumen')
                            ->label('Document Number'),
                        Infolists\Components\TextEntry::make('documentRequest.title')
                            ->label('Document Title'),
                        Infolists\Components\TextEntry::make('user_name')
                            ->label('Author'),
                        Infolists\Components\TextEntry::make('user_nik')
                            ->label('Author NIK'),
                        Infolists\Components\BadgeEntry::make('user_role')
                            ->label('Author Role')
                            ->colors([
                                'danger' => 'head_legal',
                                'warning' => ['general_manager', 'senior_manager'],
                                'success' => 'finance',
                                'primary' => ['reviewer_legal', 'admin_legal'],
                            ]),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Posted At')
                            ->dateTime(),
                    ])->columns(3),

                Infolists\Components\Section::make('Comment Content')
                    ->schema([
                        Infolists\Components\TextEntry::make('comment')
                            ->label('Message')
                            ->columnSpanFull()
                            ->prose(),
                    ]),

                Infolists\Components\Section::make('Thread Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('parent.user_name')
                            ->label('Reply To')
                            ->placeholder('This is a root message'),
                        Infolists\Components\TextEntry::make('replies_count')
                            ->label('Replies Count')
                            ->state(fn ($record) => $record->replies()->count()),
                    ])->columns(2),

                Infolists\Components\Section::make('Attachments')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('attachments')
                            ->schema([
                                Infolists\Components\TextEntry::make('original_filename')
                                    ->label('Filename'),
                                Infolists\Components\TextEntry::make('file_size')
                                    ->label('Size')
                                    ->formatStateUsing(fn ($state) => number_format($state / 1024, 2) . ' KB'),
                                Infolists\Components\TextEntry::make('uploaded_by_name')
                                    ->label('Uploaded By'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Uploaded At')
                                    ->since(),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn ($record) => $record->attachments()->exists()),

                Infolists\Components\Section::make('Forum Status')
                    ->schema([
                        Infolists\Components\IconEntry::make('is_forum_closed')
                            ->label('Forum Closed')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('forum_closed_by_name')
                            ->label('Closed By'),
                        Infolists\Components\TextEntry::make('forum_closed_at')
                            ->label('Closed At')
                            ->dateTime(),
                    ])->columns(3)
                    ->visible(fn ($record) => $record->is_forum_closed),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentComments::route('/'),
            // 'create' => Pages\CreateDocumentComment::route('/create'),
            'view' => Pages\ViewDocumentComment::route('/{record}'),
            'edit' => Pages\EditDocumentComment::route('/{record}/edit'),
        ];
    }
    public static function canCreate(): bool
{
    return false; // Disable manual create
}
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['documentRequest', 'attachments', 'parent', 'replies'])
            ->orderBy('created_at', 'desc');
    }

    // Helper untuk admin dashboard widgets
    public static function getDiscussionStats(): array
    {
        $totalComments = static::getModel()::count();
        $todayComments = static::getModel()::whereDate('created_at', today())->count();
        $activeDiscussions = DocumentRequest::where('status', 'discussion')
            ->whereDoesntHave('comments', fn($q) => $q->where('is_forum_closed', true))
            ->count();
        $closedDiscussions = DocumentRequest::where('status', 'discussion')
            ->whereHas('comments', fn($q) => $q->where('is_forum_closed', true))
            ->count();

        return [
            'total_comments' => $totalComments,
            'today_comments' => $todayComments,
            'active_discussions' => $activeDiscussions,
            'closed_discussions' => $closedDiscussions,
        ];
    }
}