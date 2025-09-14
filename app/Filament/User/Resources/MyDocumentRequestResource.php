<?php
// app/Filament/User/Resources/MyDocumentRequestResource.php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\MyDocumentRequestResource\Pages;
use App\Models\DocumentRequest;
use App\Services\DocumentWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use App\Traits\DirectorManagementTrait;


class MyDocumentRequestResource extends Resource
{
    use DirectorManagementTrait;

    protected static ?string $model = DocumentRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'My Document Requests';
    protected static ?string $modelLabel = 'My Document Request';
    protected static ?string $pluralModelLabel = 'My Document Requests';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('nik', auth()->user()->nik);
    }

    public static function form(Form $form): Form
    {
        $id = DocumentRequest::latest()->pluck('id')->first();
        if(is_null($id)){
            $part_number = 1;    
        }
        else{
            $part_number = ($id + 1);
        }
        
        $divisi = auth()->user()->divisi ?? null;
        $threeDigits = substr($divisi, 0, 3);

        $seqNumber = str_pad($part_number, 4, '0', STR_PAD_LEFT);
        $initial = preg_replace('/[^A-Z]/', '', $divisi);
        $month = self::getRomawi(date('n'));
        $nomor_dokumen = $seqNumber."/LRF/".$initial."/".$month."/".date("Y");

        return $form
            ->schema([
                Forms\Components\Section::make('Document Information')
                    ->schema([
                        Forms\Components\TextInput::make('nomor_dokumen')
                            ->default($nomor_dokumen)
                            ->label('Nomor Dokumen')
                            ->extraInputAttributes([
                                'readonly' => true,
                                'style' => 'font-size:0.7em; font-weight:bold;'
                            ]),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->label('Judul Dokumen (Nama Mitra)'),
                        Forms\Components\Select::make('tipe_dokumen')
                            ->relationship('doctype', 'document_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Tipe Dokumen'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->label('Deskripsi Perjanjian'),
                        
                        /*
                        Forms\Components\Select::make('priority')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'urgent' => 'Urgent',
                            ])
                            ->default('medium')
                            ->required()
                            ->native(false)
                            ->label('Priority Level'),
                        */
                                                    
                            Forms\Components\Radio::make('doc_filter')
                                ->label('Document')
                                ->options([
                                    'review' => 'Review',
                                    'create' => 'Create'
                                ])
                                ->default('review')
                                ->reactive() // Penting untuk membuat field lain bereaksi
                                ->extraInputAttributes(['style' => 'font-size:0.7em; font-weight:bold;']),
                                
                            
                            FileUpload::make('dokumen_utama')
                                ->label('Main Document')
                                ->directory('documents')
                                ->maxSize(5120) // 5MB
                                ->downloadable()
                                ->previewable(true)
                                ->openable()
                                ->required(fn (callable $get) => $get('doc_filter') === 'review')
                                ->helperText(fn (callable $get) => 
                                    $get('doc_filter') === 'review' 
                                        ? '⚠️ Required: Main document harus disertakan saat memilih Document Review' 
                                        : '📎 Optional: Main document tidak wajib disertakan saat memilih Document Create'
                                )
                                ->storeFile()
                                ->getUploadedFileNameForStorageUsing(function ($file, $record, $get) {
                                    $ext = $file->getClientOriginalExtension();
                                    $nomor = $get('nomor_dokumen') ?? 'TEMP';
                                    $safeNomor = str_replace('/', '-', $nomor);

                                    return $safeNomor . '.dokumen-utama.' . $ext;
                                })
                    ])->columns(2),

                Forms\Components\Section::make('Business Requirements')
                    ->schema([
                        Grid::make()->schema([
                            /*
                            Forms\Components\RichEditor::make('data')
                                ->label('Business Justification')
                                ->columnSpanFull()
                                ->helperText('Please explain why this document is needed for business purposes'),
                            */
                            Forms\Components\TextInput::make('lama_perjanjian_surat')
                                ->label('Jangka Waktu Perjanjian')
                                ->helperText('e.g., 12 Bulan, 2 Tahun'),
                        ])
                    ]),

                Forms\Components\Section::make('Hak & Kewajiban')
                    ->schema([
                        Grid::make()->schema([
                            Forms\Components\RichEditor::make('kewajiban_mitra')
                                ->label('Kewajiban Mitra')
                                ->required()
                                ->columnSpanFull(),
                            Forms\Components\RichEditor::make('hak_mitra')
                                ->label('Hak Mitra')
                                ->required()
                                ->columnSpanFull(),
                            Forms\Components\RichEditor::make('kewajiban_eci')
                                ->label('Kewajiban ECI')
                                ->required()
                                ->columnSpanFull(),
                            Forms\Components\RichEditor::make('hak_eci')
                                ->label('Hak ECI')
                                ->required()
                                ->columnSpanFull(),
                        ])
                    ]),

                Forms\Components\Section::make('Regulasi Finansial')
                    ->schema([
                        Grid::make()->schema([
                            Forms\Components\RichEditor::make('syarat_ketentuan_pembayaran')
                                ->label('Syarat & Ketentuan Pembayaran (rincian lengkap)')
                                ->required()
                                ->columnSpanFull(),
                            Forms\Components\RichEditor::make('pajak')
                                ->label('Ketentuan Pajak')
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ]),

                Forms\Components\Section::make('Ketentuan Tambahan')
                    ->schema([
                        Grid::make()->schema([
                            Forms\Components\RichEditor::make('ketentuan_lain')
                                ->label('Ketentuan lain yang perlu dimasukkan ke dalam perjanjian')
                                ->columnSpanFull(),
                        ])
                    ]),

                Forms\Components\Section::make('Lampiran Dokumen')
                    ->schema([
                        Grid::make()->columns(2)->schema([
                            FileUpload::make('akta_pendirian')
                                ->label('Akta Pendirian & SK')
                                ->directory('documents')
                                ->maxSize(5120)
                                ->downloadable()
                                ->required()
                                ->previewable(true)
                                ->openable()
                                ->getUploadedFileNameForStorageUsing(function ($file, $record, $get) {
                                    $ext = $file->getClientOriginalExtension();

                                    // ambil nomor_dokumen, jangan dari field upload ini
                                    $nomor = $get('nomor_dokumen') ?? 'TEMP';
                                    $safeNomor = str_replace('/', '-', $nomor);

                                    return $safeNomor . '.akta-pendirian.' . $ext;
                                }),
                            FileUpload::make('akta_perubahan')
                                ->label('Akta PT & SK Anggaran Dasar perubahan terakhir')
                                ->directory('documents')
                                ->maxSize(5120)
                                ->downloadable()
                                ->previewable(true)
                                ->openable()
                                ->getUploadedFileNameForStorageUsing(function ($file, $record, $get) {
                                    $ext = $file->getClientOriginalExtension();

                                    // ambil nomor_dokumen, jangan dari field upload ini
                                    $nomor = $get('nomor_dokumen') ?? 'TEMP';
                                    $safeNomor = str_replace('/', '-', $nomor);

                                    return $safeNomor . '.akta-perubahan.' . $ext;
                                }),
                            FileUpload::make('npwp')
                                ->label('NPWP (Nomor Pokok Wajib Pajak)')
                                ->directory('documents')
                                ->maxSize(5120)
                                ->required()
                                ->downloadable()
                                ->previewable(true)
                                ->openable()
                                ->getUploadedFileNameForStorageUsing(function ($file, $record, $get) {
                                    $ext = $file->getClientOriginalExtension();

                                    // ambil nomor_dokumen, jangan dari field upload ini
                                    $nomor = $get('nomor_dokumen') ?? 'TEMP';
                                    $safeNomor = str_replace('/', '-', $nomor);

                                    return $safeNomor . '.npwp.' . $ext;
                                }),
                            FileUpload::make('ktp_direktur')
                                ->label('KTP kuasa Direksi (bila penandatangan bukan Direksi)')
                                ->directory('documents')
                                ->maxSize(5120)
                                ->downloadable()
                                ->previewable(true)
                                ->openable()
                                ->getUploadedFileNameForStorageUsing(function ($file, $record, $get) {
                                    $ext = $file->getClientOriginalExtension();

                                    // ambil nomor_dokumen, jangan dari field upload ini
                                    $nomor = $get('nomor_dokumen') ?? 'TEMP';
                                    $safeNomor = str_replace('/', '-', $nomor);

                                    return $safeNomor . '.ktp-direktur.' . $ext;
                                }),
                            FileUpload::make('nib')
                                ->label('NIB (Nomor Induk Berusaha)')
                                ->directory('documents')
                                ->maxSize(5120)
                                ->required()
                                ->downloadable()
                                ->previewable(true)
                                ->openable()
                                ->getUploadedFileNameForStorageUsing(function ($file, $record, $get) {
                                    $ext = $file->getClientOriginalExtension();

                                    // ambil nomor_dokumen, jangan dari field upload ini
                                    $nomor = $get('nomor_dokumen') ?? 'TEMP';
                                    $safeNomor = str_replace('/', '-', $nomor);

                                    return $safeNomor . '.nib.' . $ext;
                                }),
                            FileUpload::make('surat_kuasa')
                                ->label('Surat kuasa Direksi (bila penandatangan bukan Direksi)')
                                ->directory('documents')
                                ->maxSize(5120)
                                ->downloadable()
                                ->previewable(true)
                                ->openable()
                                ->getUploadedFileNameForStorageUsing(function ($file, $record, $get) {
                                    $ext = $file->getClientOriginalExtension();

                                    // ambil nomor_dokumen, jangan dari field upload ini
                                    $nomor = $get('nomor_dokumen') ?? 'TEMP';
                                    $safeNomor = str_replace('/', '-', $nomor);

                                    return $safeNomor . '.surat-kuasa.' . $ext;
                                }),
                        ])
                        ]),

                // Hidden fields - auto-filled from auth user
                Forms\Components\Hidden::make('nik')
                    ->default(auth()->user()->nik ?? ''),
                Forms\Components\Hidden::make('nama')
                    ->default(auth()->user()->name ?? ''),
                Forms\Components\Hidden::make('jabatan')
                    ->default(auth()->user()->jabatan ?? ''),
                Forms\Components\Hidden::make('divisi')
                    ->default(auth()->user()->divisi ?? ''),
                Forms\Components\Hidden::make('dept')
                    ->default(auth()->user()->department ?? ''),
                Forms\Components\Hidden::make('direktorat')
                    ->default(auth()->user()->direktorat ?? ''),
                Forms\Components\Hidden::make('nik_atasan')
                    ->default(auth()->user()->supervisor_nik ?? ''),
                Forms\Components\Hidden::make('is_draft')
                    ->default(true),
                Forms\Components\Hidden::make('status')
                    ->default('draft'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('Document Number')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Not assigned'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(function ($record) {
                        return $record->title;
                    }),
                Tables\Columns\TextColumn::make('doctype.document_name')
                    ->label('Document Type')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\BadgeColumn::make('status')
    ->colors([
        'gray' => 'draft',
        'info' => 'submitted',
        'warning' => 'pending_supervisor',
        'primary' => 'pending_gm',
        'secondary' => 'pending_legal',
        'purple' => 'in_discussion',
        'indigo' => 'agreement_creation',
        'success' => 'agreement_approval',
        'danger' => 'rejected',
    ])
    ->formatStateUsing(fn($state) => match($state) {
        'in_discussion' => 'In Discussion',
        'agreement_creation' => 'Ready for AO',
        'agreement_creation' => 'AO Created',
        'pending_supervisor' => 'Pending Supervisor',
        'pending_gm' => 'Pending GM', 
        'pending_legal' => 'Pending Legal',
        default => str_replace('_', ' ', ucwords($state))
    }),

                /*
                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'success' => 'low',
                        'primary' => 'medium',
                        'warning' => 'high',
                        'danger' => 'urgent',
                    ]),
                */

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->placeholder('Not submitted')
                    ->sortable(),
                /*
                Tables\Columns\IconColumn::make('is_draft')
                    ->boolean()
                    ->label('Draft')
                    ->trueIcon('heroicon-o-pencil-square')
                    ->falseIcon('heroicon-o-paper-airplane'),
                */
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'pending_supervisor' => 'Pending Supervisor',
                        'pending_gm' => 'Pending GM',
                        'pending_legal' => 'Pending Legal',
                        'discussion' => 'In Discussion',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),
                
                /*
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ]),
                */

                SelectFilter::make('tipe_dokumen')
                    ->relationship('doctype', 'document_name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->is_draft),
                Tables\Actions\Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Document Request')
                    ->modalDescription('Are you sure you want to submit this document request? Once submitted, you cannot edit it.')
                    ->visible(fn($record) => $record->is_draft)
                    ->action(function ($record) {
                        try {
                            // Validate required fields
                            if (empty($record->title) || empty($record->description) || empty($record->dokumen_utama)) {
                                Notification::make()
                                    ->title('Validation Error')
                                    ->body('Please complete all required fields before submitting.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Update document status
                            $record->update([
                                'is_draft' => false,
                                'status' => 'submitted',
                                'submitted_at' => now(),
                            ]);
                            
                            // Trigger workflow - with error handling
                            try {
                                app(\App\Services\DocumentWorkflowService::class)->submitDocument($record, auth()->user());
                            } catch (\Exception $workflowError) {
                                // Log workflow error but don't fail the submission
                                \Log::error('Workflow error after submission', [
                                    'document_id' => $record->id,
                                    'error' => $workflowError->getMessage()
                                ]);
                                
                                // Set basic status if workflow fails
                                $record->update(['status' => 'pending_supervisor']);
                            }

                            Notification::make()
                                ->title('Document submitted successfully')
                                ->body('Your document has been submitted for approval.')
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            \Log::error('Error submitting document', [
                                'document_id' => $record->id,
                                'error' => $e->getMessage(),
                                'user_nik' => auth()->user()->nik
                            ]);
                            
                            Notification::make()
                                ->title('Error submitting document')
                                ->body('There was an error submitting your document. Please try again.')
                                ->danger()
                                ->send();
                        }
                    }),
                    

// Tambahkan action ini di MyDocumentRequestResource table actions:

Tables\Actions\Action::make('create_agreement_overview')
    ->label('Create AO')
    ->icon('heroicon-o-clipboard-document-list')
    ->color('success')
    ->size('sm')
    ->form([
        Forms\Components\Section::make('Agreement Overview Details')
            ->schema([
                Forms\Components\TextInput::make('counterparty_name')
                    ->label('Counterparty Name')
                    ->required()
                    ->placeholder('Enter counterparty name'),
                    
                Forms\Components\Textarea::make('description')
                    ->label('Agreement Description')
                    ->rows(3)
                    ->placeholder('Describe the agreement purpose and scope'),
                    
                Forms\Components\Select::make('director2_selection')
                    ->label('Select Director 2')
                    ->options([
                        '14070619' => 'Wiradi - FA IT Director',
                        '710144' => 'Lyvia Mariana - Direktur Utama', 
                        '20050037' => 'Widi Satya Chitra - Corporate Secretary, Legal & Business Development Director',
                    ])
                    ->searchable()
                    ->required()
                    ->reactive()
                       ->afterStateUpdated(function ($state, callable $set) {
                        // Use the consistent method name
                        $director2 = static::getDirector2Details($state);
                        $set('director2_name', $director2['name']);
                        
                        // // Debug log
                        // \Log::info('Director2 Selection in AO Form', [
                        //     'selected_nik' => $state,
                        //     'director_data' => $director2
                        // ]);
                    }),
                    
                Forms\Components\Select::make('initial_status')
                    ->label('Initial Action')
                    ->options([
                        'draft' => 'Save as Draft - Complete details later',
                        'pending_head' => 'Submit for Head Approval - Start workflow'
                    ])
                    ->default('draft')
                    ->required()
            ])
    ])
    ->action(function (DocumentRequest $record, array $data) {
        try {
            // Check if AO already exists
            $existingAO = \DB::table('agreement_overviews')
                ->where('document_request_id', $record->id)
                ->first();
                
            if ($existingAO) {
                Notification::make()
                    ->title('AO Already Exists')
                    ->body('Agreement Overview for this document already exists.')
                    ->warning()
                    ->send();
                return;
            }
            
            // Generate AO number
            $aoNumber = static::generateAONumber();
            
            // Get Director 1 from same direktorat (akan dari API nanti)
            $director1Info = static::getDirector1FromDirektorat($record->direktorat ?? auth()->user()->direktorat);
            
            // Get Director 2 from trait  
            $director2Info = static::getDirector2Details($data['director2_selection']);
            
            \Log::info('AO Creation Director Info', [
                'director1' => $director1Info,
                'director2' => $director2Info,
                'selection_input' => $data['director2_selection']
            ]);
            
            // Create AO record
            $aoData = [
                'document_request_id' => $record->id,
                'nomor_dokumen' => $aoNumber,
                'direktorat' => auth()->user()->divisi ?? 'DIREKTORAT',
                'divisi' => auth()->user()->divisi ?? 'DIVISI',
                'pic' => auth()->user()->divisi ?? 'DIVISI',
                'deskripsi' => $data['description'] ?? (($record->doctype->document_name ?? 'General Agreement') . ' - ' . $record->title),
                'counterparty' => $data['counterparty_name'],
                'status' => $data['initial_status'],
                'nik' => auth()->user()->nik ?? 'NIK',
                'nama' => auth()->user()->name ?? 'USER',
                'jabatan' => auth()->user()->jabatan ?? 'JABATANG',
                'director1_nik' => $director1Info['nik'] ?? null,
                'director1_name' => $director1Info['name'] ?? null,
                'director2_nik' => $director2Info['nik'] ?? null,
                'director2_name' => $director2Info['name'] ?? null,
                'start_date_jk' => now(),'end_date_jk' => now(),
                'tanggal_ao' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            \DB::table('agreement_overviews')->insert($aoData);
            
            // Update document status
            $record->update(['status' => 'agreement_creation']);
            
            $statusMessage = $data['initial_status'] === 'pending_head' 
                ? 'AO created and submitted for Head approval'
                : 'AO created as draft. Complete details and submit for approval';
            
            Notification::make()
                ->title('Agreement Overview Created!')
                ->body("AO Number: {$aoNumber} - {$statusMessage}")
                ->success()
                ->duration(5000)
                ->send();
                
        } catch (\Exception $e) {
            \Log::error('Create AO Error', [
                'document_id' => $record->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Notification::make()
                ->title('Error Creating AO')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->duration(5000)
                ->send();
        }
    })
    ->visible(function (DocumentRequest $record) {
        return $record->status === 'agreement_creation';
            }),
                    Tables\Actions\Action::make('view_agreement_overview')
                    ->label('View AO')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->size('sm')
                    ->modalContent(function (DocumentRequest $record) {
                        $ao = \DB::table('agreement_overviews')
                            ->where('document_request_id', $record->id)
                            ->first();
                            
                        if (!$ao) {
                            return new \Illuminate\Support\HtmlString('<p>No Agreement Overview found.</p>');
                        }
                        
                        $statusColors = [
                            'draft' => 'bg-gray-100 text-gray-800',
                            'pending_head' => 'bg-yellow-100 text-yellow-800',
                            'pending_gm' => 'bg-blue-100 text-blue-800',
                            'pending_finance' => 'bg-purple-100 text-purple-800',
                            'pending_legal' => 'bg-indigo-100 text-indigo-800',
                            'pending_director1' => 'bg-orange-100 text-orange-800',
                            'pending_director2' => 'bg-pink-100 text-pink-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800',
                            'rediscuss' => 'bg-gray-100 text-gray-800',
                        ];
                        
                        $statusLabels = [
                            'draft' => 'Draft',
                            'pending_head' => 'Pending Head Approval',
                            'pending_gm' => 'Pending GM Approval',
                            'pending_finance' => 'Pending Finance Approval',
                            'pending_legal' => 'Pending Legal Approval',
                            'pending_director1' => 'Pending Director 1 Approval',
                            'pending_director2' => 'Pending Director 2 Approval',
                            'approved' => 'Fully Approved',
                            'rejected' => 'Rejected',
                            'rediscuss' => 'Back to Discussion',
                        ];
                        
                        $statusColor = $statusColors[$ao->status] ?? 'bg-gray-100 text-gray-800';
                        $statusText = $statusLabels[$ao->status] ?? ucfirst(str_replace('_', ' ', $ao->status));
                        
                        // Simple progress tracking for user
                        $progressSteps = [
                            'draft' => '📝 Draft Created',
                            'pending_head' => '👨‍💼 Head Review',
                            'pending_gm' => '🎯 GM Review', 
                            'pending_finance' => '💰 Finance Review',
                            'pending_legal' => '⚖️ Legal Review',
                            'pending_director1' => '👔 Director 1 Review',
                            'pending_director2' => '👔 Director 2 Review',
                            'approved' => '✅ Fully Approved'
                        ];
                        
                        $currentStepIndex = array_search($ao->status, array_keys($progressSteps));
                        
                        $progressHtml = '<div class="mb-4"><h4 class="font-medium mb-2">Approval Progress</h4><div class="space-y-1">';
                        foreach ($progressSteps as $stepStatus => $stepLabel) {
                            $stepIndex = array_search($stepStatus, array_keys($progressSteps));
                            $isCompleted = $stepIndex < $currentStepIndex || $ao->status === 'approved';
                            $isCurrent = $stepStatus === $ao->status;
                            
                            $stepClass = $isCompleted ? 'text-green-600' : ($isCurrent ? 'text-blue-600 font-medium' : 'text-gray-400');
                            $icon = $isCompleted ? '✅' : ($isCurrent ? '🔄' : '⏳');
                            
                            $progressHtml .= "<div class='{$stepClass} text-sm'>{$icon} {$stepLabel}</div>";
                        }
                        $progressHtml .= '</div></div>';
                        
                        return new \Illuminate\Support\HtmlString("
                            <div class='space-y-4'>
                                {$progressHtml}
                                
                                <div class='grid grid-cols-1 gap-3'>
                                    <div>
                                        <label class='block text-sm font-medium text-gray-700'>AO Number</label>
                                        <p class='text-lg font-semibold text-blue-600'>{$ao->nomor_dokumen}</p>
                                    </div>
                                    <div>
                                        <label class='block text-sm font-medium text-gray-700'>Current Status</label>
                                        <span class='inline-flex px-3 py-1 text-sm font-medium rounded-full {$statusColor}'>
                                            {$statusText}
                                        </span>
                                    </div>
                                    <div>
                                        <label class='block text-sm font-medium text-gray-700'>Description</label>
                                        <p class='text-gray-900'>{$ao->deskripsi}</p>
                                    </div>
                                    <div>
                                        <label class='block text-sm font-medium text-gray-700'>Counterparty</label>
                                        <p class='text-gray-900'>{$ao->counterparty}</p>
                                    </div>
                                </div>
                                
                                <div class='bg-blue-50 p-3 rounded-md'>
                                    <h5 class='font-medium text-blue-900 mb-2'>Assigned Directors</h5>
                                    <div class='text-sm text-blue-800'>
                                        <div><strong>Director 1 (Auto):</strong> {$ao->director1_name}</div>
                                        <div><strong>Director 2 (Selected):</strong> {$ao->director2_name}</div>
                                    </div>
                                </div>
                                
                                <div class='text-xs text-gray-500 pt-3 border-t'>
                                    <div><strong>Created:</strong> {$ao->created_at}</div>
                                    <div><strong>Last Updated:</strong> {$ao->updated_at}</div>
                                </div>
                            </div>
                        ");
                    })
                    ->modalHeading('My Agreement Overview')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(function (DocumentRequest $record) {
                        return \DB::table('agreement_overviews')
                            ->where('document_request_id', $record->id)
                            ->exists();
                    }),
                Tables\Actions\Action::make('withdraw')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Withdraw Document Request')
                    ->modalDescription('This will return the document to draft status.')
                    ->visible(fn($record) => in_array($record->status, ['submitted', 'pending_supervisor']) && !$record->is_draft)
                    ->action(function ($record) {
                        $record->update([
                            'is_draft' => true,
                            'status' => 'draft',
                            'submitted_at' => null,
                        ]);

                        Notification::make()
                            ->title('Document withdrawn successfully')
                            ->success()
                            ->send();
                    }), 
            ])
            ->actionsAlignment('start')
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Document Requests')
            ->emptyStateDescription('Create your first document request to get started.')
            ->emptyStateIcon('heroicon-o-document-text');
    }


// Helper method untuk generate AO number (tambahkan di class DocumentRequestResource)
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
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                /*
                // DEBUG SECTION - Tampilkan semua data untuk cek
                Infolists\Components\Section::make('🔍 Debug Info')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Document ID'),
                        Infolists\Components\TextEntry::make('dokumen_utama')
                            ->label('Main Document Path'),
                        Infolists\Components\TextEntry::make('akta_pendirian')
                            ->label('Akta Pendirian Path'),
                        Infolists\Components\TextEntry::make('syarat_ketentuan_pembayaran')
                            ->label('Payment Terms Raw'),
                        Infolists\Components\TextEntry::make('lama_perjanjian_surat')
                            ->label('Contract Duration Raw'),
                    ])
                    ->collapsible()
                    ->collapsed(),
                */

                Infolists\Components\Section::make('Document Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('nomor_dokumen')
                            ->label('Nomor Dokumen')
                            ->placeholder('Not assigned'),
                        Infolists\Components\TextEntry::make('title')
                            ->label('Nama Mitra'),
                        Infolists\Components\TextEntry::make('doctype.document_name')
                            ->label('Tipe Dokumen')
                            ->badge(),
                        Infolists\Components\TextEntry::make('status')
                            ->badge(),
                        /*
                        Infolists\Components\TextEntry::make('priority')
                            ->badge(),
                            
                        Infolists\Components\IconEntry::make('is_draft')
                            ->boolean()
                            ->label('Draft Status'),
                        */
                    ])->columns(2),

                Infolists\Components\Section::make('Requester Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('nama')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('nik')
                            ->label('NIK'),
                        Infolists\Components\TextEntry::make('jabatan')
                            ->label('Position'),
                        Infolists\Components\TextEntry::make('divisi')
                            ->label('Division'),
                        Infolists\Components\TextEntry::make('dept')
                            ->label('Department'),
                        Infolists\Components\TextEntry::make('direktorat')
                            ->label('Directorate'),
                    ])->columns(3),

                Infolists\Components\Section::make('Informasi Dokumen')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('lama_perjanjian_surat')
                                    ->label('⏰ Jangka Waktu Perjanjian')
                                    ->placeholder('Not specified'),
                                Infolists\Components\TextEntry::make('doc_filter')
                                    ->label('📑 Tipe Dokumen')
                                    ->formatStateUsing(fn($state) => match($state) {
                                        'review' => '🔍 Review',
                                        'create' => '✨ Create New',
                                        default => $state ?: 'Not specified'
                                    })
                                    ->badge(),
                            ]),
                        Infolists\Components\TextEntry::make('description')
                            ->label('📝 Deskripsi Dokumen')
                            ->html()
                            ->columnSpanFull()
                            ->placeholder('Tidak ada deskripsi pada Document Request ini.'),
                        /*
                        Infolists\Components\TextEntry::make('data')
                            ->label('Business Justification')
                            ->html()
                            ->columnSpanFull(),
                        */
                    ]),

                // HAK & KEWAJIBAN - SELALU TAMPIL
                Infolists\Components\Section::make('⚖️ Hak & Kewajiban')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('kewajiban_mitra')
                                    ->label('📝 Kewajiban Mitra')
                                    ->html()
                                    ->placeholder('Not specified'),
                                Infolists\Components\TextEntry::make('hak_mitra')
                                    ->label('✅ Hak Mitra')
                                    ->html()
                                    ->placeholder('Not specified'),
                                Infolists\Components\TextEntry::make('kewajiban_eci')
                                    ->label('📝 Kewajiban ECI')
                                    ->html()
                                    ->placeholder('Not specified'),
                                Infolists\Components\TextEntry::make('hak_eci')
                                    ->label('✅ Hak ECI')
                                    ->html()
                                    ->placeholder('Not specified'),
                            ]),
                    ]),

                // CONTRACT TERMS - SELALU TAMPIL
                Infolists\Components\Section::make('📋 Regulasi Finansial')
                    ->schema([
                        Infolists\Components\TextEntry::make('syarat_ketentuan_pembayaran')
                            ->label('💰 Syarat & Ketentuan Pembayaran')
                            ->columnSpanFull()
                            ->html()
                            ->placeholder('Not specified'),
                        Infolists\Components\TextEntry::make('pajak')
                            ->label('📊 Ketentuan Pajak')
                            ->columnSpanFull()
                            ->html()
                            ->placeholder('Not specified'),
                    ]),

                // ADDITIONAL TERMS - SELALU TAMPIL
                Infolists\Components\Section::make('📄 Ketentuan Tambahan')
                    ->schema([
                        Infolists\Components\TextEntry::make('ketentuan_lain')
                            ->label('📋 Ketentuan Lainnya')
                            ->columnSpanFull()
                            ->html()
                            ->placeholder('Tidak ada ketentuan tambahan.'),
                    ]),

                // ATTACHMENTS - SELALU TAMPIL tanpa visible condition
                Infolists\Components\Section::make('📎 Lampiran Dokumen')
                    ->schema([
                        Infolists\Components\TextEntry::make('dokumen_utama')
                            ->label('📄 Main Document')
                            ->formatStateUsing(function($state) {
                                if (!$state) return '❌ Not uploaded';
                                $filename = basename($state);
                                $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                return "📁 {$filename} ({$extension})";
                            })
                            ->url(fn ($record) => $record->dokumen_utama ? asset('storage/' . $record->dokumen_utama) : null)
                            ->openUrlInNewTab()
                            ->color(fn($state) => $state ? 'success' : 'danger')
                            ->tooltip(fn($state) => $state ? basename($state) : 'No file'),
                        Infolists\Components\Grid::make(2)
                            ->schema([                                
                                Infolists\Components\TextEntry::make('akta_pendirian')
                                    ->label('🏢 Akta Pendirian')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->akta_pendirian ? asset('storage/' . $record->akta_pendirian) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->akta_pendirian), // full text muncul di hover

                                Infolists\Components\TextEntry::make('akta_perubahan')
                                    ->label('📋 Akta Perubahan')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->akta_perubahan ? asset('storage/' . $record->akta_perubahan) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->akta_perubahan), // full text muncul di hover

                                Infolists\Components\TextEntry::make('npwp')
                                    ->label('📋 NPWP (Nomor Pokok Wajib Pajak)')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->npwp ? asset('storage/' . $record->npwp) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->npwp), // full text muncul di hover
                                
                                Infolists\Components\TextEntry::make('ktp_direktur')
                                    ->label('🆔 KTP kuasa Direktur')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->ktp_direktur ? asset('storage/' . $record->ktp_direktur) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->ktp_direktur), // full text muncul di hover

                                Infolists\Components\TextEntry::make('nib')
                                    ->label('🏪 NIB (Nomor Induk Berusaha)')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->nib ? asset('storage/' . $record->nib) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->nib), // full text muncul di hover
                                
                                Infolists\Components\TextEntry::make('surat_kuasa')
                                    ->label('✍️ Surat kuasa Direktur')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->surat_kuasa ? asset('storage/' . $record->surat_kuasa) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->limit(30) // batasi jadi 30 karakter, sisanya diganti ...
                                    ->tooltip(fn ($record) => $record->surat_kuasa), // full text muncul di hover
                            ]),
                    ]),

                Infolists\Components\Section::make('Timeline')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('submitted_at')
                            ->label('Submitted At')
                            ->dateTime()
                            ->placeholder('Not submitted'),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Completed At')
                            ->dateTime()
                            ->placeholder('Not completed'),
                    ])->columns(3),

                // APPROVAL HISTORY
                Infolists\Components\Section::make('📊 Approval History')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('approvals')
                            ->schema([
                                Infolists\Components\TextEntry::make('approver_name')
                                    ->label('👤 Approver'),
                                Infolists\Components\TextEntry::make('approval_type')
                                    ->label('🏷️ Role'),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('📋 Status')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('approved_at')
                                    ->label('📅 Date')
                                    ->dateTime()
                                    ->placeholder('⏳ Pending'),
                                Infolists\Components\TextEntry::make('comments')
                                    ->label('💬 Comments')
                                    ->placeholder('No comments'),
                            ])
                            ->columns(5),
                    ])
                    ->visible(fn($record) => $record->approvals && $record->approvals->count() > 0),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyDocumentRequests::route('/'),
            'create' => Pages\CreateMyDocumentRequest::route('/create'),
            'view' => Pages\ViewMyDocumentRequest::route('/{record}'),
            'edit' => Pages\EditMyDocumentRequest::route('/{record}/edit'),
        ];
    }

    public static function getRomawi($bln): string
    {
        $romawi = [
            1 => "I", 2 => "II", 3 => "III", 4 => "IV", 5 => "V", 6 => "VI",
            7 => "VII", 8 => "VIII", 9 => "IX", 10 => "X", 11 => "XI", 12 => "XII"
        ];
        
        return $romawi[$bln] ?? "I";
    }

    public static function getDirector1FromDirektorat($direktorat): array
{
    try {
        // Director mapping based on direktorat
        $directorMapping = [
            'IT' => [
                'nik' => '14070619', 
                'name' => 'Wiradi',
                'title' => 'Finance & Admin IT Director',
                'direktorat' => 'IT'
            ],
            'LEGAL' => [
                'nik' => '20050037', 
                'name' => 'Widi Satya Chitra',
                'title' => 'Corporate Secretary, Legal & Business Development Director',
                'direktorat' => 'Legal'
            ],
            'EXECUTIVE' => [
                'nik' => '710144', 
                'name' => 'Lyvia Mariana',
                'title' => 'Direktur Utama',
                'direktorat' => 'Executive'
            ],
        ];
        
        $direktoratKey = strtoupper($direktorat);
        return $directorMapping[$direktoratKey] ?? $directorMapping['IT']; // Default to IT
        
    } catch (\Exception $e) {
        \Log::error('Error getting Director 1 from direktorat', [
            'direktorat' => $direktorat,
            'error' => $e->getMessage()
        ]);
        
        return [
            'nik' => '14070619', 
            'name' => 'Wiradi',
            'title' => 'Finance & Admin IT Director',
            'direktorat' => 'IT'
        ];
    }
}
}