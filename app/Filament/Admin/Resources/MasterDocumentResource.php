<?php
// app/Filament/Admin/Resources/MasterDocumentResource.php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MasterDocumentResource\Pages;
use App\Models\MasterDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;

class MasterDocumentResource extends Resource
{
    protected static ?string $model = MasterDocument::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Jenis Perjanjian';
    protected static ?string $modelLabel = 'Jenis Perjanjian';
    protected static ?string $pluralModelLabel = 'Jenis Perjanjian';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Jenis Perjanjian')
                    ->schema([
                        Forms\Components\TextInput::make('document_name')
                            ->label('Nama Jenis Perjanjian')
                            ->required()
                            ->maxLength(255)
                            ->helperText('e.g., Perjanjian Sewa Menyewa, Surat Perintah Kerja (Nilai transaksi di atas 80 juta), etc.'),
                        Forms\Components\TextInput::make('document_code')
                            ->label('Inisial Jenis Perjanjian')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Kode unik untuk jenis perjanjian ini (e.g., PSM, SPK79, SPK81, etc.)'),
                        /*
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi (Optional)')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Rincian deskripsi jenis perjanjian ini.'),
                        */
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true)
                            ->helperText('Hanya jenis perjanjian yang aktif yang bisa dipilih oleh PIC'),
                    ])->columns(2),

                // New Section: Notification Settings
                Forms\Components\Section::make('Notification Settings')
                    ->schema([
                        Forms\Components\Toggle::make('enable_notifications')
                            ->label('Enable Notifications')
                            ->default(true)
                            ->helperText('Enable automatic notifications for documents of this type')
                            ->live(),
                        
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('warning_days')
                                    ->label('Warning Days')
                                    ->numeric()
                                    ->default(7)
                                    ->minValue(1)
                                    ->maxValue(365)
                                    ->helperText('Send warning notification X days before due date')
                                    ->suffix('days'),
                                Forms\Components\TextInput::make('urgent_days')
                                    ->label('Urgent Days')
                                    ->numeric()
                                    ->default(3)
                                    ->minValue(1)
                                    ->maxValue(365)
                                    ->helperText('Send urgent notification X days before due date')
                                    ->suffix('days'),
                                Forms\Components\TextInput::make('critical_days')
                                    ->label('Critical Days')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->maxValue(365)
                                    ->helperText('Send critical notification X days before due date')
                                    ->suffix('days'),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('enable_notifications')),

                        Forms\Components\Group::make([
                            Forms\Components\CheckboxList::make('notification_recipients.default_recipients')
                                ->label('Default Recipients')
                                ->options([
                                    'requester' => 'PIC',
                                    'supervisor' => 'Supervisor/Manager',
                                    'legal_team' => 'Legal Team',
                                    'finance' => 'Finance Team',
                                    'head_legal' => 'Head Legal',
                                ])
                                ->default(['requester', 'supervisor'])
                                ->columns(2),

                            /*
                            Forms\Components\TagsInput::make('notification_recipients.custom_emails')
                                ->label('Additional Email Recipients')
                                ->helperText('Enter additional email addresses that should receive notifications')
                                ->placeholder('email@company.com'),
                            */
                        ])
                        ->visible(fn (Forms\Get $get) => $get('enable_notifications')),

                        Forms\Components\Textarea::make('notification_message_template')
                            ->label('Notification Message Template')
                            ->rows(4)
                            ->default('Dokumen LRF Anda pada "{nama_mitra}" dengan jenis perjanjian "{jenis_perjanjian}" memiliki sisa "{hari_tersisa}" hari. Mohon segera periksa dan lakukan tindakan yang diperlukan.')
                        //  ->helperText('Available variables: {document_title}, {document_type}, {days_remaining}, {requester_name}, {due_date}')
                            ->visible(fn (Forms\Get $get) => $get('enable_notifications')),

                        Forms\Components\Repeater::make('notification_settings.custom_rules')
                            ->label('Custom Notification Rules')
                            ->schema([
                                Forms\Components\Select::make('trigger')
                                    ->label('Trigger')
                                    ->options([
                                        'days_before_due' => 'Days Before Due Date',
                                        'status_change' => 'Status Change',
                                        'no_activity' => 'No Activity For X Days',
                                        'overdue' => 'Overdue',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('value')
                                    ->label('Value')
                                    ->numeric()
                                    ->helperText('Number of days or other value based on trigger'),
                                Forms\Components\Select::make('priority')
                                    ->label('Priority')
                                    ->options([
                                        'low' => 'Low',
                                        'medium' => 'Medium',
                                        'high' => 'High',
                                        'critical' => 'Critical',
                                    ])
                                    ->default('medium'),
                                Forms\Components\Textarea::make('custom_message')
                                    ->label('Custom Message')
                                    ->rows(2),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Custom Notification')
                            ->collapsible()
                            ->visible(fn (Forms\Get $get) => $get('enable_notifications')),
                    ])
                    ->collapsible(), // Remove ->persistCollapsed()

                /*
                Forms\Components\Section::make('Required Fields Configuration')
                    ->schema([
                        Forms\Components\Repeater::make('required_fields')
                            ->label('Required Fields')
                            ->schema([
                                Forms\Components\TextInput::make('field_name')
                                    ->label('Field Name')
                                    ->required(),
                                Forms\Components\Select::make('field_type')
                                    ->label('Field Type')
                                    ->options([
                                        'text' => 'Text',
                                        'textarea' => 'Textarea', 
                                        'number' => 'Number',
                                        'date' => 'Date',
                                        'select' => 'Select',
                                        'file' => 'File Upload',
                                        'checkbox' => 'Checkbox',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('field_label')
                                    ->label('Field Label')
                                    ->required(),
                                Forms\Components\Textarea::make('field_options')
                                    ->label('Field Options')
                                    ->helperText('For select fields, enter options separated by commas')
                                    ->rows(2),
                                Forms\Components\Toggle::make('is_required')
                                    ->label('Required')
                                    ->default(true),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add Required Field')
                            ->collapsible()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(), // Remove ->persistCollapsed()

                Forms\Components\Section::make('Optional Fields Configuration')
                    ->schema([
                        Forms\Components\Repeater::make('optional_fields')
                            ->label('Optional Fields')
                            ->schema([
                                Forms\Components\TextInput::make('field_name')
                                    ->label('Field Name')
                                    ->required(),
                                Forms\Components\Select::make('field_type')
                                    ->label('Field Type')
                                    ->options([
                                        'text' => 'Text',
                                        'textarea' => 'Textarea',
                                        'number' => 'Number',
                                        'date' => 'Date',
                                        'select' => 'Select',
                                        'file' => 'File Upload',
                                        'checkbox' => 'Checkbox',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('field_label')
                                    ->label('Field Label')
                                    ->required(),
                                Forms\Components\Textarea::make('field_options')
                                    ->label('Field Options')
                                    ->helperText('For select fields, enter options separated by commas')
                                    ->rows(2),
                                Forms\Components\Toggle::make('is_visible')
                                    ->label('Visible by Default')
                                    ->default(true),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add Optional Field')
                            ->collapsible()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(), // Remove ->persistCollapsed()
                */
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_code')
                    ->label('Kode Perjanjian')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('document_name')
                    ->label('Jenis Perjanjian')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                /*
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(80)
                    ->tooltip(function ($record) {
                        return $record->description;
                    }),
                // New notification columns
                Tables\Columns\IconColumn::make('enable_notifications')
                    ->label('Notifications')
                    ->boolean()
                    ->trueIcon('heroicon-o-bell')
                    ->falseIcon('heroicon-o-bell-slash')
                    ->trueColor('success')
                    ->falseColor('gray'),
                */
                Tables\Columns\TextColumn::make('notification_range')
                    ->label('Notification Days')
                    ->getStateUsing(fn($record) => 
                        $record->enable_notifications 
                            ? "{$record->warning_days}/{$record->urgent_days}/{$record->critical_days}"
                            : 'Disabled'
                    )
                    ->badge()
                    ->color(fn($state) => $state === 'Disabled' ? 'gray' : 'info'),
                /*
                Tables\Columns\TextColumn::make('required_fields_count')
                    ->label('Required Fields')
                    ->getStateUsing(fn($record) => count($record->required_fields ?? []))
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('optional_fields_count')
                    ->label('Optional Fields')
                    ->getStateUsing(fn($record) => count($record->optional_fields ?? []))
                    ->badge()
                    ->color('info'),
                */
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Used Count')
                    ->counts('documentRequests')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : 'No Documents')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
                SelectFilter::make('enable_notifications')
                    ->label('Notifications')
                    ->options([
                        1 => 'Enabled',
                        0 => 'Disabled',
                    ]),
                /*
                Tables\Filters\Filter::make('has_custom_notification_rules')
                    ->label('Has Custom Rules')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereNotNull('notification_settings')
                            ->whereJsonLength('notification_settings->custom_rules', '>', 0)
                    ),
                */
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function ($record) {
                        $newRecord = $record->replicate();
                        $newRecord->document_name = $record->document_name . ' (Copy)';
                        $newRecord->document_code = $record->document_code . '_COPY';
                        $newRecord->is_active = false;
                        $newRecord->save();
                        
                        return redirect()->to(static::getUrl('edit', ['record' => $newRecord]));
                    }),
                Tables\Actions\Action::make('toggle_status')
                    ->icon(fn($record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn($record) => $record->is_active ? 'warning' : 'success')
                    ->label(fn($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['is_active' => !$record->is_active]);
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
                ]),
            ])
            ->defaultSort('document_name', 'asc')
            ->emptyStateHeading('No Document Types')
            ->emptyStateDescription('Create your first document type to get started.')
            ->emptyStateIcon('heroicon-o-document-check')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Document Type'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Document Type Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('document_code')
                            ->label('Kode Dokumen')
                            ->badge()
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('document_name')
                            ->label('Nama Jenis Perjanjian'),
                        Infolists\Components\TextEntry::make('is_active')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                            ->color(fn ($state) => $state ? 'success' : 'danger'),
                        Infolists\Components\TextEntry::make('documentRequests')
                            ->label('Usage Count')
                            ->getStateUsing(fn($record) => $record->documentRequests()->count())
                            ->badge()
                            ->color('info')
                            ->placeholder('No document.'),
                    ])->columns(4),

                // New notification section
                Infolists\Components\Section::make('Notification Settings')
                    ->schema([
                        Infolists\Components\TextEntry::make('enable_notifications')
                            ->label('Notifications Enabled')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Enabled' : 'Disabled')
                            ->color(fn ($state) => $state ? 'success' : 'danger'),
                        Infolists\Components\TextEntry::make('warning_days')
                            ->label('Warning Days')
                            ->suffix(' days before due'),
                        Infolists\Components\TextEntry::make('urgent_days')
                            ->label('Urgent Days')
                            ->suffix(' days before due'),
                        Infolists\Components\TextEntry::make('critical_days')
                            ->label('Critical Days')
                            ->suffix(' days before due'),
                        Infolists\Components\TextEntry::make('notification_recipients.default_recipients')
                            ->label('Default Recipients')
                            ->html()
                            ->formatStateUsing(function ($state) {
                                $labels = [
                                    'requester'   => 'PIC',
                                    'supervisor'  => 'Supervisor/Manager',
                                    'legal_team'  => 'Legal Team',
                                    'finance'     => 'Finance Team',
                                    'head_legal'  => 'Head Legal',
                                ];

                                if (empty($state)) {
                                    return '<span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">None</span>';
                                }

                                $badges = collect($state)->map(function ($key) use ($labels) {
                                    $label = $labels[$key] ?? $key;
                                    return "<span class=\"px-2 py-0.5 mr-1 text-xs rounded-full bg-blue-100 text-blue-800\">{$label}</span>";
                                })->implode('');

                                return $badges;
                            }),
                        Infolists\Components\TextEntry::make('notification_message_template')
                            ->label('Message Template')
                            ->columnSpanFull(),
                    ])->columns(4)
                    ->visible(fn($record) => $record->enable_notifications),

                /*
                Infolists\Components\Section::make('Description')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Required Fields')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('required_fields')
                            ->schema([
                                Infolists\Components\TextEntry::make('field_name')
                                    ->label('Field Name'),
                                Infolists\Components\TextEntry::make('field_type')
                                    ->label('Type')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('field_label')
                                    ->label('Label'),
                                Infolists\Components\TextEntry::make('field_options')
                                    ->label('Options')
                                    ->placeholder('No options'),
                                Infolists\Components\IconEntry::make('is_required')
                                    ->label('Required')
                                    ->boolean(),
                            ])
                            ->columns(5),
                    ])
                    ->visible(fn($record) => !empty($record->required_fields)),

                Infolists\Components\Section::make('Optional Fields')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('optional_fields')
                            ->schema([
                                Infolists\Components\TextEntry::make('field_name')
                                    ->label('Field Name'),
                                Infolists\Components\TextEntry::make('field_type')
                                    ->label('Type')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('field_label')
                                    ->label('Label'),
                                Infolists\Components\TextEntry::make('field_options')
                                    ->label('Options')
                                    ->placeholder('No options'),
                                Infolists\Components\IconEntry::make('is_visible')
                                    ->label('Visible')
                                    ->boolean(),
                            ])
                            ->columns(5),
                    ])
                    ->visible(fn($record) => !empty($record->optional_fields)),
                */

                Infolists\Components\Section::make('Usage History')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('latest_usage')
                            ->label('Latest Usage')
                            ->getStateUsing(fn($record) => 
                                $record->documentRequests()->latest()->first()?->created_at?->diffForHumans() ?? 'Never used'
                            ),
                    ])->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMasterDocuments::route('/'),
            'create' => Pages\CreateMasterDocument::route('/create'),
            'view' => Pages\ViewMasterDocument::route('/{record}'),
            'edit' => Pages\EditMasterDocument::route('/{record}/edit'),
        ];
    }
}