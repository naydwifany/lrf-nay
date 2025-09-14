<?php
// app/Filament/Admin/Resources/DocumentRequestResource.php - PART 1

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DocumentRequestResource\Pages;
use App\Models\DocumentRequest;
use App\Models\DocumentApproval;
use App\Services\DocumentWorkflowService;
use App\Services\DocumentRequestService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Resources\Pages\ViewRecord;

class DocumentRequestResource extends Resource
{
    protected static ?string $model = DocumentRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Document Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $navigationLabel = 'Document Requests';
    
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user) return null;
        
        // Count pending approvals for current user
        $pendingCount = DocumentApproval::where('approver_nik', $user->nik)
            ->where('status', 'pending')
            ->count();
            
        return $pendingCount > 0 ? (string) $pendingCount : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('Document Information')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\Section::make('📄 Basic Document Details')
                                ->schema([
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\TextInput::make('nomor_dokumen')
                                                ->label('Document Number')
                                                ->placeholder('Auto-generated after submission')
                                                ->disabled()
                                                ->dehydrated(false),
                                            Forms\Components\TextInput::make('title')
                                                ->label('Document Title')
                                                ->required()
                                                ->maxLength(255)
                                                ->columnSpan(2)
                                                ->placeholder('Enter a descriptive title for your document'),
                                        ]),
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\Select::make('tipe_dokumen')
                                                ->relationship('doctype', 'document_name')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->label('Document Type')
                                                ->placeholder('Select document type'),
                                            /* priority field is unnecessary
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
                                            Forms\Components\Select::make('doc_filter')
                                                ->label('Document Action')
                                                ->options([
                                                    'review' => 'Review Existing Document',
                                                    'create' => 'Create New Document'
                                                ])
                                                ->default('create')
                                                ->required()
                                                ->native(false),
                                        ]),
                                    Forms\Components\Textarea::make('description')
                                        ->label('Document Description')
                                        ->rows(4)
                                        ->maxLength(1000)
                                        ->required()
                                        ->placeholder('Provide a clear description of the document purpose and scope')
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    Forms\Components\Wizard\Step::make('Requester Information')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\Section::make('👤 Personal Information')
                                ->schema([
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\TextInput::make('nik')
                                                ->label('Employee ID (NIK)')
                                                ->required()
                                                ->maxLength(50)
                                                ->default(fn() => auth()->user()->nik ?? '')
                                                ->placeholder('Enter your employee ID'),
                                            Forms\Components\TextInput::make('nama')
                                                ->label('Full Name')
                                                ->required()
                                                ->maxLength(255)
                                                ->default(fn() => auth()->user()->name ?? '')
                                                ->placeholder('Enter your full name'),
                                            Forms\Components\TextInput::make('jabatan')
                                                ->label('Position/Job Title')
                                                ->maxLength(255)
                                                ->placeholder('Your current position'),
                                        ]),
                                ]),
                            Forms\Components\Section::make('🏢 Organizational Information')
                                ->schema([
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\TextInput::make('divisi')
                                                ->label('Division')
                                                ->maxLength(255)
                                                ->placeholder('Your division'),
                                            Forms\Components\TextInput::make('dept')
                                                ->label('Department')
                                                ->maxLength(255)
                                                ->placeholder('Your department'),
                                            Forms\Components\TextInput::make('direktorat')
                                                ->label('Directorate')
                                                ->maxLength(255)
                                                ->placeholder('Your directorate'),
                                        ]),
                                    Forms\Components\TextInput::make('nik_atasan')
                                        ->label('Supervisor NIK')
                                        ->maxLength(50)
                                        ->placeholder('Your direct supervisor NIK')
                                        ->helperText('This will be auto-filled from API login data'),
                                ]),
                        ]),

                    Forms\Components\Wizard\Step::make('Business Requirements')
                        ->icon('heroicon-o-briefcase')
                        ->schema([
                            /*
                            Forms\Components\Section::make('📋 Business Justification')
                                ->schema([
                                    Forms\Components\RichEditor::make('data')
                                        ->label('Business Justification & Requirements')
                                        ->required()
                                        ->placeholder('Explain the business need, justification, and specific requirements for this document')
                                        ->toolbarButtons([
                                            'bold', 'italic', 'underline', 'strike',
                                            'bulletList', 'orderedList',
                                            'h2', 'h3', 'paragraph',
                                            'link', 'undo', 'redo'
                                        ])
                                        ->columnSpanFull(),
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('lama_perjanjian_surat')
                                                ->label('Contract/Agreement Duration')
                                                ->placeholder('e.g., 12 months, 2 years, permanent')
                                                ->helperText('Specify the intended duration of the agreement'),
                                            Forms\Components\DatePicker::make('target_completion')
                                                ->label('Target Completion Date')
                                                ->placeholder('When do you need this completed?')
                                                ->helperText('Helps prioritize and plan the workflow'),
                                        ]),
                                ]),
                            */
                        ]),

                    Forms\Components\Wizard\Step::make('Terms & Conditions')
                        ->icon('heroicon-o-scale')
                        ->schema([
                            Forms\Components\Section::make('💰 Financial Terms')
                                ->schema([
                                    Forms\Components\RichEditor::make('syarat_ketentuan_pembayaran')
                                        ->label('Payment Terms & Conditions')
                                        ->placeholder('Detail payment terms, schedules, methods, and conditions')
                                        ->toolbarButtons([
                                            'bold', 'italic', 'bulletList', 'orderedList',
                                            'h3', 'paragraph', 'undo', 'redo'
                                        ])
                                        ->columnSpanFull(),
                                    Forms\Components\RichEditor::make('pajak')
                                        ->label('Tax Considerations')
                                        ->placeholder('Specify tax implications, rates, and responsibilities')
                                        ->toolbarButtons([
                                            'bold', 'italic', 'bulletList', 'orderedList',
                                            'h3', 'paragraph', 'undo', 'redo'
                                        ])
                                        ->columnSpanFull(),
                                ]),
                            Forms\Components\Section::make('⚖️ Rights & Obligations')
                                ->schema([
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\RichEditor::make('hak_mitra')
                                                ->label('Partner Rights')
                                                ->placeholder('Define rights of the partner/counterparty')
                                                ->toolbarButtons(['bold', 'italic', 'bulletList', 'paragraph']),
                                            Forms\Components\RichEditor::make('kewajiban_mitra')
                                                ->label('Partner Obligations')
                                                ->placeholder('Define obligations of the partner/counterparty')
                                                ->toolbarButtons(['bold', 'italic', 'bulletList', 'paragraph']),
                                        ]),
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\RichEditor::make('hak_eci')
                                                ->label('Company (ECI) Rights')
                                                ->placeholder('Define rights of the company')
                                                ->toolbarButtons(['bold', 'italic', 'bulletList', 'paragraph']),
                                            Forms\Components\RichEditor::make('kewajiban_eci')
                                                ->label('Company (ECI) Obligations')
                                                ->placeholder('Define obligations of the company')
                                                ->toolbarButtons(['bold', 'italic', 'bulletList', 'paragraph']),
                                        ]),
                                    Forms\Components\RichEditor::make('ketentuan_lain')
                                        ->label('Additional Provisions')
                                        ->placeholder('Any other terms, conditions, or special provisions')
                                        ->toolbarButtons([
                                            'bold', 'italic', 'bulletList', 'orderedList',
                                            'h3', 'paragraph', 'undo', 'redo'
                                        ])
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    Forms\Components\Wizard\Step::make('Document Attachments')
                        ->icon('heroicon-o-paper-clip')
                        ->schema([
                            Forms\Components\Section::make('📎 Upload Required Documents')
                                ->description('Upload all necessary supporting documents')
                                ->schema([
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\FileUpload::make('dokumen_utama')
                                                ->label('Main Document')
                                                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                                ->maxSize(10240) // 10MB
                                                ->downloadable()
                                                ->previewable()
                                                ->helperText('Upload the primary document (PDF, DOC, DOCX - Max: 10MB)'),
                                            Forms\Components\FileUpload::make('akta_pendirian')
                                                ->label('Articles of Incorporation')
                                                ->acceptedFileTypes(['application/pdf'])
                                                ->maxSize(5120) // 5MB
                                                ->downloadable()
                                                ->previewable()
                                                ->helperText('Company incorporation documents (PDF - Max: 5MB)'),
                                        ]),
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\FileUpload::make('supporting_documents')
                                                ->label('Supporting Documents')
                                                ->multiple()
                                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                                ->maxSize(5120)
                                                ->maxFiles(5)
                                                ->downloadable()
                                                ->previewable()
                                                ->helperText('Additional supporting files (Max: 5 files, 5MB each)'),
                                            Forms\Components\FileUpload::make('reference_documents')
                                                ->label('Reference Documents')
                                                ->multiple()
                                                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                                ->maxSize(5120)
                                                ->maxFiles(3)
                                                ->downloadable()
                                                ->previewable()
                                                ->helperText('Reference materials or templates (Max: 3 files, 5MB each)'),
                                        ]),
                                ]),
                        ]),
                ])
                ->columnSpanFull()
                ->persistStepInQueryString()
                ->submitAction(new \Filament\Forms\Components\Actions\Action('submit'))
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('Doc Number')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Pending')
                    ->copyable()
                    ->size('sm')
                    ->color('primary')
                    ->weight(FontWeight::Bold),
                
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(function ($record) {
                        return $record->title;
                    })
                    ->weight(FontWeight::Medium),
                
                Tables\Columns\TextColumn::make('nama')
                    ->label('Requester')
                    ->searchable()
                    ->sortable()
                    ->size('sm')
                    ->weight(FontWeight::Medium),
                
                Tables\Columns\TextColumn::make('divisi')
                    ->label('Division')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->size('sm'),
                
                Tables\Columns\TextColumn::make('doctype.document_name')
                    ->label('Doc Type')
                    ->badge()
                    ->color('primary')
                    ->size('sm'),

                Tables\Columns\BadgeColumn::make('decision')
                    ->label('Your Decision')
                    ->getStateUsing(function ($record) {
                        $userNik = auth()->user()->nik;

                        // Ambil approval yang spesifik untuk user login
                        $approval = $record->approvals->firstWhere('approver_nik', $userNik);

                        if (! $approval) {
                            return 'You haven\'t been involved yet';
                        }

                        if (is_null($approval->status) || $approval->status === 'pending') {
                            return 'Your Turn';
                        }

                        return $approval->status; // 'approved' / 'rejected'
                    }),
                
                Tables\Columns\BadgeColumn::make('computed_status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending_supervisor',
                        'info'    => ['pending_gm', 'in_discussion'],
                        'primary' => ['pending_legal', 'pending_legal_admin'],
                        'gray'    => 'submitted',

                        // AO stages
                        'purple'  => \App\Models\AgreementOverview::STATUS_PENDING_HEAD,
                        'success' => ['agreement_creation', 'completed'],
                        'danger'  => \App\Models\AgreementOverview::STATUS_REJECTED,
                    ])
                    ->formatStateUsing(function ($state, $record) {
                        // sekarang $state sudah computed_status (bisa AO / docreq)
                        return match ($state) {
                            \App\Models\AgreementOverview::STATUS_DRAFT             => 'AO Draft',
                            \App\Models\AgreementOverview::STATUS_PENDING_HEAD      => 'AO - Pending Head',
                            \App\Models\AgreementOverview::STATUS_PENDING_GM        => 'AO - Pending GM',
                            \App\Models\AgreementOverview::STATUS_PENDING_FINANCE   => 'AO - Pending Finance',
                            \App\Models\AgreementOverview::STATUS_PENDING_LEGAL     => 'AO - Pending Legal',
                            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR1 => 'AO - Pending Director 1',
                            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR2 => 'AO - Pending Director 2',
                            \App\Models\AgreementOverview::STATUS_APPROVED          => 'AO Approved',
                            \App\Models\AgreementOverview::STATUS_REJECTED          => 'AO Rejected',
                            \App\Models\AgreementOverview::STATUS_REDISCUSS         => 'AO Back to Discussion',

                            'pending_supervisor'   => 'Pending Supervisor',
                            'pending_gm'           => 'Pending GM',
                            'pending_legal_admin'  => 'Pending Admin Legal',
                            'pending_legal'        => 'Pending Legal',
                            'in_discussion'        => 'On Discussion Forum',
                            'agreement_creation'   => 'Ready for AO',
                            'completed'            => 'Agreement Successful',
                            'approved'             => 'Approved',
                            'rejected'             => 'Rejected',
                            default                => 'You haven\'t been involved yet',
                        };
                    }),
                
                /* priority field is unnecessary
                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'success' => 'low',
                        'primary' => 'medium',
                        'warning' => 'high',
                        'danger' => 'urgent',
                    ])
                    ->size('sm'),
                */

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y')
                    ->dateTime()
                    ->sortable()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        // Pakai completed_at kalau ada, kalau null pakai status fallback
                        return $record->completed_at ?? $record->status;
                    })
                    ->formatStateUsing(function ($state) {
                        if ($state instanceof \Carbon\Carbon) {
                            return $state->format('d M Y H:i');
                        }

                        return match ($state) {
                            'rejected' => 'Rejected',
                            default => 'Still on Progress',
                        };
                    }),
                
                /*
                Tables\Columns\TextColumn::make('days_pending')
                    ->label('Days Pending')
                    ->getStateUsing(fn($record) => $record->submitted_at ? now()->diffInDays($record->submitted_at) : 0)
                    ->badge()
                    ->color(fn($state) => $state > 14 ? 'danger' : ($state > 7 ? 'warning' : 'success'))
                    ->size('sm'),
                
                Tables\Columns\IconColumn::make('is_draft')
                    ->boolean()
                    ->label('Draft')
                    ->trueIcon('heroicon-o-pencil-square')
                    ->falseIcon('heroicon-o-paper-airplane')
                    ->trueColor('gray')
                    ->falseColor('success')
                    ->size('sm'),
                */
            ])
            ->filters([
                Filter::make('computed_status')
                    ->label('Status')
                    ->form([
                        Forms\Components\MultiSelect::make('statuses')
                            ->label('Select Status')
                            ->options([
                                'pending_supervisor'   => 'Pending Supervisor',
                                'pending_gm'           => 'Pending GM',
                                'pending_legal_admin'  => 'Pending Admin Legal',
                                'pending_legal'        => 'Pending Legal',
                                'in_discussion'        => 'On Discussion Forum',
                                'agreement_creation'   => 'Ready for AO',
                                'completed'            => 'Agreement Successful',
                                'approved'             => 'Approved',
                                'rejected'             => 'Rejected',

                                // AO stages
                                \App\Models\AgreementOverview::STATUS_DRAFT             => 'AO Draft',
                                \App\Models\AgreementOverview::STATUS_PENDING_HEAD      => 'AO - Pending Head',
                                \App\Models\AgreementOverview::STATUS_PENDING_GM        => 'AO - Pending GM',
                                \App\Models\AgreementOverview::STATUS_PENDING_FINANCE   => 'AO - Pending Finance',
                                \App\Models\AgreementOverview::STATUS_PENDING_LEGAL     => 'AO - Pending Legal',
                                \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR1 => 'AO - Pending Director 1',
                                \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR2 => 'AO - Pending Director 2',
                                \App\Models\AgreementOverview::STATUS_APPROVED          => 'AO Approved',
                                \App\Models\AgreementOverview::STATUS_REJECTED          => 'AO Rejected',
                                \App\Models\AgreementOverview::STATUS_REDISCUSS         => 'AO Back to Discussion',
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['statuses'])) {
                            return;
                        }

                        $query->where(function ($subQuery) use ($data) {
                            $subQuery->whereHas('agreementOverview', function ($aoQuery) use ($data) {
                                $aoQuery->whereIn('status', $data['statuses']);
                            })->orWhere(function ($docReqQuery) use ($data) {
                                $docReqQuery->whereIn('status', $data['statuses']);
                            });
                        });
                    }),
                
                /* priority field is unnecessary
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ])
                    ->multiple(),
                */

                Filter::make('decision')
                    ->label('Your Decision')
                    ->form([
                        Forms\Components\Select::make('decisions')
                            ->label('Select a status')
                            ->options([
                                'approved'   => 'Approved',
                                'rejected'   => 'Rejected',
                                'your_turn'  => 'Your Turn',
                                'not_involved' => 'You haven\'t been involved yet',
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['decisions'])) {
                            return;
                        }

                        $userNik = auth()->user()->nik;

                        $query->where(function ($q) use ($data, $userNik) {
                            switch ($data['decisions']) {
                                case 'approved':
                                    $q->whereHas('approvals', function ($approvalQuery) use ($userNik) {
                                        $approvalQuery->where('approver_nik', $userNik)
                                            ->where('status', 'approved');
                                    });
                                    break;

                                case 'rejected':
                                    $q->whereHas('approvals', function ($approvalQuery) use ($userNik) {
                                        $approvalQuery->where('approver_nik', $userNik)
                                            ->where('status', 'rejected');
                                    });
                                    break;

                                case 'your_turn':
                                    $q->whereHas('approvals', function ($approvalQuery) use ($userNik) {
                                        $approvalQuery->where('approver_nik', $userNik)
                                            ->where(function ($sub) {
                                                $sub->whereNull('status')
                                                    ->orWhere('status', 'pending');
                                            });
                                    });
                                    break;

                                case 'not_involved':
                                    $q->whereDoesntHave('approvals', function ($approvalQuery) use ($userNik) {
                                        $approvalQuery->where('approver_nik', $userNik);
                                    });
                                    break;
                            }
                        });
                    }),

                SelectFilter::make('tipe_dokumen')
                    ->relationship('doctype', 'document_name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                
                SelectFilter::make(name: 'divisi')
                    ->options(function () {
                        return DocumentRequest::whereNotNull('divisi')
                            ->distinct()
                            ->pluck('divisi', 'divisi')
                            ->filter()
                            ->toArray();
                    })
                    ->searchable()
                    ->multiple(),
                
                /*
                Filter::make('my_requests')
                    ->label('My Requests')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('nik', auth()->user()->nik ?? '')
                    )
                    ->toggle(),
                
                Filter::make('pending_my_approval')
                    ->label('Pending My Approval')
                    ->query(function (Builder $query): Builder {
                        $user = auth()->user();
                        if (!$user || !$user->nik) return $query->whereRaw('1=0');
                        
                        return $query->whereHas('approvals', function ($q) use ($user) {
                            $q->where('approver_nik', $user->nik)
                              ->where('status', 'pending');
                        });
                    })
                    ->toggle(),
                
                Filter::make('overdue')
                    ->label('Overdue (>14 days)')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('submitted_at', '<', now()->subDays(14))
                              ->whereNotIn('status', ['approved', 'rejected'])
                    )
                    ->toggle(),
                
                // priority field is unnecessary
                Filter::make('urgent_pending')
                    ->label('Urgent & Pending')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('priority', 'urgent')
                              ->whereNotIn('status', ['approved', 'rejected'])
                    )
                    ->toggle(),
                */

                /*
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Created From'),
                        DatePicker::make('created_until')
                            ->label('Created Until'),
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
                */
            ])
            // CONTINUATION FROM PART 1 - ACTIONS SECTION
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('info')
                    ->size('sm'),
                
                Tables\Actions\EditAction::make()
                    ->color('warning')
                    ->size('sm')
                    ->visible(function ($record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        
                        // Only allow editing drafts by creator or admin
                        return ($record->status === 'draft' && $record->nik === $user->nik) || 
                               $user->role === 'admin';
                    }),
                
                Tables\Actions\Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->size('sm')
                    ->action(function ($record) {
                        try {
                            $workflowService = app(DocumentWorkflowService::class);
                            $workflowService->submitDocument($record, auth()->user());
                            
                            Notification::make()
                                ->title('Document submitted successfully')
                                ->body('Your document has been submitted for approval.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error submitting document')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Submit Document')
                    ->modalDescription('Are you sure you want to submit this document for approval? You won\'t be able to edit it after submission.')
                    ->visible(function ($record) {
                        $user = auth()->user();
                        if (!$user) return false;
                        
                        return $record->status === 'draft' && 
                               $record->nik === $user->nik &&
                               $record->title && 
                               $record->description;
                    }),
                
                // Tables\Actions\Action::make('approve')
                //     ->label('Approve')
                //     ->icon('heroicon-o-check-circle')
                //     ->color('success')
                //     ->size('sm')
                //     ->form([
                //         Forms\Components\Textarea::make('approval_comments')
                //             ->label('Approval Comments')
                //             ->rows(3)
                //             ->placeholder('Add your comments for this approval (optional)'),
                //     ])
                //     ->action(function ($record, array $data) {
                //         try {
                //             $workflowService = app(DocumentWorkflowService::class);
                //             $workflowService->approve(
                //                 $record, 
                //                 auth()->user(), 
                //                 $data['approval_comments'] ?? null
                //             );
                            
                //             Notification::make()
                //                 ->title('Document approved successfully')
                //                 ->body('Document has been approved and moved to the next stage.')
                //                 ->success()
                //                 ->send();
                //         } catch (\Exception $e) {
                //             Notification::make()
                //                 ->title('Error approving document')
                //                 ->body($e->getMessage())
                //                 ->danger()
                //                 ->send();
                //         }
                //     })
                //     ->requiresConfirmation()
                //     ->modalHeading('Approve Document')
                //     ->modalDescription('Are you sure you want to approve this document?')
                //     ->visible(function ($record) {
                //         $user = auth()->user();
                //         if (!$user || !$user->nik) return false;
                        
                //         // FIXED LOGIC: Check role-based permission AND document status
                        
                //         // 1. User cannot approve their own request
                //         if ($record->nik === $user->nik) {
                //             return false;
                //         }
                        
                //         // 2. Check if document is in a status that can be approved
                //         if (!in_array($record->status, [
                //             'pending_supervisor', 
                //             'pending_gm', 
                //             'pending_legal_admin'
                //         ])) {
                //             return false;
                //         }
                        
                //         // 3. Role-based approval permission
                //         $canApproveByRole = match($record->status) {
                //             'pending_supervisor' => in_array($user->role, ['supervisor', 'manager', 'senior_manager', 'general_manager']),
                //             'pending_gm' => in_array($user->role, ['general_manager', 'senior_manager']),
                //             'pending_legal_admin' => in_array($user->role, ['admin_legal', 'legal_admin', 'head_legal']),
                //             default => false
                //         };
                        
                //         if (!$canApproveByRole) {
                //             return false;
                //         }
                        
                //         // 4. Optional: Check if user has pending approval record (if exists)
                //         $hasPendingApproval = DocumentApproval::where('document_request_id', $record->id)
                //             ->where('approver_nik', $user->nik)
                //             ->where('status', 'pending')
                //             ->exists();
                            
                //         // If approval record exists, use it. If not, rely on role-based permission
                //         return $hasPendingApproval || $canApproveByRole;
                //     }),
                
                // Tables\Actions\Action::make('reject')
                //     ->label('Reject')
                //     ->icon('heroicon-o-x-circle')
                //     ->color('danger')
                //     ->size('sm')
                //     ->form([
                //         Forms\Components\Textarea::make('rejection_reason')
                //             ->label('Rejection Reason')
                //             ->required()
                //             ->rows(3)
                //             ->placeholder('Please provide a clear reason for rejection'),
                //     ])
                //     ->action(function ($record, array $data) {
                //         try {
                //             $workflowService = app(DocumentWorkflowService::class);
                //             $workflowService->reject(
                //                 $record, 
                //                 auth()->user(), 
                //                 $data['rejection_reason']
                //             );
                            
                //             Notification::make()
                //                 ->title('Document rejected successfully')
                //                 ->body('Document has been rejected and requester will be notified.')
                //                 ->success()
                //                 ->send();
                //         } catch (\Exception $e) {
                //             Notification::make()
                //                 ->title('Error rejecting document')
                //                 ->body($e->getMessage())
                //                 ->danger()
                //                 ->send();
                //         }
                //     })
                //     ->requiresConfirmation()
                //     ->modalHeading('Reject Document')
                //     ->modalDescription('Are you sure you want to reject this document? This action cannot be undone.')
                //     ->visible(function ($record) {
                //         $user = auth()->user();
                //         if (!$user || !$user->nik) return false;
                        
                //         // Check if user has pending approval for this document
                //         return DocumentApproval::where('document_request_id', $record->id)
                //             ->where('approver_nik', $user->nik)
                //             ->where('status', 'pending')
                //             ->exists();
                //     }),
                
            Tables\Actions\Action::make('view_discussion')
                ->label('View Discussion')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('primary')
                ->visible(function ($record) {
                    $user = auth()->user(); // ambil user yang sedang login
                    $allowedRoles = ['head_legal','reviewer_legal','general_manager','finance','head_finance','senior_manager','manager','supervisor','head'];
                    
                    // Tombol hanya terlihat jika status sesuai dan user punya role yang diperbolehkan
                    return in_array($record->status, ['in_discussion', 'agreement_creation']) 
                        && in_array($user->role, $allowedRoles);
                })
                ->url(fn($record) => static::getUrl('discussion', ['record' => $record])),

            /*
            Tables\Actions\Action::make('close_discussion_forum')
                ->label('Close Discussion')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->size('sm')
                ->requiresConfirmation()
                ->modalHeading('Close Discussion Forum')
                ->modalDescription('This will close the discussion and move document to Agreement Overview creation phase.')
                ->form([
                    Forms\Components\Textarea::make('closure_notes')
                        ->label('Closure Notes (Optional)')
                        ->placeholder('Provide reasons for closing the discussion...')
                        ->rows(3)
                ])
                ->action(function (DocumentRequest $record, array $data) {
                    try {
                        // Check finance participation
                        $financeComments = \App\Models\DocumentComment::where('document_request_id', $record->id)
                            ->where('user_role', 'finance')
                            ->where('is_forum_closed', false)
                            ->count();
                            
                        if ($financeComments == 0) {
                            Notification::make()
                                ->title('Cannot close discussion')
                                ->body('Finance team must participate in the discussion before it can be closed.')
                                ->danger()
                                ->duration(5000)
                                ->send();
                            return;
                        }
                        
                        // Close all comments in this discussion
                        \App\Models\DocumentComment::where('document_request_id', $record->id)
                            ->update(['is_forum_closed' => true]);
                        
                        // Update document status to agreement_creation
                        $record->update(['status' => 'agreement_creation']);
                        
                        // Create closure comment
                        \App\Models\DocumentComment::create([
                            'document_request_id' => $record->id,
                            'user_id' => auth()->id(),
                            'user_nik' => auth()->user()->nik ?? 'ADMIN',
                            'user_name' => auth()->user()->name ?? 'Admin',
                            'user_role' => 'head_legal',
                            'comment' => 'Discussion forum has been closed by Head Legal. ' . ($data['closure_notes'] ?? 'Ready to proceed to Agreement Overview creation.'),
                            'is_forum_closed' => true,
                        ]);
                        
                        Notification::make()
                            ->title('Discussion closed successfully!')
                            ->body('Document status updated to Agreement Creation. You can now create the Agreement Overview.')
                            ->success()
                            ->duration(5000)
                            ->send();
                            
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error closing discussion')
                            ->body('Error: ' . $e->getMessage())
                            ->danger()
                            ->duration(5000)
                            ->send();
                    }
                })
                ->visible(function (DocumentRequest $record) {
                    return $record->status === 'in_discussion' && auth()->user()->role === 'head_legal';
                })
                ->disabled(function (DocumentRequest $record) {
                    // FIXED: Use method without user parameter
                    return !$record->canBeClosed();
                })
                ->tooltip(function (DocumentRequest $record) {
                    if (!$record->hasFinanceParticipated()) {
                        return 'Finance team must participate first';
                    }
                    if (!$record->canBeClosed()) {
                        return 'Discussion cannot be closed yet';
                    }
                    return 'Close discussion forum';
                }),
                */
    
                /*
                Tables\Actions\Action::make('view_approval_history')
                    ->label('Approval History')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->size('sm')
                    ->modalContent(function ($record) {
                        $approvals = $record->approvals()->with('approver')->orderBy('created_at')->get();
                        
                        $content = '<div class="space-y-4">';
                        
                        if ($approvals->isEmpty()) {
                            $content .= '<p class="text-gray-500">No approval history yet.</p>';
                        } else {
                            foreach ($approvals as $approval) {
                                $statusColor = match($approval->status) {
                                    'pending' => 'text-yellow-600 bg-yellow-50',
                                    'approved' => 'text-green-600 bg-green-50', 
                                    'rejected' => 'text-red-600 bg-red-50',
                                    default => 'text-gray-600 bg-gray-50'
                                };
                                
                                $approverName = $approval->approver->name ?? 'Unknown';
                                $approvalType = ucfirst(str_replace('_', ' ', $approval->approval_type));
                                $date = $approval->approved_at ? $approval->approved_at->format('M j, Y H:i') : 'Pending';
                                $comments = $approval->comments ?: 'No comments';
                                
                                $content .= "
                                    <div class='border rounded-lg p-4 bg-white'>
                                        <div class='flex justify-between items-start mb-2'>
                                            <div>
                                                <h4 class='font-medium text-gray-900'>{$approverName}</h4>
                                                <p class='text-sm text-gray-500'>{$approvalType}</p>
                                            </div>
                                            <span class='inline-flex px-2 py-1 text-xs font-medium rounded-full {$statusColor}'>
                                                " . ucfirst($approval->status) . "
                                            </span>
                                        </div>
                                        <p class='text-sm text-gray-600 mb-1'><strong>Date:</strong> {$date}</p>
                                        <p class='text-sm text-gray-600'><strong>Comments:</strong> {$comments}</p>
                                    </div>
                                ";
                            }
                        }
                        
                        $content .= '</div>';
                        
                        return new \Illuminate\Support\HtmlString($content);
                    })
                    ->modalHeading('Approval History')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn($record) => $record->status !== 'draft'),
                */
            ])

            /*
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(function () {
                            $user = auth()->user();
                            return $user && ($user->role === 'admin' || $user->role === 'super_admin');
                        }),
                    
                    Tables\Actions\BulkAction::make('bulk_submit')
                        ->label('Submit Selected')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            $errors = 0;
                            
                            foreach ($records as $record) {
                                if ($record->status === 'draft' && $record->nik === auth()->user()->nik) {
                                    try {
                                        app(DocumentWorkflowService::class)->submitDocument($record, auth()->user());
                                        $count++;
                                    } catch (\Exception $e) {
                                        $errors++;
                                    }
                                }
                            }
                            
                            if ($count > 0) {
                                Notification::make()
                                    ->title("{$count} documents submitted successfully")
                                    ->success()
                                    ->send();
                            }
                            
                            if ($errors > 0) {
                                Notification::make()
                                    ->title("{$errors} documents failed to submit")
                                    ->warning()
                                    ->send();
                            }
                        }),
                ]),
            ])
            */
            ->actionsAlignment('start')
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->striped()
            ->emptyStateHeading('No Document Requests')
            ->emptyStateDescription('No document requests have been created yet. Create your first document request to get started.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create First Document Request')
                    ->color('primary'),
            ]);
    }

   
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('📄 Document Overview')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('nomor_dokumen')
                                    ->label('Nomor Dokumen')
                                    ->placeholder('Pending Assignment')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('title')
                                    ->label('Judul Dokumen')
                                    ->weight(FontWeight::Medium),
                                Infolists\Components\TextEntry::make('doctype.document_name')
                                    ->label('Tipe Dokumen')
                                    ->badge()
                                    ->color('info'),
                            ]),
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('computed_status')
                                    ->badge()
                                    ->label('Status')
                                    ->colors([
                                        'warning' => 'pending_supervisor',
                                        'info'    => 'pending_gm',
                                        'primary' => ['pending_legal', 'pending_legal_admin'],
                                        'gray'    => 'submitted',

                                        // AO stages
                                        'purple'  => \App\Models\AgreementOverview::STATUS_PENDING_HEAD,
                                        'success' => \App\Models\AgreementOverview::STATUS_APPROVED,
                                        'danger'  => \App\Models\AgreementOverview::STATUS_REJECTED,
                                    ])
                                    ->formatStateUsing(function ($state, $record) {
                                        // sekarang $state sudah computed_status (bisa AO / docreq)
                                        return match ($state) {
                                            \App\Models\AgreementOverview::STATUS_DRAFT             => 'AO Draft',
                                            \App\Models\AgreementOverview::STATUS_PENDING_HEAD      => 'AO - Pending Head',
                                            \App\Models\AgreementOverview::STATUS_PENDING_GM        => 'AO - Pending GM',
                                            \App\Models\AgreementOverview::STATUS_PENDING_FINANCE   => 'AO - Pending Finance',
                                            \App\Models\AgreementOverview::STATUS_PENDING_LEGAL     => 'AO - Pending Legal',
                                            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR1 => 'AO - Pending Director 1',
                                            \App\Models\AgreementOverview::STATUS_PENDING_DIRECTOR2 => 'AO - Pending Director 2',
                                            \App\Models\AgreementOverview::STATUS_APPROVED          => 'AO Approved',
                                            \App\Models\AgreementOverview::STATUS_REJECTED          => 'AO Rejected',
                                            \App\Models\AgreementOverview::STATUS_REDISCUSS         => 'AO Back to Discussion',

                                            'pending_supervisor'   => 'Pending Supervisor',
                                            'pending_gm'           => 'Pending GM',
                                            'pending_legal_admin'  => 'Pending Admin Legal',
                                            'pending_legal'        => 'Pending Legal',
                                            'in_discussion'        => 'On Discussion Forum',
                                            'agreement_creation'   => 'Ready for AO',
                                            'completed'            => 'Agreement Successful',
                                            'approved'             => 'Approved',
                                            'rejected'             => 'Rejected',
                                            default                => 'You haven\'t been involved yet',
                                        };
                                    }),                                
                                /* priority field is unnecessary
                                Infolists\Components\TextEntry::make('priority')
                                    ->label('Priority Level')
                                    ->badge()
                                    ->color(fn($state) => match($state) {
                                        'low' => 'success',
                                        'medium' => 'primary',
                                        'high' => 'warning',
                                        'urgent' => 'danger',
                                        default => 'gray'
                                    })
                                    ->formatStateUsing(fn($state) => ucfirst($state)),
                                */
                                Infolists\Components\TextEntry::make('doc_filter')
                                    ->label('Document Action')
                                    ->badge()
                                    ->color('secondary')
                                    ->formatStateUsing(fn($state) => $state === 'create' ? 'Create New' : 'Review Existing'),
                                /* is draft icon is unnecessary
                                Infolists\Components\IconEntry::make('is_draft')
                                    ->label('Draft Status')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-pencil-square')
                                    ->falseIcon('heroicon-o-paper-airplane')
                                    ->trueColor('gray')
                                    ->falseColor('success'),
                                */
                                Infolists\Components\TextEntry::make('lama_perjanjian_surat')
                                    ->label('⏰ Jangka Waktu Perjanjian')
                                    ->placeholder('Not specified'),
                            ]),
                        Infolists\Components\TextEntry::make('description')
                            ->label('📝 Deskripsi Dokumen')
                            ->html()
                            ->columnSpanFull()
                            ->placeholder('Tidak ada deskripsi pada Document Request ini.'),
                    ]),

                Infolists\Components\Section::make('👤 Requester Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('nama')
                                    ->label('Requester Name')
                                    ->weight(FontWeight::Medium)
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('nik')
                                    ->label('Employee ID (NIK)')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('jabatan')
                                    ->label('Position'),
                            ]),
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('divisi')
                                    ->label('Division')
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('dept')
                                    ->label('Department'),
                                Infolists\Components\TextEntry::make('direktorat')
                                    ->label('Directorate'),
                            ]),
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('nama_atasan')
                                    ->label('Supervisor Name')
                                    ->placeholder('Not specified'),
                                Infolists\Components\TextEntry::make('nik_atasan')
                                    ->label('Supervisor NIK')
                                    ->placeholder('Not specified'),
                            ]),
                    ]),

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
                    ])
                    ->collapsible(),

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
                    ])
                    ->collapsible(),

                // ADDITIONAL TERMS - SELALU TAMPIL
                Infolists\Components\Section::make('📄 Ketentuan Tambahan')
                    ->schema([
                        Infolists\Components\TextEntry::make('ketentuan_lain')
                            ->label('📋 Ketentuan Lainnya')
                            ->columnSpanFull()
                            ->html()
                            ->placeholder('Tidak ada ketentuan tambahan.'),
                    ])
                    ->collapsible(),

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
                    ])
                    ->collapsible(),
                    
