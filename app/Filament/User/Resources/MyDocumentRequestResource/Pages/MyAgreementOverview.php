<?php
// app/Filament/User/Resources/MyAgreementOverview.php
// UPDATED VERSION WITH FIXED FORM AND SERVICE INTEGRATION

namespace App\Filament\User\Resources\MyDocumentRequestResource\Pages;

use Filament\Resources\Pages\Page;
use App\Filament\User\Resources\MyDocumentRequestResource\Pages\MyAgreementOverviews\ListMyAgreementOverview;
use App\Filament\User\Resources\MyDocumentRequestResource\Pages\MyAgreementOverviews\ViewMyAgreementOverview;
use App\Filament\User\Resources\MyDocumentRequestResource\Pages\MyAgreementOverviews\CreateMyAgreementOverview;
use App\Filament\User\Resources\MyDocumentRequestResource\Pages\MyAgreementOverviews\EditMyAgreementOverview;
use App\Models\AgreementOverview;
use App\Models\DocumentRequest;
use App\Traits\DirectorManagementTrait;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Enums\FontWeight;

class MyAgreementOverview extends Page
{
    use DirectorManagementTrait;
    
    // ⚠️ Bagian penting untuk edit/add:
    protected static ?string $model = null; 
    // ➜ jangan fix ke AgreementOverview, karena nanti kita pakai polymorphic atau query gabungan AO + LRF

