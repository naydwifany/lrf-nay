<?php
// app/Filament/Admin/Resources/NotificationResource.php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\NotificationResource\Pages;
use App\Models\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationGroup = 'System Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notification Details')
                    ->schema([
                        Forms\Components\Select::make('recipient_nik')
                            ->relationship('recipient', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('type')
                            ->options([
                                'document_submitted' => 'Document Submitted',
                                'approval_required' => 'Approval Required',
                                'document_approved' => 'Document Approved',
                                'document_rejected' => 'Document Rejected',
                                'discussion_started' => 'Discussion Started',
                                'comment_added' => 'Comment Added',
                                'agreement_created' => 'Agreement Created',
                                'reminder' => 'Reminder',
                                'system' => 'System Notification',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->rows(3),
                    ])->columns(2),

                Forms\Components\Section::make('Related Information')
                    ->schema([
                        Forms\Components\Select::make('document_request_id')
                            ->relationship('documentRequest', 'title')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('agreement_overview_id')
                            ->relationship('agreementOverview', 'nomor_dokumen')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('action_url')
                            ->label('Action URL')
                            ->url()
                            ->maxLength(500),
                    ])->columns(3),

                Forms\Components\Section::make('Status & Scheduling')
                    ->schema([
                        Forms\Components\Toggle::make('is_read')
                            ->label('Mark as Read')
                            ->default(false),
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Schedule For')
                            ->helperText('Leave empty to send immediately'),
                        Forms\Components\DateTimePicker::make('read_at')
                            ->label('Read At')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Additional Data')
                    ->schema([
                        Forms\Components\KeyValue::make('data')
                            ->label('Additional Data')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'document_submitted',
                        'warning' => 'approval_required',
                        'success' => 'document_approved',
                        'danger' => 'document_rejected',
                        'info' => 'discussion_started',
                        'gray' => 'system',
                    ]),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('recipient.name')
                    ->label('Recipient')
                    ->searchable(),
                Tables\Columns\TextColumn::make('documentRequest.title')
                    ->label('Related Document')
                    ->limit(30)
                    ->default('N/A'),
                Tables\Columns\IconColumn::make('is_read')
                    ->boolean()
                    ->label('Read'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('read_at')
                    ->dateTime()
                    ->placeholder('Unread'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'document_submitted' => 'Document Submitted',
                        'approval_required' => 'Approval Required',
                        'document_approved' => 'Document Approved',
                        'document_rejected' => 'Document Rejected',
                        'discussion_started' => 'Discussion Started',
                        'comment_added' => 'Comment Added',
                        'agreement_created' => 'Agreement Created',
                        'reminder' => 'Reminder',
                        'system' => 'System Notification',
                    ]),
                SelectFilter::make('is_read')
                    ->options([
                        1 => 'Read',
                        0 => 'Unread',
                    ]),
                SelectFilter::make('recipient_nik')
                    ->relationship('recipient', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('mark_read')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn($record) => !$record->is_read)
                    ->action(function ($record) {
                        $record->update([
                            'is_read' => true,
                            'read_at' => now(),
                        ]);
                    }),
                Tables\Actions\Action::make('mark_unread')
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->visible(fn($record) => $record->is_read)
                    ->action(function ($record) {
                        $record->update([
                            'is_read' => false,
                            'read_at' => null,
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('mark_all_read')
                        ->label('Mark All as Read')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'is_read' => true,
                                    'read_at' => now(),
                                ]);
                            });
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Notification Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('type')
                            ->badge(),
                        Infolists\Components\TextEntry::make('title'),
                        Infolists\Components\TextEntry::make('recipient.name'),
                        Infolists\Components\IconEntry::make('is_read')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('read_at')
                            ->dateTime()
                            ->placeholder('Unread'),
                    ])->columns(3),

                Infolists\Components\Section::make('Message Content')
                    ->schema([
                        Infolists\Components\TextEntry::make('message')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Related Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('documentRequest.title')
                            ->label('Related Document'),
                        Infolists\Components\TextEntry::make('agreementOverview.nomor_dokumen')
                            ->label('Related Agreement'),
                        Infolists\Components\TextEntry::make('action_url')
                            ->label('Action URL')
                            ->url(),
                    ])->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            'create' => Pages\CreateNotification::route('/create'),
            'view' => Pages\ViewNotification::route('/{record}'),
            'edit' => Pages\EditNotification::route('/{record}/edit'),
        ];
    }
}