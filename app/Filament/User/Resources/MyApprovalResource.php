<?php
// app/Filament/User/Resources/MyApprovalResource.php - UPDATED WITH SMART LOGIC

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\MyApprovalResource\Pages;
use App\Filament\User\Resources\MyApprovalResource\Pages\PendingAgreementOverview;
use App\Models\DocumentRequest;
use App\Services\DocumentRequestService;
use App\Services\NotificationService;
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
use Filament\Support\Enums\FontWeight;

class MyApprovalResource extends Resource
{
    protected static ?string $model = DocumentRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Pending Approvals';
    protected static ?string $modelLabel = 'Approval Request';
    protected static ?string $pluralModelLabel = 'Approval Requests';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        
        \Log::info('MyApprovalResource Query Debug', [
            'user_nik' => $user->nik,
            'user_role' => $user->role,
            'user_jabatan' => $user->jabatan
        ]);
        
        return parent::getEloquentQuery()
            ->where(function ($query) use ($user) {
                // METHOD 1: Documents with explicit approval records for current user
                $query->whereHas('approvals', function ($q) use ($user) {
                    $q->where('approver_nik', $user->nik)
                      ->where('status', 'pending');
                });
                
                // METHOD 2: Documents where user should be approver based on workflow logic
                // This handles cases where approval records might not be created yet
                $query->orWhere(function ($q) use ($user) {
                    // SUPERVISOR/MANAGER APPROVALS
                    if (static::isSupervisorLevel($user)) {
                        $q->where('status', 'pending_supervisor')
                          ->where('nik_atasan', $user->nik);
                    }
                    
                    // GENERAL MANAGER APPROVALS
                    if (static::isGeneralManagerLevel($user)) {
                        $q->orWhere('status', 'pending_gm');
                    }
                    
                    // LEGAL ADMIN APPROVALS
                    if (static::isLegalAdminLevel($user)) {
                        $q->orWhere('status', 'pending_legal_admin')
                          ->orWhere('status', 'pending_legal');
                    }
                });
            })
            ->where('is_draft', false) // Only submitted documents
            ->whereIn('status', [
                'pending_supervisor',
                'pending_gm', 
                'pending_legal_admin',
                'pending_legal'
            ]);
    }

    /**
     * Check if user is supervisor level
     */
    protected static function isSupervisorLevel($user): bool
    {
        $role = strtolower($user->role ?? '');
        $jabatan = strtolower($user->jabatan ?? '');
        
        $supervisorRoles = ['supervisor', 'manager', 'head'];
        $supervisorJabatan = ['supervisor', 'manager', 'kepala', 'head'];
        
        foreach ($supervisorRoles as $roleCheck) {
            if (str_contains($role, $roleCheck)) {
                return true;
            }
        }
        
        foreach ($supervisorJabatan as $jabatanCheck) {
            if (str_contains($jabatan, $jabatanCheck)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user is General Manager level
     */
    protected static function isGeneralManagerLevel($user): bool
    {
        $role = strtolower($user->role ?? '');
        $jabatan = strtolower($user->jabatan ?? '');
        
        $gmRoles = ['general_manager', 'gm', 'director', 'senior_manager'];
        $gmJabatan = ['general manager', 'gm', 'direktur', 'director', 'senior manager'];
        
        foreach ($gmRoles as $roleCheck) {
            if (str_contains($role, $roleCheck)) {
                return true;
            }
        }
        
        foreach ($gmJabatan as $jabatanCheck) {
            if (str_contains($jabatan, $jabatanCheck)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user is Legal Admin level
     */
    protected static function isLegalAdminLevel($user): bool
    {
        $role = strtolower($user->role ?? '');
        $jabatan = strtolower($user->jabatan ?? '');
        
        $legalRoles = ['admin_legal', 'legal_admin', 'legal'];
        $legalJabatan = ['legal', 'hukum'];
        
        foreach ($legalRoles as $roleCheck) {
            if (str_contains($role, $roleCheck)) {
                return true;
            }
        }
        
        foreach ($legalJabatan as $jabatanCheck) {
            if (str_contains($jabatan, $jabatanCheck)) {
                return true;
            }
        }
        
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Diunggah')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('No. Dokumen')
                    ->searchable()
                    ->placeholder('Not assigned')
                    ->copyable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Nama Mitra')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(function ($record) {
                        return $record->title;
                    }),
                Tables\Columns\TextColumn::make('doctype.document_name')
                    ->label('Jenis Perjanjian')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('nama')
                    ->label('PIC')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('dept')
                    ->label('Departemen')
                    ->searchable(), 
                Tables\Columns\BadgeColumn::make('computed_status')
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
                
                /*
                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'success' => 'low',
                        'primary' => 'medium',
                        'warning' => 'high',
                        'danger' => 'urgent',
                    ]),
                */
                Tables\Columns\TextColumn::make('days_pending')
                    ->label('Days Pending')
                    ->getStateUsing(fn ($record) => 
                        $record->status === 'pending'
                            ? now()->diffInDays($record->created_at)
                            : 0
                    )
                    ->badge()
                    ->color(fn ($state) => $state > 7 ? 'danger' : ($state > 3 ? 'warning' : 'success')),

                // Show current approval step
                Tables\Columns\TextColumn::make('current_step')
                    ->label('Your Role')
                    ->getStateUsing(function ($record) {
                        $user = auth()->user();
                        
                        // Check explicit approval record first
                        $approval = $record->approvals()
                            ->where('approver_nik', $user->nik)
                            ->where('status', 'pending')
                            ->first();
                            
                        if ($approval) {
                            return match($approval->approval_type) {
                                'supervisor' => '👨‍💼 Supervisor',
                                'general_manager' => '🎯 General Manager',
                                'admin_legal' => '⚖️ Legal Admin',
                                default => ucfirst(str_replace('_', ' ', $approval->approval_type))
                            };
                        }
                        
                        // Fallback to status-based detection
                        return match($record->status) {
                            'pending_supervisor' => '👨‍💼 Supervisor',
                            'pending_gm' => '🎯 General Manager',
                            'pending_legal_admin', 'pending_legal' => '⚖️ Legal Admin',
                            default => '❓ Unknown'
                        };
                    })
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                /*
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ]),
                */

                SelectFilter::make('status')
                    ->options([
                        'pending_supervisor' => 'Pending Supervisor',
                        'pending_gm' => 'Pending GM',
                        'pending_legal_admin' => 'Pending Legal',
                    ])
                    ->native(false),
                    
                SelectFilter::make('dept')
                    ->label('Departemen')
                    ->native(false),
                    
                SelectFilter::make('tipe_dokumen')
                    ->label('Jenis Perjanjian')
                    ->relationship('doctype', 'document_name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View LRF'),
                Tables\Actions\Action::make('view_pending_ao')
                    ->label('View Pending AO')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => PendingAgreementOverview::getUrl(['record' => $record->id])),
                
                /* approve/reject move to infolist below
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Textarea::make('approval_comments')
                            ->label('Approval Comments')
                            ->rows(3)
                            ->helperText('Optional: Add your comments for this approval'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            // Get the pending approval for current user
                            $approval = $record->approvals()
                                ->where('approver_nik', auth()->user()->nik)
                                ->where('status', 'pending')
                                ->first();

                            if (!$approval) {
                                // If no explicit approval record, try to create one based on current status
                                $approval = static::createImplicitApproval($record, auth()->user());
                                
                                if (!$approval) {
                                    throw new \Exception('No pending approval found for your role');
                                }
                            }

                            // Use DocumentRequestService
                            $success = app(DocumentRequestService::class)->processApproval(
                                $approval,
                                'approve',
                                $data['approval_comments'] ?? null,
                                auth()->user()
                            );
                            
                            if ($success) {
                                Notification::make()
                                    ->title('Document approved successfully')
                                    ->body('The document has been approved and moved to the next step.')
                                    ->success()
                                    ->send();
                            } else {
                                throw new \Exception('Failed to process approval');
                            }
                        } catch (\Exception $e) {
                            \Log::error('Approval error', [
                                'document_id' => $record->id,
                                'user_nik' => auth()->user()->nik,
                                'error' => $e->getMessage()
                            ]);
                            
                            Notification::make()
                                ->title('Error approving document')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Document Request')
                    ->modalDescription('Are you sure you want to approve this document request?'),

                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3)
                            ->helperText('Please provide a clear reason for rejection'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            // Get the pending approval for current user
                            $approval = $record->approvals()
                                ->where('approver_nik', auth()->user()->nik)
                                ->where('status', 'pending')
                                ->first();

                            if (!$approval) {
                                // If no explicit approval record, try to create one
                                $approval = static::createImplicitApproval($record, auth()->user());
                                
                                if (!$approval) {
                                    throw new \Exception('No pending approval found for your role');
                                }
                            }

                            // Use DocumentRequestService
                            $success = app(DocumentRequestService::class)->processApproval(
                                $approval,
                                'reject',
                                $data['rejection_reason'],
                                auth()->user()
                            );
                            
                            if ($success) {
                                Notification::make()
                                    ->title('Document rejected')
                                    ->body('The document has been rejected and returned to the requester.')
                                    ->success()
                                    ->send();
                            } else {
                                throw new \Exception('Failed to process rejection');
                            }
                        } catch (\Exception $e) {
                            \Log::error('Rejection error', [
                                'document_id' => $record->id,
                                'user_nik' => auth()->user()->nik,
                                'error' => $e->getMessage()
                            ]);
                            
                            Notification::make()
                                ->title('Error rejecting document')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Reject Document Request')
                    ->modalDescription('Are you sure you want to reject this document request?'),
                */
            ])

            ->bulkActions([])
            ->defaultSort('submitted_at', 'asc')
            ->poll('30s')
            ->emptyStateHeading('No Pending Approvals')
            ->emptyStateDescription('You have no documents waiting for your approval.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    /**
     * Create implicit approval record if missing
     */
    protected static function createImplicitApproval($record, $user): ?\App\Models\DocumentApproval
    {
        try {
            $approvalType = static::getApprovalTypeForUser($record, $user);
            
            if (!$approvalType) {
                return null;
            }

            // Get the next order number
            $maxOrder = $record->approvals()->max('order') ?? 0;
            
            $approval = \App\Models\DocumentApproval::create([
                'document_request_id' => $record->id,
                'approver_nik' => $user->nik,
                'approver_name' => $user->name,
                'approval_type' => $approvalType,
                'status' => 'pending',
                'order' => $maxOrder + 1
            ]);

            \Log::info('Created implicit approval record', [
                'document_id' => $record->id,
                'approver_nik' => $user->nik,
                'approval_type' => $approvalType
            ]);

            return $approval;
            
        } catch (\Exception $e) {
            \Log::error('Failed to create implicit approval: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get approval type for user based on document status
     */
    protected static function getApprovalTypeForUser($record, $user): ?string
    {
        // Check based on document status and user role/position
        return match($record->status) {
            'pending_supervisor' => static::isSupervisorLevel($user) ? 'supervisor' : null,
            'pending_gm' => static::isGeneralManagerLevel($user) ? 'general_manager' : null,
            'pending_legal_admin', 'pending_legal' => static::isLegalAdminLevel($user) ? ['admin_legal', 'legal_admin'] : null,
            default => null
        };
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Document Overview')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('nomor_dokumen')
                                    ->label('Nomor Dokumen')
                                    ->placeholder('Not assigned')
                                    ->weight(FontWeight::Bold)
                                    ->copyable()
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('title')
                                    ->label('Nama Mitra')
                                    ->weight(FontWeight::Medium),
                                Infolists\Components\TextEntry::make('doctype.document_name')
                                    ->label('Jenis Perjanjian')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('computed_status')
                                    ->label('Status')
                                    ->badge()
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
                                    })
                                    ->getStateUsing(fn ($record) => $record->computed_status),
                                
                                /*
                                Infolists\Components\TextEntry::make('priority')
                                    ->label('Priority')
                                    ->badge()
                                    ->color(fn($state) => match($state) {
                                        'low' => 'success',
                                        'medium' => 'primary',
                                        'high' => 'warning',
                                        'urgent' => 'danger',
                                        default => 'gray'
                                    }),
                                */

                                Infolists\Components\TextEntry::make('approval_level')
                                    ->label('Your Role in Approval')
                                    ->getStateUsing(function ($record) {
                                        $user = auth()->user();
                                        return match(true) {
                                            static::isSupervisorLevel($user) => '👨‍💼 Supervisor Approval',
                                            static::isGeneralManagerLevel($user) => '🎯 GM Approval',
                                            static::isLegalAdminLevel($user) => '⚖️ Legal Review',
                                            default => '❓ Unknown Role'
                                        };
                                    })
                                    ->badge()
                                    ->color('warning'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Requester Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('nama')
                                    ->label('PIC')
                                    ->weight(FontWeight::Medium)
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('nik')
                                    ->label('NIK')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('jabatan')
                                    ->label('Position'),
                                Infolists\Components\TextEntry::make('divisi')
                                    ->label('Division'),
                                Infolists\Components\TextEntry::make('dept')
                                    ->label('Department'),
                                Infolists\Components\TextEntry::make('direktorat')
                                    ->label('Directorate'),
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
                                    ->label('📑 Document')
                                    ->formatStateUsing(fn($state) => match($state) {
                                        'review' => '🔍 Review',
                                        'create' => '✨ Create New',
                                        default => $state ?: 'Not specified'
                                    })
                                    ->badge(),
                            ]),
                        /*
                        Infolists\Components\TextEntry::make('description')
                            ->label('📝 Deskripsi Dokumen')
                            ->html()
                            ->columnSpanFull()
                            ->placeholder('Tidak ada deskripsi pada Document Request ini.'),

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
                                Infolists\Components\TextEntry::make('kewajiban_eci')
                                    ->label('📝 Kewajiban ECI')
                                    ->html()
                                    ->placeholder('Not specified'),
                                Infolists\Components\TextEntry::make('hak_mitra')
                                    ->label('✅ Hak Mitra')
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
                                    ->label('🏢 Akta Pendirian + SK')
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
                                    ->label('📋 Akta PT & SK Anggaran Dasar perubahan terakhir')
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
                                    ->label('🆔 KTP kuasa Direksi (bila penandatangan bukan Direksi)')
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
                                    ->label('✍️ Surat kuasa Direksi (bila penandatangan bukan Direksi)')
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

                Infolists\Components\Section::make('⏱️ Timeline & Progress')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('📅 Dibuat')
                                    ->dateTime()
                                    ->since(),
                                Infolists\Components\TextEntry::make('submitted_at')
                                    ->label('📤 Diunggah')
                                    ->dateTime()
                                    ->since()
                                    ->placeholder('Not submitted yet'),
                                Infolists\Components\TextEntry::make('days_waiting')
                                    ->label('⏰ Jumlah Hari selama Diproses')
                                    ->getStateUsing(fn($record) => $record->submitted_at ? now()->diffInDays($record->submitted_at) . ' days' : '0 days')
                                    ->badge()
                                    ->color(fn($state) => (int)filter_var($state, FILTER_SANITIZE_NUMBER_INT) > 7 ? 'danger' : 'success'),
                            ]),
                    ]),

                Infolists\Components\Section::make('📊 Approval History')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('approvals')
                            ->schema([
                                Infolists\Components\TextEntry::make('approver_name')
                                    ->label('👤 Approver')
                                    ->weight(FontWeight::Medium),
                                Infolists\Components\TextEntry::make('approval_type')
                                    ->label('🏷️ Role')
                                    ->formatStateUsing(fn($state) => match($state) {
                                        'supervisor' => '👨‍💼 Supervisor',
                                        'general_manager' => '🎯 General Manager',
                                        'admin_legal' => '⚖️ Legal Admin',
                                        'head_legal' => '👩‍⚖️ Head Legal',
                                        default => ucfirst(str_replace('_', ' ', $state))
                                    }),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('📋 Status')
                                    ->badge()
                                    ->color(fn($state) => match($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray'
                                    }),
                                Infolists\Components\TextEntry::make('approved_at')
                                    ->label('📅 Date')
                                    ->dateTime()
                                    ->since()
                                    ->formatStateUsing(fn ($state) => $state ? $state->diffForHumans() : '⏳ Pending'),
                                Infolists\Components\TextEntry::make('comments')
                                    ->label('💬 Comments')
                                    ->limit(50)
                                    ->formatStateUsing(fn ($state) => $state ?: 'No comments')
                                    ->tooltip(fn($state) => $state),
                            ])
                            ->columns(5),
                    ]),

                // Enhanced Action Buttons
                Infolists\Components\Section::make('🎯 Approval Actions')
                    ->description('Review the document carefully and make your decision')
                    ->schema([
                        Infolists\Components\Actions::make([
                            Infolists\Components\Actions\Action::make('approve')
                                ->label('Approve Document')
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
                                                
                                            return redirect()->to(MyApprovalResource::getUrl('index'));
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
                                ->label('Reject Document')
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
                                                
                                            return redirect()->to(MyApprovalResource::getUrl('index'));
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
            'index'     => Pages\ListMyApprovals::route('/'),
            'view'      => Pages\ViewMyApproval::route('/{record}'),
            'view_ao'   => Pages\PendingAgreementOverview::route('/{record}/ao')
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}