    // ⚠️ Tambahkan method untuk menampilkan list sesuai user login
    protected static function getTableQueryForMyApprovals($type = 'ao')
    {
        if ($type === 'ao') {
            return AgreementOverview::query()
                ->whereHas('workflowApprovers', fn($q) => $q->where('user_id', auth()->id()));
        } else if ($type === 'lrf') {
            return DocumentRequest::query()
                ->whereHas('workflowApprovers', fn($q) => $q->where('user_id', auth()->id()));
        }
        return null;
    }
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'My Agreement Overviews';
    protected static ?string $modelLabel = 'My Agreement Overview';
    protected static ?string $pluralModelLabel = 'My Agreement Overviews';
    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('nik', auth()->user()->nik);
    }

    protected static function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where('creator_nik', auth()->user()->nik);
    }

    public function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Approval Decision')
                ->schema([
                    Forms\Components\Section::make('Agreement Overview Information')
                        ->schema([
                            // FIXED: Properly handle both field possibilities
                            Forms\Components\Select::make('document_request_id')
                                ->label('Source Document Request')
                                ->options(function () {
                                    return DocumentRequest::where('nik', auth()->user()->nik)
                                        ->where('status', 'agreement_creation')
                                        ->whereDoesntHave('agreementOverview')
                                        ->get()
                                        ->mapWithKeys(function ($doc) {
                                            $label = ($doc->nomor_dokumen ?? 'No Number') . ' - ' . ($doc->title ?? 'No Title');
                                            return [$doc->id => $label];
                                        })
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord)
                                ->helperText('Select the document request this agreement is based on'),
                                
                            Forms\Components\TextInput::make('nomor_dokumen')
                                ->label('Agreement Number')
                                ->maxLength(255)
                                ->disabled()
                                ->helperText('Will be auto-generated if left empty'),
                                
                            Forms\Components\DatePicker::make('tanggal_ao')
                                ->label('Agreement Overview Date')
                                ->default(now())
                                ->required(),
                                
                            Forms\Components\TextInput::make('pic')
                                ->label('PIC (Person In Charge)')
                                ->default(auth()->user()->name)
                                ->required()
                                ->maxLength(255),
                        ])->columns(2),

                    Forms\Components\Section::make('Counterparty Information')
                        ->schema([
                            Forms\Components\TextInput::make('counterparty')
                                ->label('Counterparty Name')
                                ->required()
                                ->maxLength(255),
                                
                            Forms\Components\Textarea::make('deskripsi')
                                ->label('Agreement Description')
                                ->rows(3)
                                ->required()
                                ->columnSpanFull(),
                        ])->columns(2),

                    Forms\Components\Section::make('Director Selection')
                        ->schema([
                            Forms\Components\TextInput::make('director1_name')
                                ->label('Director 1 (Auto-assigned)')
                                ->helperText('Automatically assigned from your supervisor line')
                                ->disabled()
                                ->dehydrated(false),
                                
                            Forms\Components\Select::make('director2_nik')
                                ->label('Select Director 2')
                                ->options(static::getAvailableDirectors())
                                ->searchable()
                                ->required()
                                ->reactive()
                                ->helperText('Choose second director for this agreement')
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $director2 = static::getDirector2Details($state);
                                        $set('director2_name', $director2['name']);
                                        
                                        \Log::info('AO Form Director2 Selection', [
                                            'selected_nik' => $state,
                                            'director_data' => $director2
                                        ]);
                                    }
                                }),
                        ])->columns(2),

                    Forms\Components\Section::make('Agreement Period')
                        ->schema([
                            Forms\Components\DatePicker::make('start_date_jk')
                                ->label('Agreement Start Date')
                                ->required(),
                                
                            Forms\Components\DatePicker::make('end_date_jk')
                                ->label('Agreement End Date')
                                ->required()
                                ->after('start_date_jk'),
                        ])->columns(2),

                    Forms\Components\Section::make('Agreement Content')
                        ->schema([
                            Forms\Components\RichEditor::make('resume')
                                ->label('Executive Summary')
                                ->required()
                                ->columnSpanFull()
                                ->helperText('Provide a comprehensive summary of the agreement')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'bulletList', 'orderedList',
                                    'h2', 'h3', 'paragraph',
                                    'undo', 'redo'
                                ]),
                                
                            Forms\Components\RichEditor::make('ketentuan_dan_mekanisme')
                                ->label('Terms and Mechanisms')
                                ->required()
                                ->columnSpanFull()
                                ->helperText('Detail the key terms and operational mechanisms')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'bulletList', 'orderedList',
                                    'h2', 'h3', 'paragraph',
                                    'undo', 'redo'
                                ]),
                        ]),

                    Forms\Components\Section::make('Parties Information')
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
                                        ->rows(2)
                                        ->label('Address'),
                                        
                                    Forms\Components\TextInput::make('contact_person')
                                        ->label('Contact Person'),
                                        
                                    Forms\Components\TextInput::make('email')
                                        ->email()
                                        ->label('Email'),
                                        
                                    Forms\Components\TextInput::make('phone')
                                        ->label('Phone Number'),
                                ])
                                ->columns(3)
                                ->defaultItems(2)
                                ->columnSpanFull()
                                ->addActionLabel('Add Party')
                                ->collapsible(),
                        ]),

                    Forms\Components\Section::make('Key Terms & Risks')
                        ->schema([
                            Forms\Components\Repeater::make('terms')
                                ->schema([
                                    Forms\Components\TextInput::make('key')
                                        ->label('Term/Condition')
                                        ->required(),
                                        
                                    Forms\Components\Textarea::make('value')
                                        ->label('Description')
                                        ->required()
                                        ->rows(2),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->label('Key Terms & Conditions')
                                ->addActionLabel('Add Term')
                                ->collapsible(),
                            
                            Forms\Components\Repeater::make('risks')
                                ->schema([
                                    Forms\Components\TextInput::make('key')
                                        ->label('Risk Item')
                                        ->required(),
                                        
                                    Forms\Components\Textarea::make('value')
                                        ->label('Mitigation Strategy')
                                        ->required()
                                        ->rows(2),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->label('Identified Risks & Mitigation')
                                ->addActionLabel('Add Risk')
                                ->collapsible(),
                        ]),

                    // Hidden fields - auto-filled
                    Forms\Components\Hidden::make('nik'),
                    Forms\Components\Hidden::make('nama'),
                    Forms\Components\Hidden::make('jabatan'),
                    Forms\Components\Hidden::make('divisi'),
                    Forms\Components\Hidden::make('direktorat'),
                    Forms\Components\Hidden::make('level'),
                    Forms\Components\Hidden::make('director1_nik'),
                    Forms\Components\Hidden::make('director1_name'),
                    Forms\Components\Hidden::make('director2_nik'),
                    Forms\Components\Hidden::make('director2_name'),
                    Forms\Components\Hidden::make('is_draft'),
                    Forms\Components\Hidden::make('status'),
                ])
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('Agreement Number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('documentRequest.nomor_dokumen')
                    ->label('Source Doc Number')
                    ->searchable()
                    ->placeholder('No source document')
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('documentRequest.title')
                    ->label('Source Doc Title')
                    ->limit(30)
                    ->searchable()
                    ->placeholder('No source document')
                    ->tooltip(function ($record) {
                        return $record->documentRequest?->title ?? 'No source document';
                    }),
                    
                Tables\Columns\TextColumn::make('counterparty')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function ($record) {
                        return $record->counterparty;
                    }),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => ['pending_head', 'pending_gm'],
                        'info' => ['pending_finance', 'pending_legal'],
                        'primary' => ['pending_director1', 'pending_director2'],
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn($state) => match($state) {
                        'pending_head' => 'Pending Head',
                        'pending_gm' => 'Pending GM',
                        'pending_finance' => 'Pending Finance',
                        'pending_legal' => 'Pending Legal',
                        'pending_director1' => 'Pending Director 1',
                        'pending_director2' => 'Pending Director 2',
                        default => str_replace('_', ' ', ucwords($state))
                    }),
                    
                Tables\Columns\TextColumn::make('director1_name')
                    ->label('Director 1')
                    ->placeholder('Not assigned')
                    ->limit(25),
                    
                Tables\Columns\TextColumn::make('director2_name')
                    ->label('Director 2')
                    ->placeholder('Not selected')
                    ->limit(25),
                    
                Tables\Columns\TextColumn::make('tanggal_ao')
                    ->label('AO Date')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_draft')
                    ->boolean()
                    ->label('Draft'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AgreementOverview::getStatusOptions()),
                    
                SelectFilter::make('is_draft')
                    ->options([
                        1 => 'Draft',
                        0 => 'Submitted',
                    ]),
                    
                SelectFilter::make('document_request_id')
                    ->label('Source Document')
                    ->relationship('documentRequest', 'title')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->canBeEdited()),
                    
                
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Agreement Overviews')
            ->emptyStateDescription('Create your first agreement overview from approved document requests.')
            ->emptyStateIcon('heroicon-o-document-duplicate');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Agreement Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('nomor_dokumen')
                            ->label('Agreement Number')
                            ->copyable(),
                            
                        Infolists\Components\TextEntry::make('tanggal_ao')
                            ->label('AO Date')
                            ->date(),
                        
                        Infolists\Components\TextEntry::make('pic')
                            ->label('PIC'),
                            
                        Infolists\Components\TextEntry::make('counterparty'),
                        
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn($state) => AgreementOverview::getStatusColors()[$state] ?? 'gray'),
                            
                        Infolists\Components\IconEntry::make('is_draft')
                            ->boolean(),
                    ])->columns(2),

                Infolists\Components\Section::make('Source Document')
                    ->schema([
                        Infolists\Components\TextEntry::make('documentRequest.nomor_dokumen')
                            ->label('Document Number')
                            ->copyable(),
                            
                        Infolists\Components\TextEntry::make('documentRequest.title')
                            ->label('Document Title'),
                            
                        Infolists\Components\TextEntry::make('documentRequest.doctype.document_name')
                            ->label('Document Type')
                            ->badge(),
                        Infolists\Components\TextEntry::make('lama_perjanjian_surat')
                            ->label('Agreement Duration')
                            ->placeholder('Not specified'),
                    ])->columns(2),

                Infolists\Components\Section::make('Directors & Agreement Period')
                    ->schema([
                        Infolists\Components\TextEntry::make('director1_name')
                            ->label('Director 1 (Auto)'),
                            
                        Infolists\Components\TextEntry::make('director2_name')
                            ->label('Director 2 (Selected)'),
                            
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
                    ])->columns(2),

                Infolists\Components\Section::make('Agreement Content')
                    ->schema([
                        Infolists\Components\TextEntry::make('deskripsi')
                            ->label('Description')
                            ->columnSpanFull(),
                            
                        Infolists\Components\TextEntry::make('resume')
                            ->label('Executive Summary')
                            ->html()
                            ->columnSpanFull(),
                            
                        Infolists\Components\TextEntry::make('ketentuan_dan_mekanisme')
                            ->label('Terms and Mechanisms')
                            ->html()
                            ->columnSpanFull(),
                    ]),

               Infolists\Components\Section::make('📎 Document Attachments')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('documentRequest.dokumen_utama')
                                    ->label('📄 Main Document')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '❌ Not uploaded';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->documentRequest?->dokumen_utama ? asset('storage/' . $record->documentRequest->dokumen_utama) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'danger')
                                    ->weight(FontWeight::Medium),

                                Infolists\Components\TextEntry::make('documentRequest.akta_pendirian')
                                    ->label('🏢 Akta Pendirian')
                                    ->formatStateUsing(fn($state) => $state
                                        ? "📁 " . basename($state) . " (" . strtoupper(pathinfo($state, PATHINFO_EXTENSION)) . ")"
                                        : "➖ Not provided"
                                    )
                                    ->url(fn ($record) => $record->documentRequest?->akta_pendirian ? asset('storage/' . $record->documentRequest->akta_pendirian) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray'),
                                
                                Infolists\Components\TextEntry::make('documentRequest.ktp_direktur')
                                    ->label('🆔 Director ID Card')
                                    ->formatStateUsing(fn($state) => $state
                                        ? "📁 " . basename($state) . " (" . strtoupper(pathinfo($state, PATHINFO_EXTENSION)) . ")"
                                        : "➖ Not provided"
                                    )
                                    ->url(fn ($record) => $record->documentRequest?->ktp_direktur ? asset('storage/' . $record->documentRequest?->ktp_direktur) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray'),
                                
                                Infolists\Components\TextEntry::make('documentRequest.akta_perubahan')
                                    ->label('📋 Akta Perubahan')
                                    ->formatStateUsing(fn($state) => $state
                                        ? "📁 " . basename($state) . " (" . strtoupper(pathinfo($state, PATHINFO_EXTENSION)) . ")"
                                        : "➖ Not provided"
                                    )
                                    ->url(fn ($record) => $record->documentRequest?->akta_perubahan ? asset('storage/' . $record->documentRequest?->akta_perubahan) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray'),
                                
                                Infolists\Components\TextEntry::make('documentRequest.surat_kuasa')
                                    ->label('✍️ Surat Kuasa')
                                    ->formatStateUsing(fn($state) => $state
                                        ? "📁 " . basename($state) . " (" . strtoupper(pathinfo($state, PATHINFO_EXTENSION)) . ")"
                                        : "➖ Not provided"
                                    )
                                    ->url(fn ($record) => $record->documentRequest?->surat_kuasa ? asset('storage/' . $record->documentRequest?->surat_kuasa) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray'),
                                
                                Infolists\Components\TextEntry::make('documentRequest.nib')
                                    ->label('🏪 NIB')
                                    ->formatStateUsing(fn($state) => $state
                                        ? "📁 " . basename($state) . " (" . strtoupper(pathinfo($state, PATHINFO_EXTENSION)) . ")"
                                        : "➖ Not provided"
                                    )
                                    ->url(fn ($record) => $record->documentRequest?->nib ? asset('storage/' . $record->documentRequest?->nib) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray'),
                            ]),
                    ]),

                // Show approval status if not draft
               Infolists\Components\Section::make('📊 Approval Status & Progress')
                ->schema([
                    // Progress Bar
                    Infolists\Components\TextEntry::make('approval_progress')
                        ->label('Approval Progress')
                        ->getStateUsing(fn($record) => $record->getApprovalProgress() . '%')
                        ->badge()
                        ->color(fn($record) => match(true) {
                            $record->getApprovalProgress() === 100 => 'success',
                            $record->getApprovalProgress() >= 50 => 'warning',
                            default => 'gray'
                        }),
                        
                    Infolists\Components\TextEntry::make('current_step')
                        ->label('Current Step')
                        ->getStateUsing(fn($record) => $record->getCurrentApprovalStep())
                        ->badge()
                        ->color(fn($record) => match($record->status) {
                            'approved' => 'success',
                            'rejected' => 'danger',
                            'draft' => 'gray',
                            default => 'warning'
                        }),
                        
                    // Workflow Steps Visual
                    Infolists\Components\TextEntry::make('workflow_steps')
                        ->label('Workflow Progress')
                        ->getStateUsing(function($record) {
                            $steps = [
                                'draft' => '📝 Draft',
                                'pending_head' => '👨‍💼 Head Review',
                                'pending_gm' => '🎯 GM Review',
                                'pending_finance' => '💰 Finance Review',
                                'pending_legal' => '⚖️ Legal Review',
                                'pending_director1' => '👔 Director 1 Review',
                                'pending_director2' => '👔 Director 2 Review',
                                'approved' => '✅ Fully Approved'
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
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn($record) => !$record->is_draft),

                Infolists\Components\Section::make('📋 Approval History')
    ->schema([
        Infolists\Components\RepeatableEntry::make('approvals')
            ->schema([
                Infolists\Components\TextEntry::make('approval_type')
                    ->label('👤 Step'),
                Infolists\Components\TextEntry::make('approver_name')
                    ->label('👤 Approver'),
                Infolists\Components\TextEntry::make('status')
                    ->label('📋 Decision')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'revision_requested' => 'warning',
                        default => 'gray'
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'approved' => '✅ Approved',
                        'rejected' => '❌ Rejected',
                        'revision_requested' => '🔄 Revision Requested',
                        default => ucfirst($state)
                    }),
                Infolists\Components\TextEntry::make('approved_at')
                    ->label('📅 Date')
                    ->dateTime()
                    ->placeholder('⏳ Pending'),
                Infolists\Components\TextEntry::make('comments')
                    ->label('💬 Comments')
                    ->placeholder('No comments')
                    ->columnSpanFull(),
            ])
            ->columns(3),
    ])
    ->visible(fn($record) => !$record->is_draft && $record->approvals && $record->approvals->count() > 0),
            ]);
    }

    public static function getPages(): array
    {
        return [
        'index'  => ListMyAgreementOverview::route('/'),
        'view'   => ViewMyAgreementOverview::route('/{record}'),
        'create' => CreateMyAgreementOverview::route('/create'),
        'edit'   => EditMyAgreementOverview::route('/{record}/edit'),
        ];
    }

    public static function getDirector2Details($director2Selection): array
{
    // FIXED: Use correct mapping that matches the form options
    $directors = [
        '14070619' => [
            'nik' => '14070619', 
            'name' => 'Wiradi - FA IT Director',
            'title' => 'Finance & Admin IT Director',
            'direktorat' => 'IT'
        ],
        '710144' => [
            'nik' => '710144', 
            'name' => 'Lyvia Mariana - Direktur Utama',
            'title' => 'Direktur Utama', 
            'direktorat' => 'Executive'
        ],
        '20050037' => [
            'nik' => '20050037', 
            'name' => 'Widi Satya Chitra - Corporate Secretary, Legal & Business Development Director',
            'title' => 'Corporate Secretary, Legal & Business Development Director',
            'direktorat' => 'Legal'
        ],
    ];
    
    \Log::info('Director2 Selection Debug', [
        'input' => $director2Selection,
        'type' => gettype($director2Selection),
        'available_keys' => array_keys($directors),
        'found' => isset($directors[$director2Selection])
    ]);
    
    return $directors[$director2Selection] ?? [
        'nik' => 'DIR_UNKNOWN', 
        'name' => 'Unknown Director - Selection: ' . $director2Selection,
        'title' => 'Unknown Title',
        'direktorat' => 'Unknown'
    ];
    }

    public static function generateAONumber(): string
{
    try {
        $lastAO = \DB::table('agreement_overviews')->latest('id')->first();
        $partNumber = $lastAO ? ($lastAO->id + 1) : 1;
        
        $seqNumber = str_pad($partNumber, 4, '0', STR_PAD_LEFT);
        $month = date('m');
        $year = date('Y');
        
        return "AO/{$seqNumber}/{$month}/{$year}";
    } catch (\Exception $e) {
        // Fallback jika table belum ada
        $partNumber = rand(1000, 9999);
        $month = date('m');
        $year = date('Y');
        
        return "AO/{$partNumber}/{$month}/{$year}";
    }
}
public static function getDirector1FromAPI($direktorat): array
{
    try {
        // Nanti implementasi call ke API direksi.php
        // Sementara return dummy data based on direktorat
        $directorMapping = [
            'IT' => ['nik' => '14070619', 'name' => 'Wiradi'],
           
        ];
        
        return $directorMapping[$direktorat] ?? [
            'nik' => '14070619', 
            'name' => 'Wiradi'
        ];
        
        // Future implementation:
        // $response = Http::get('your-api-endpoint/direksi.php', [
        //     'direktorat' => $direktorat
        // ]);
        // return $response->json();
    } catch (\Exception $e) {
        \Log::error('Error getting Director 1 from API', ['error' => $e->getMessage()]);
        return ['nik' => 'DIR_ERROR', 'name' => 'Director - Error Loading'];
    }
}
}