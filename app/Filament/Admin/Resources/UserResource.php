<?php
// app/Filament/Admin/Resources/UserResource.php - FIXED

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('nik')
                            ->label('NIK')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Leave empty to keep current password'),
                    ])->columns(2),

                Forms\Components\Section::make('Role & Access')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->options([
                                'user' => 'User',
                                'supervisor' => 'Supervisor',
                                'manager' => 'Manager',
                                'senior_manager' => 'Senior Manager',
                                'general_manager' => 'General Manager',
                                'director' => 'Director',
                                'admin_legal' => 'Admin Legal',
                                'reviewer_legal' => 'Reviewer Legal',
                                'head_legal' => 'Head Legal',
                                'finance' => 'Finance',
                                'head_finance' => 'Head Finance',
                            ])
                            ->required()
                            ->native(false)
                            ->searchable(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true),
                        Forms\Components\Toggle::make('can_access_admin_panel')
                            ->label('Can Access Admin Panel')
                            ->helperText('Allow access to admin panel (for Legal team and Management)')
                            ->default(false),
                    ])->columns(3),
                
                Forms\Components\Section::make('Organization Information')
                    ->schema([
                        Forms\Components\TextInput::make('jabatan')
                            ->label('Position/Job Title')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('divisi')
                            ->label('Division')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('department')
                            ->label('Department')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('direktorat')
                            ->label('Directorate')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('level')
                            ->label('Level')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->helperText('Organizational level (1-10)'),
                        Forms\Components\Select::make('supervisor_nik')
                            ->label('Supervisor')
                            ->relationship('supervisor', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->nik})")
                            ->searchable(['name', 'nik'])
                            ->preload()
                            ->helperText('Select direct supervisor'),
                    ])->columns(3),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('last_login_at')
                            ->label('Last Login')
                            ->disabled(),
                        Forms\Components\TextInput::make('login_attempts')
                            ->label('Failed Login Attempts')
                            ->numeric()
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nik')
                    ->label('NIK')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->colors([
                        'gray' => 'user',
                        'primary' => ['supervisor', 'manager'],
                        'success' => ['senior_manager', 'general_manager'],
                        'warning' => ['director'],
                        'danger' => ['head_legal', 'head_finance'],
                        'info' => ['admin_legal', 'reviewer_legal', 'finance'],
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('jabatan')
                    ->label('Position')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('divisi')
                    ->label('Division')
                    ->searchable()
                    ->limit(20)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('department')
                    ->label('Department')
                    ->searchable()
                    ->limit(20)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('supervisor.name')
                    ->label('Supervisor')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('level')
                    ->label('Level')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state >= 6 => 'success',
                        $state >= 4 => 'warning',
                        $state >= 2 => 'info',
                        default => 'gray'
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\IconColumn::make('can_access_admin_panel')
                    ->label('Admin Access')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->since()
                    ->placeholder('Never')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'user' => 'User',
                        'supervisor' => 'Supervisor',
                        'manager' => 'Manager',
                        'senior_manager' => 'Senior Manager',
                        'general_manager' => 'General Manager',
                        'director' => 'Director',
                        'admin_legal' => 'Admin Legal',
                        'reviewer_legal' => 'Reviewer Legal',
                        'head_legal' => 'Head Legal',
                        'finance' => 'Finance',
                        'head_finance' => 'Head Finance',
                    ])
                    ->multiple(),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
                SelectFilter::make('can_access_admin_panel')
                    ->label('Admin Access')
                    ->options([
                        1 => 'Can Access Admin',
                        0 => 'User Panel Only',
                    ]),
                SelectFilter::make('divisi')
                    ->label('Division')
                    ->options(function () {
                        return User::whereNotNull('divisi')
                            ->distinct()
                            ->pluck('divisi', 'divisi')
                            ->filter()
                            ->toArray();
                    })
                    ->searchable()
                    ->multiple(),
                SelectFilter::make('department')
                    ->label('Department')
                    ->options(function () {
                        return User::whereNotNull('department')
                            ->distinct()
                            ->pluck('department', 'department')
                            ->filter()
                            ->toArray();
                    })
                    ->searchable()
                    ->multiple(),
                SelectFilter::make('level')
                    ->options([
                        1 => 'Level 1',
                        2 => 'Level 2',
                        3 => 'Level 3',
                        4 => 'Level 4 (Manager)',
                        5 => 'Level 5 (Senior Manager)',
                        6 => 'Level 6 (General Manager)',
                        7 => 'Level 7',
                        8 => 'Level 8',
                        9 => 'Level 9',
                        10 => 'Level 10',
                    ])
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reset_password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(6),
                        Forms\Components\TextInput::make('new_password_confirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->required()
                            ->same('new_password'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'password' => bcrypt($data['new_password']),
                            'login_attempts' => 0,
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Password reset successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Reset Password')
                    ->modalDescription('This will reset the user\'s password.'),
                Tables\Actions\Action::make('toggle_status')
                    ->icon(fn($record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn($record) => $record->is_active ? 'warning' : 'success')
                    ->label(fn($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['is_active' => !$record->is_active]);
                    }),
                Tables\Actions\Action::make('toggle_admin_access')
                    ->icon(fn($record) => $record->can_access_admin_panel ? 'heroicon-o-shield-exclamation' : 'heroicon-o-shield-check')
                    ->color(fn($record) => $record->can_access_admin_panel ? 'warning' : 'success')
                    ->label(fn($record) => $record->can_access_admin_panel ? 'Remove Admin Access' : 'Grant Admin Access')
                    ->requiresConfirmation()
                    ->visible(fn($record) => in_array($record->role, ['admin_legal', 'reviewer_legal', 'head_legal', 'finance', 'head_finance', 'general_manager', 'director']))
                    ->action(function ($record) {
                        $record->update(['can_access_admin_panel' => !$record->can_access_admin_panel]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(fn($record) => $record->update(['is_active' => true]));
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(function ($records) {
                            $records->each(fn($record) => $record->update(['is_active' => false]));
                        }),
                    Tables\Actions\BulkAction::make('grant_admin_access')
                        ->label('Grant Admin Access')
                        ->icon('heroicon-o-shield-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if (in_array($record->role, ['admin_legal', 'reviewer_legal', 'head_legal', 'finance', 'head_finance', 'general_manager', 'director'])) {
                                    $record->update(['can_access_admin_panel' => true]);
                                }
                            });
                        }),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->poll('30s')
            ->emptyStateHeading('No Users Found')
            ->emptyStateDescription('Start by creating your first user.')
            ->emptyStateIcon('heroicon-o-users');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('User Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('nik')
                            ->label('NIK')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('name')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('role')
                            ->badge()
                            ->color(fn ($state) => match($state) {
                                'user' => 'gray',
                                'supervisor', 'manager' => 'primary',
                                'senior_manager', 'general_manager' => 'success',
                                'director' => 'warning',
                                'head_legal', 'head_finance' => 'danger',
                                default => 'info'
                            }),
                        Infolists\Components\IconEntry::make('is_active')
                            ->boolean()
                            ->label('Active Status'),
                        Infolists\Components\IconEntry::make('can_access_admin_panel')
                            ->boolean()
                            ->label('Admin Panel Access'),
                    ])->columns(3),

                Infolists\Components\Section::make('Organization')
                    ->schema([
                        Infolists\Components\TextEntry::make('jabatan')
                            ->label('Position'),
                        Infolists\Components\TextEntry::make('divisi')
                            ->label('Division'),
                        Infolists\Components\TextEntry::make('department')
                            ->label('Department'),
                        Infolists\Components\TextEntry::make('direktorat')
                            ->label('Directorate'),
                        Infolists\Components\TextEntry::make('level')
                            ->label('Level')
                            ->badge(),
                        Infolists\Components\TextEntry::make('supervisor.name')
                            ->label('Supervisor'),
                    ])->columns(3),

                Infolists\Components\Section::make('Activity')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('last_login_at')
                            ->label('Last Login')
                            ->dateTime()
                            ->placeholder('Never logged in'),
                        Infolists\Components\TextEntry::make('login_attempts')
                            ->label('Failed Login Attempts')
                            ->badge()
                            ->color(fn ($state) => $state > 3 ? 'danger' : ($state > 0 ? 'warning' : 'success')),
                    ])->columns(4),

                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull()
                            ->placeholder('No notes'),
                    ])
                    ->visible(fn ($record) => filled($record->notes)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}