// DISCUSSION FORUM - HANYA TAMPIL JIKA STATUS discussion
Infolists\Components\Section::make('Discussion Forum')
    ->schema([
        Infolists\Components\TextEntry::make('discussion_status')
            ->label('Discussion Status')
            ->getStateUsing(function ($record) {
                if ($record->status !== 'discussion') {
                    return 'Not in discussion phase';
                }
                
                if ($record->isDiscussionClosed()) {
                    return 'Closed';
                }
                
                return 'Active';
            })
            ->badge()
            ->color(fn ($state) => match($state) {
                'Active' => 'success',
                'Closed' => 'danger',
                default => 'gray'
            }),
            
        Infolists\Components\TextEntry::make('total_comments')
            ->label('Total Messages')
            ->getStateUsing(fn ($record) => $record->comments()->count()),
            
        Infolists\Components\TextEntry::make('participants')
            ->label('Participants')
            ->getStateUsing(fn ($record) => $record->comments()->distinct('user_nik')->count()),
            
        Infolists\Components\TextEntry::make('finance_participated')
            ->label('Finance Participated')
            ->getStateUsing(fn ($record) => 
                $record->hasFinanceParticipated() ? 'Already' : 'Not yet'
            )
            ->badge()
            ->color(fn ($state) => $state === 'Already' ? 'success' : 'gray'),
    ])
    ->columns(4)
    ->visible(fn ($record) => $record->status === 'discussion'),
                Infolists\Components\Section::make('⏱️ Timeline & Progress')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('📅 Created')
                                    ->dateTime()
                                    ->since(),
                                Infolists\Components\TextEntry::make('submitted_at')
                                    ->label('📤 Submitted')
                                    ->dateTime()
                                    ->since()
                                    ->placeholder('Not submitted yet'),
                                Infolists\Components\TextEntry::make('completed_at')
                                    ->label('✅ Completed')
                                    ->dateTime()
                                    ->since()
                                    ->placeholder('Not completed yet'),
                                Infolists\Components\TextEntry::make('processing_time')
                                    ->label('⏳ Processing Time')
                                    ->getStateUsing(function ($record) {
                                        if ($record->submitted_at && $record->completed_at) {
                                            return $record->submitted_at->diffForHumans($record->completed_at, true);
                                        } elseif ($record->submitted_at) {
                                            return $record->submitted_at->diffForHumans(now(), true) . ' (ongoing)';
                                        }
                                        return 'N/A';
                                    }),
                            ]),
                    ]),

                Infolists\Components\Section::make('🔄 Current Workflow Status')
                    ->schema([
                        Infolists\Components\TextEntry::make('current_approver')
                            ->label('Current Approver')
                            ->getStateUsing(function ($record) {
                                $approval = $record->approvals()
                                    ->where('status', 'pending')
                                    ->with('approver')
                                    ->first();
                                    
                                if (!$approval) {
                                    return $record->status === 'approved' ? 'Document Approved' : 'No pending approval';
                                }
                                
                                $approver = $approval->approver;
                                return $approver ? $approver->name . ' (' . $approval->approval_type . ')' : 'Approver not found';
                            })
                            ->badge()
                            ->color(fn($state) => str_contains($state, 'Approved') ? 'success' : 'warning'),
                        
                        Infolists\Components\TextEntry::make('next_action')
                            ->label('Next Action Required')
                            ->getStateUsing(function ($record) {
                                switch ($record->status) {
                                    case 'draft':
                                        return 'Submit document for approval';
                                    case 'pending_supervisor':
                                        return 'Waiting for supervisor approval';
                                    case 'pending_gm':
                                        return 'Waiting for general manager approval';
                                    case 'pending_legal_admin':
                                        return 'Waiting for legal admin review';
                                    case 'in_discussion':
                                        return 'Document in legal discussion forum';
                                    case 'approved':
                                        return 'Document approved - can proceed to agreement overview';
                                    case 'rejected':
                                        return 'Document rejected - no further action';
                                    default:
                                        return 'Unknown status';
                                }
                            })
                            ->badge()
                            ->color(fn($state) => match(true) {
                                str_contains($state, 'approved') => 'success',
                                str_contains($state, 'rejected') => 'danger',
                                str_contains($state, 'Submit') => 'warning',
                                default => 'info'
                            }),
                    ]),

                Infolists\Components\Section::make('📊 Approval History')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('approvals')
                            ->schema([
                                Infolists\Components\TextEntry::make('approver.name')
                                    ->label('👤 Approver')
                                    ->placeholder('Unknown')
                                    ->weight(FontWeight::Medium),
                                    
                                Infolists\Components\TextEntry::make('approval_type')
                                    ->label('🏷️ Role')
                                    ->formatStateUsing(fn($state) => match($state) {
                                        'supervisor' => '👨‍💼 Supervisor',
                                        'general_manager' => '🎯 General Manager',
                                        'admin_legal' => '⚖️ Legal Admin',
                                        'head_legal' => '👩‍⚖️ Head Legal',
                                        'head_finance' => '💰 Head Finance',
                                        'director' => '👔 Director',
                                        default => ucfirst(str_replace('_', ' ', $state))
                                    })
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('status')
                                    ->label('📋 Status')
                                    ->badge()
                                    ->color(fn($state) => match($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray'
                                    })
                                    ->formatStateUsing(fn($state) => match($state) {
                                        'pending' => '⏳ Pending',
                                        'approved' => '✅ Approved',
                                        'rejected' => '❌ Rejected',
                                        default => ucfirst($state)
                                    }),
                                    
                                Infolists\Components\TextEntry::make('approved_at')
                                    ->label('📅 Date')
                                    ->dateTime()
                                    ->since()
                                    ->placeholder('⏳ Pending'),
                                    
                                Infolists\Components\TextEntry::make('comments')
                                    ->label('💬 Comments')
                                    ->placeholder('No comments')
                                    ->limit(100)
                                    ->tooltip(fn($state) => $state),
                            ])
                            ->columns(5)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record) => $record->approvals()->count() > 0),
                
                // Enhanced Action Buttons
                Infolists\Components\Section::make('🎯 Approval Actions')
                    ->description('Review the document carefully and make your decision')
                    ->schema([
                        Infolists\Components\Actions::make([
                            Infolists\Components\Actions\Action::make('approve')
                                ->label('✅ Approve Document')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->size('lg')
                                ->form([
                                    Forms\Components\Textarea::make('approval_comments')
                                        ->label('💬 Approval Comments')
                                        ->rows(3)
                                        ->helperText('Optional: Add your comments for this approval'),
                                ])
                                ->action(function ($record, array $data) {
                                    try {
                                        $approval = $record->approvals()
                                            ->where('approver_nik', auth()->user()->nik)
                                            ->where('status', 'pending')
                                            ->first();

                                        if (!$approval) {
                                            $approval = static::createImplicitApproval($record, auth()->user());
                                            if (!$approval) {
                                                throw new \Exception('No pending approval found for your role');
                                            }
                                        }

                                        $success = app(DocumentRequestService::class)->processApproval(
                                            $approval,
                                            'approve',
                                            $data['approval_comments'] ?? null,
                                            auth()->user()
                                        );
                                        
                                        if ($success) {
                                            Notification::make()
                                                ->title('✅ Document approved successfully')
                                                ->body('The document has been approved and forwarded to the next step.')
                                                ->success()
                                                ->send();
                                                
                                            return redirect()->to(DocumentRequestResource::getUrl('index'));
                                        } else {
                                            throw new \Exception('Failed to process approval');
                                        }
                                    } catch (\Exception $e) {
                                        Notification::make()
                                            ->title('❌ Error approving document')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                })
                                ->requiresConfirmation()
                                ->modalHeading('✅ Approve Document')
                                ->modalDescription('Are you sure you want to approve this document request?'),

                            Infolists\Components\Actions\Action::make('reject')
                                ->label('❌ Reject Document')
                                ->icon('heroicon-o-x-circle')
                                ->color('danger')
                                ->size('lg')
                                ->form([
                                    Forms\Components\Textarea::make('rejection_reason')
                                        ->label('📝 Rejection Reason')
                                        ->required()
                                        ->rows(3)
                                        ->helperText('Please provide a clear reason for rejection'),
                                ])
                                ->action(function ($record, array $data) {
                                    try {
                                        $approval = $record->approvals()
                                            ->where('approver_nik', auth()->user()->nik)
                                            ->where('status', 'pending')
                                            ->first();

                                        if (!$approval) {
                                            $approval = static::createImplicitApproval($record, auth()->user());
                                            if (!$approval) {
                                                throw new \Exception('No pending approval found for your role');
                                            }
                                        }

                                        $success = app(DocumentRequestService::class)->processApproval(
                                            $approval,
                                            'reject',
                                            $data['rejection_reason'],
                                            auth()->user()
                                        );
                                        
                                        if ($success) {
                                            Notification::make()
                                                ->title('❌ Document rejected')
                                                ->body('The document has been rejected and returned to the requester.')
                                                ->success()
                                                ->send();
                                                
                                            return redirect()->to(DocumentRequestResource::getUrl('index'));
                                        } else {
                                            throw new \Exception('Failed to process rejection');
                                        }
                                    } catch (\Exception $e) {
                                        Notification::make()
                                            ->title('❌ Error rejecting document')
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                })
                                ->requiresConfirmation()
                                ->modalHeading('❌ Reject Document')
                                ->modalDescription('Are you sure you want to reject this document request?'),
                        ])->columnSpanFull(),
                    ])
                    ->visible(function ($record) {
                        return app(\App\Services\DocumentWorkflowService::class)
                            ->canUserApproveDocument($record, auth()->user());
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentRequests::route('/'),
            'create' => Pages\CreateDocumentRequest::route('/create'),
            'view' => Pages\ViewDocumentRequest::route('/{record}'),
            'edit' => Pages\EditDocumentRequest::route('/{record}/edit'),
             'discussion' => Pages\ViewDiscussion::route('/{record}/discussion'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            // Add relation managers if needed
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'title',
            'nomor_dokumen', 
            'nama',
            'divisi',
            'description'
        ];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->title;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Document Number' => $record->nomor_dokumen ?? 'Pending',
            'Requester' => $record->nama,
            'Status' => ucfirst(str_replace('_', ' ', $record->status)),
            'Division' => $record->divisi,
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['doctype']);
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Document Management';
    }

    public static function canCreate(): bool
    {
        return auth()->check();
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        
        // Only allow editing drafts by creator or admin
        return ($record->status === 'draft' && $record->nik === $user->nik) || 
               in_array($user->role ?? '', ['admin', 'super_admin']);
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        
        // Only allow deleting drafts by creator or admin
        return ($record->status === 'draft' && $record->nik === $user->nik) || 
               in_array($user->role ?? '', ['admin', 'super_admin']);
    }

    public static function canView($record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        
        // Admin dan super admin bisa lihat semua
        if (in_array($user->role ?? '', ['admin', 'super_admin'])) {
            return true;
        }
        
        // Document owner bisa lihat
        if ($record->nik === $user->nik) {
            return true;
        }
        
        // Approvers bisa lihat documents yang mereka approve
        if ($record->approvals()->where('approver_nik', $user->nik)->exists()) {
            return true;
        }
        
        // Legal team bisa lihat semua documents
        if (in_array($user->role ?? '', ['head_legal', 'reviewer_legal', 'admin_legal', 'legal_admin'])) {
            return true;
        }
        
        // General manager dan senior manager bisa lihat semua
        if (in_array($user->role ?? '', ['general_manager', 'senior_manager'])) {
            return true;
        }
        
        // Manager/supervisor bisa lihat documents dari divisi yang sama
        if (in_array($user->role ?? '', ['manager', 'supervisor', 'head']) && 
            $record->divisi === $user->divisi) {
            return true;
        }
        
        return false;
    }
}

// END OF DocumentRequestResource.php