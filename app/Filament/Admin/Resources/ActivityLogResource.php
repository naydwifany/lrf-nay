<?php
// app/Filament/Admin/Resources/ActivityLogResource.php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'System Management';
    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false; // Activity logs are auto-generated
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Activity Information')
                    ->schema([
                        Forms\Components\TextInput::make('user_nik')
                            ->label('User NIK')
                            ->disabled(),
                        Forms\Components\TextInput::make('user_name')
                            ->label('User Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('action')
                            ->disabled(),
                        Forms\Components\TextInput::make('model_type')
                            ->label('Model Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('model_id')
                            ->label('Model ID')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Activity Details')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->disabled()
                            ->rows(3),
                        Forms\Components\Textarea::make('old_values')
                            ->label('Old Values (JSON)')
                            ->disabled()
                            ->rows(4),
                        Forms\Components\Textarea::make('new_values')
                            ->label('New Values (JSON)')
                            ->disabled()
                            ->rows(4),
                    ]),

                Forms\Components\Section::make('Request Information')
                    ->schema([
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled(),
                        Forms\Components\TextInput::make('user_agent')
                            ->label('User Agent')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('action')
                    ->colors([
                        'success' => 'created',
                        'warning' => 'updated',
                        'danger' => 'deleted',
                        'info' => 'viewed',
                        'primary' => 'login',
                        'gray' => 'logout',
                    ]),
                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model')
                    ->formatStateUsing(fn($state) => class_basename($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'viewed' => 'Viewed',
                        'login' => 'Login',
                        'logout' => 'Logout',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'submitted' => 'Submitted',
                    ]),
                SelectFilter::make('model_type')
                    ->options([
                        'App\Models\DocumentRequest' => 'Document Request',
                        'App\Models\AgreementOverview' => 'Agreement Overview',
                        'App\Models\DocumentComment' => 'Document Comment',
                        'App\Models\User' => 'User',
                    ]),
                SelectFilter::make('user_nik')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Date From'),
                        DatePicker::make('created_until')
                            ->label('Date Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Activity Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('user_name')
                            ->label('User'),
                        Infolists\Components\TextEntry::make('action')
                            ->badge(),
                        Infolists\Components\TextEntry::make('model_type')
                            ->label('Model Type')
                            ->formatStateUsing(fn($state) => class_basename($state)),
                        Infolists\Components\TextEntry::make('model_id')
                            ->label('Model ID'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                    ])->columns(3),

                Infolists\Components\Section::make('Description')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Data Changes')
                    ->schema([
                        Infolists\Components\TextEntry::make('old_values')
                            ->label('Old Values')
                            ->formatStateUsing(fn($state) => $state ? json_encode(json_decode($state), JSON_PRETTY_PRINT) : 'N/A')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('new_values')
                            ->label('New Values')
                            ->formatStateUsing(fn($state) => $state ? json_encode(json_decode($state), JSON_PRETTY_PRINT) : 'N/A')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Request Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('ip_address')
                            ->label('IP Address'),
                        Infolists\Components\TextEntry::make('user_agent')
                            ->label('User Agent')
                            ->limit(100),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}