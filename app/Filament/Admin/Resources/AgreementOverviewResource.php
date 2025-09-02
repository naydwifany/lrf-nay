<?php
// app/Filament/Admin/Resources/AgreementOverviewResource.php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AgreementOverviewResource\Pages;
use App\Models\AgreementOverview;
use App\Models\DocumentApproval;
use App\Services\DocumentWorkflowService;
use App\Enums\DocumentStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;

class AgreementOverviewResource extends Resource
{
    protected static ?string $model = AgreementOverview::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Agreement Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'nomor_dokumen';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Agreement Overview Information')
                    ->schema([
                        Forms\Components\Select::make('document_request_id')
                            ->relationship('documentRequest', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('nomor_dokumen')
                            ->label('Document Number')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('tanggal_ao')
                            ->label('Agreement Overview Date')
                            ->required(),
                        Forms\Components\TextInput::make('pic')
                            ->label('PIC (Person In Charge)')
                            ->required()
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('nik')
                            ->label('NIK')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('nama')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('jabatan')
                            ->label('Position')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('divisi')
                            ->label('Division')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('direktorat')
                            ->label('Directorate')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('level')
                            ->label('Level')
                            ->maxLength(50),
                    ])->columns(3),

                Forms\Components\Section::make('Counterparty & Description')
                    ->schema([
                        Forms\Components\TextInput::make('counterparty')
                            ->label('Counterparty')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('deskripsi')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Director Information')
                    ->schema([
                        Forms\Components\TextInput::make('nama_direksi_default')
                            ->label('Default Director Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('nama_direksi')
                            ->label('Selected Director Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('nik_direksi')
                            ->label('Director NIK')
                            ->maxLength(50),
                    ])->columns(3),

                Forms\Components\Section::make('Agreement Period')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date_jk')
                            ->label('Start Date')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date_jk')
                            ->label('End Date')
                            ->required()
                            ->after('start_date_jk'),
                    ])->columns(2),

                Forms\Components\Section::make('Agreement Details')
                    ->schema([
                        Forms\Components\RichEditor::make('resume')
                            ->label('Resume/Summary')
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('ketentuan_dan_mekanisme')
                            ->label('Terms and Mechanisms')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Parties, Terms & Risks')
                    ->schema([
                        Forms\Components\Repeater::make('parties')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->label('Party Name'),
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'company' => 'Company',
                                        'individual' => 'Individual',
                                        'government' => 'Government Entity',
                                        'ngo' => 'NGO',
                                    ])
                                    ->required(),
                                Forms\Components\Textarea::make('address')
                                    ->rows(2),
                                Forms\Components\TextInput::make('contact_person'),
                            ])
                            ->columns(2)
                            ->defaultItems(2)
                            ->columnSpanFull(),
                        
                        Forms\Components\KeyValue::make('terms')
                            ->label('Key Terms')
                            ->columnSpanFull(),
                        
                        Forms\Components\KeyValue::make('risks')
                            ->label('Identified Risks')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Status & Control')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'submitted' => 'Submitted',
                                'pending_approval' => 'Pending Approval',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('draft')
                            ->required(),
                        Forms\Components\Toggle::make('is_draft')
                            ->label('Is Draft')
                            ->default(true),
                        Forms\Components\DateTimePicker::make('submitted_at')
                            ->label('Submitted At'),
                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Completed At'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('Document Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('documentRequest.title')
                    ->label('Source Document')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('nama')
                    ->label('Requester')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('counterparty')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'submitted',
                        'info' => 'pending_approval',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('tanggal_ao')
                    ->label('AO Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date_jk')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date_jk')
                    ->label('End Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_draft')
                    ->boolean()
                    ->label('Draft'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'pending_approval' => 'Pending Approval',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                SelectFilter::make('is_draft')
                    ->options([
                        1 => 'Draft',
                        0 => 'Final',
                    ]),
            ])

            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AgreementOverview $record) =>
                        app(DocumentWorkflowService::class)
                            ->canUserApproveAgreementOverview(auth()->user(), $record)
                    )
                    ->action(function (AgreementOverview $record, array $data) {
                        $workflowService = app(DocumentWorkflowService::class);

                        $workflowService->approveAgreementOverview(
                            $record,
                            auth()->user(),
                            $data['approval_comments'] ?? 'Approved'
                        );

                        Notification::make()
                            ->title('Agreement Overview Approved')
                            ->body('The agreement overview has been successfully approved.')
                            ->success()
                            ->send();
                    })
                    ->form([
                        Forms\Components\Textarea::make('approval_comments')
                            ->label('Approval Comments')
                            ->rows(3)
                            ->helperText('Optional: Add your comments for this approval'),
                    ]),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Agreement Overview')
                    ->modalDescription('Are you sure you want to reject this agreement overview?')
                    ->modalSubmitActionLabel('Reject')
                    ->visible(fn (AgreementOverview $record) =>
                        auth()->user()->role === 'director' &&
                        app(DocumentWorkflowService::class)
                            ->canUserApproveAgreementOverview(auth()->user(), $record)
                    )
                    ->action(function (AgreementOverview $record, array $data) {
                        $workflowService = app(DocumentWorkflowService::class);

                        $workflowService->rejectAgreementOverview(
                            $record,
                            auth()->user(),
                            $data['rejection_reason']
                        );

                        Notification::make()
                            ->title('Agreement Overview Rejected')
                            ->body('The agreement overview has been rejected and returned to the requester.')
                            ->danger()
                            ->send();
                    })
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3)
                            ->helperText('Please provide a clear reason for rejection'),
                    ]),

                Tables\Actions\Action::make('send_to_rediscuss')
                    ->label('Send to Rediscuss')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Send Agreement Overview to Rediscussion')
                    ->modalDescription('Are you sure you want to send this agreement overview back to discussion?')
                    ->modalSubmitActionLabel('Send Back')
                    ->visible(fn (AgreementOverview $record) =>
                        auth()->user()->role === 'director' &&
                        in_array($record->status, [
                            AgreementOverview::STATUS_PENDING_DIRECTOR1,
                            AgreementOverview::STATUS_PENDING_DIRECTOR2,
                        ])
                    )
                    ->action(function (AgreementOverview $record, array $data) {
                        $workflowService = app(DocumentWorkflowService::class);

                        $workflowService->sendAgreementOverviewToRediscussion(
                   $record,
                            auth()->user(),
                            $data['rediscussion_comments'] ?? 'Sent back to discussion'
                        );

                        Notification::make()
                            ->title('Agreement Overview Sent Back')
                            ->body('The agreement overview has been sent back to forum discussion.')
                            ->warning()
                            ->send();
                    })
                    ->form([
                        Forms\Components\Textarea::make('rediscussion_comments')
                            ->label('Rediscussion Comments')
                            ->rows(3)
                            ->helperText('Optional: Add your comments for this rediscussion'),
                    ]),
                    
                Tables\Actions\Action::make('download_pdf')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function ($record) {
                        // Generate PDF logic here
                        return response()->download(storage_path('app/agreements/' . $record->id . '.pdf'));
                    }),
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
                Infolists\Components\Section::make('Agreement Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('nomor_dokumen')
                            ->label('Document Number'),
                        Infolists\Components\TextEntry::make('tanggal_ao')
                            ->label('AO Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('pic')
                            ->label('PIC'),
                        Infolists\Components\TextEntry::make('counterparty'),
                        Infolists\Components\IconEntry::make('is_draft')
                            ->boolean(),
                    ])->columns(3),

                Infolists\Components\Section::make('Requester Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('nik'),
                        Infolists\Components\TextEntry::make('nama'),
                        Infolists\Components\TextEntry::make('jabatan'),
                        Infolists\Components\TextEntry::make('divisi'),
                        Infolists\Components\TextEntry::make('direktorat'),
                        Infolists\Components\TextEntry::make('level'),
                    ])->columns(3),

                Infolists\Components\Section::make('Agreement Period')
                    ->schema([
                        Infolists\Components\TextEntry::make('start_date_jk')
                            ->label('Start Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('end_date_jk')
                            ->label('End Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('duration')
                            ->label('Duration')
                            ->getStateUsing(fn($record) => 
                                $record->start_date_jk && $record->end_date_jk 
                                    ? $record->start_date_jk->diffInDays($record->end_date_jk) . ' days'
                                    : 'N/A'
                            ),
                    ])->columns(3),

                Infolists\Components\Section::make('Agreement Content')
                    ->schema([
                        Infolists\Components\TextEntry::make('deskripsi')
                            ->html()
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('resume')
                            ->html()
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('ketentuan_dan_mekanisme')
                            ->html()
                            ->columnSpanFull(),
                    ]),

            // Workflow Steps Visual
            Infolists\Components\Section::make('Workflow Progress')
                ->schema([
                    Infolists\Components\TextEntry::make('workflow_steps')
                        ->getStateUsing(function ($record) {
                            $steps = [
                                'draft' => '📝 Draft',
                                'pending_head' => '👨‍💼 Head Review',
                                'pending_gm' => '🎯 GM Review',
                                'pending_finance' => '💰 Finance Review',
                                'pending_legal' => '⚖️ Legal Review',
                                'pending_director1' => '👔 Director 1 Review',
                                'pending_director2' => '👔 Director 2 Review',
                                'approved' => '✅ Fully Approved',
                            ];

                            $currentStatus = $record->status;
                            $stepKeys = array_keys($steps);
                            $currentIndex = array_search($currentStatus, $stepKeys);
                            $html = '<div class="space-y-2">';

                            foreach ($steps as $stepStatus => $stepLabel) {
                                $stepIndex = array_search($stepStatus, $stepKeys);
                                $isCompleted = $stepIndex < $currentIndex || $currentStatus === 'approved';
                                $isCurrent = $stepStatus === $currentStatus;

                                if ($isCompleted) {
                                    $html .= "<div class='text-green-600 font-medium'>✅ {$stepLabel}</div>";
                                } elseif ($isCurrent) {
                                    $html .= "<div class='text-blue-600 font-bold'>🔄 {$stepLabel} (Current)</div>";
                                } else {
                                    $html .= "<div class='text-gray-400'>⏳ {$stepLabel}</div>";
                                }
                            }

                            $html .= '</div>';
                            return $html;
                        })
                        ->html()
                        ->columnSpanFull()
                        ->visible(fn($record) => !$record->is_draft),
                ])
                ->columns(2),

                Infolists\Components\Section::make('Approval History')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('approvals')
                            ->schema([
                                Infolists\Components\TextEntry::make('approver_name'),
                                Infolists\Components\TextEntry::make('approval_type'),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('approved_at')
                                    ->dateTime(),
                                Infolists\Components\TextEntry::make('comments'),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgreementOverviews::route('/'),
            'view' => Pages\ViewAgreementOverview::route('/{record}'),
        ];
    }
}