<?php
// app/Filament/User/Resources/MyHistoryResource.php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\MyHistoryResource\Pages;
use App\Models\DocumentRequest;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;

class MyHistoryResource extends Resource
{
    protected static ?string $model = DocumentRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Document History';
    protected static ?string $modelLabel = 'Document History';
    protected static ?string $pluralModelLabel = 'Document History';
    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('nik', auth()->user()->nik)
            ->where(function ($query) {
                $query->where('status', 'completed')
                      ->orWhere('status', 'rejected');
            });
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('Document Number')
                    ->searchable()
                    ->placeholder('Not assigned'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('doctype.document_name')
                    ->label('Document Type')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'completed',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('processing_time')
                    ->label('Processing Time')
                    ->getStateUsing(function ($record) {
                        if ($record->submitted_at && $record->completed_at) {
                            return $record->submitted_at->diffForHumans($record->completed_at, true);
                        }
                        return 'N/A';
                    })
                    ->badge()
                    ->color(function ($state, $record) {
                        if (!$record->submitted_at || !$record->completed_at) return 'gray';
                        $days = $record->submitted_at->diffInDays($record->completed_at);
                        return $days <= 7 ? 'success' : ($days <= 14 ? 'warning' : 'danger');
                    }),
                Tables\Columns\IconColumn::make('has_agreement')
                    ->label('Agreement Created')
                    ->getStateUsing(fn($record) => $record->agreementOverview !== null)
                    ->boolean()
                    ->trueIcon('heroicon-o-document-duplicate')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),
                SelectFilter::make('tipe_dokumen')
                    ->relationship('doctype', 'document_name')
                    ->searchable()
                    ->preload(),
                Filter::make('submitted_at')
                    ->form([
                        DatePicker::make('submitted_from')
                            ->label('Submitted From'),
                        DatePicker::make('submitted_until')
                            ->label('Submitted Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['submitted_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '>=', $date),
                            )
                            ->when(
                                $data['submitted_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('view_agreement')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->visible(fn($record) => $record->agreementOverview !== null)
                    ->url(fn($record) => MyAgreementOverviewResource::getUrl('view', ['record' => $record->agreementOverview])),
                Tables\Actions\Action::make('download_documents')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'completed')
                    ->action(function ($record) {
                        // Generate ZIP with all documents
                        return response()->download(storage_path('app/documents/' . $record->id . '_complete.zip'));
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('completed_at', 'desc')
            ->emptyStateHeading('No Document History')
            ->emptyStateDescription('Your completed and rejected documents will appear here.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Document Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('nomor_dokumen')
                            ->label('Document Number'),
                        Infolists\Components\TextEntry::make('title'),
                        Infolists\Components\TextEntry::make('doctype.document_name')
                            ->label('Document Type')
                            ->badge(),
                        Infolists\Components\TextEntry::make('status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('priority')
                            ->badge(),
                    ])->columns(3),

                Infolists\Components\Section::make('Timeline')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('submitted_at')
                            ->label('Submitted')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Completed')
                            ->dateTime()
                            ->placeholder('Not completed'),
                        Infolists\Components\TextEntry::make('total_processing_time')
                            ->label('Total Processing Time')
                            ->getStateUsing(function ($record) {
                                if ($record->submitted_at && $record->completed_at) {
                                    return $record->submitted_at->diffForHumans($record->completed_at, true);
                                }
                                return 'N/A';
                            }),
                    ])->columns(4),

                Infolists\Components\Section::make('Approval History')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('approvals')
                            ->schema([
                                Infolists\Components\TextEntry::make('approver_name')
                                    ->label('Approver'),
                                Infolists\Components\TextEntry::make('approval_type')
                                    ->label('Type'),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('approved_at')
                                    ->label('Date')
                                    ->dateTime(),
                                Infolists\Components\TextEntry::make('comments')
                                    ->label('Comments')
                                    ->placeholder('No comments'),
                            ])
                            ->columns(5),
                    ]),

                Infolists\Components\Section::make('Related Agreement')
                    ->schema([
                        Infolists\Components\TextEntry::make('agreementOverview.nomor_dokumen')
                            ->label('Agreement Number')
                            ->placeholder('No agreement created'),
                        Infolists\Components\TextEntry::make('agreementOverview.status')
                            ->label('Agreement Status')
                            ->badge()
                            ->placeholder('N/A'),
                        Infolists\Components\TextEntry::make('agreementOverview.tanggal_ao')
                            ->label('Agreement Date')
                            ->date()
                            ->placeholder('N/A'),
                    ])->columns(3)
                    ->visible(fn($record) => $record->agreementOverview !== null),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyHistory::route('/'),
            'view' => Pages\ViewMyHistory::route('/{record}'),
        ];
    }
}