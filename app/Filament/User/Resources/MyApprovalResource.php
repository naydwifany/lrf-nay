<?php
// app/Filament/User/Resources/MyApprovalResource.php - UPDATED WITH SMART LOGIC

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\MyApprovalResource\Pages;
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
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('Document Number')
                    ->searchable()
                    ->placeholder('Not assigned')
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(function ($record) {
                        return $record->title;
                    }),
                    
                Tables\Columns\TextColumn::make('nama')
                    ->label('Requester')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('divisi')
                    ->label('Division')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('doctype.document_name')
                    ->label('Document Type')
                    ->badge()
                    ->color('primary'),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending_supervisor',
                        'info' => 'pending_gm',
                        'primary' => ['pending_legal', 'pending_legal_admin'],
                        'gray' => 'submitted',
                    ])
                    ->formatStateUsing(fn($state) => match($state) {
                        'pending_supervisor' => 'Pending Supervisor',
                        'pending_gm' => 'Pending GM',
                        'pending_legal_admin' => 'Pending Legal',
                        'pending_legal' => 'Pending Legal',
                        default => str_replace('_', ' ', ucwords($state))
                    }),
                    
                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'success' => 'low',
                        'primary' => 'medium',
                        'warning' => 'high',
                        'danger' => 'urgent',
                    ]),
                    
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('days_pending')
                    ->label('Days Pending')
                    ->getStateUsing(fn($record) => $record->submitted_at ? now()->diffInDays($record->submitted_at) : 0)
                    ->badge()
                    ->color(fn($state) => $state > 7 ? 'danger' : ($state > 3 ? 'warning' : 'success')),
                    
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
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ]),
                    
                SelectFilter::make('status')
                    ->options([
                        'pending_supervisor' => 'Pending Supervisor',
                        'pending_gm' => 'Pending GM',
                        'pending_legal_admin' => 'Pending Legal',
                    ]),
                    
                SelectFilter::make('divisi')
                    ->label('Division'),
                    
                SelectFilter::make('tipe_dokumen')
                    ->relationship('doctype', 'document_name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
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
            'pending_legal_admin', 'pending_legal' => static::isLegalAdminLevel($user) ? 'admin_legal' : null,
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
                                    ->label('Document Number')
                                    ->placeholder('Not assigned')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('title')
                                    ->label('Document Title')
                                    ->weight(FontWeight::Medium),
                                Infolists\Components\TextEntry::make('doctype.document_name')
                                    ->label('Document Type')
                                    ->badge()
                                    ->color('info'),
                            ]),
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn($state) => match($state) {
                                        'pending_supervisor' => 'warning',
                                        'pending_gm' => 'info',
                                        'pending_legal', 'pending_legal_admin' => 'primary',
                                        default => 'gray'
                                    }),
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
                                    ->label('Division'),
                                Infolists\Components\TextEntry::make('dept')
                                    ->label('Department'),
                                Infolists\Components\TextEntry::make('direktorat')
                                    ->label('Directorate'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Business Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label('Document Description')
                            ->columnSpanFull()
                            ->prose()
                            ->markdown(),
                        Infolists\Components\TextEntry::make('data')
                            ->label('Business Justification')
                            ->columnSpanFull()
                            ->html()
                            ->prose(),
                    ]),

                // Document Attachments Section
                Infolists\Components\Section::make('📎 Document Attachments')
                    ->description('Click on any file to download and review')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('dokumen_utama')
                                    ->label('📄 Document Utama')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '❌ Not uploaded';
                                        $filename = basename($state);
                                        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                                        return "📁 {$filename} ({$extension})";
                                    })
                                    ->url(fn ($record) => $record->dokumen_utama ? asset('storage/' . $record->dokumen_utama) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'danger')
                                    ->weight(FontWeight::Medium),
                                
                                Infolists\Components\TextEntry::make('ktp_direktur')
                                    ->label('KTP Direktur')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        return "📁 {$filename}";
                                    })
                                    ->url(fn ($record) => $record->ktp_direktur ? asset('storage/' . $record->ktp_direktur) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray'),

                                Infolists\Components\TextEntry::make('akta_perubahan')
                                    ->label('🏢 Akta Perubahan')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        return "📁 {$filename}";
                                    })
                                    ->url(fn ($record) => $record->akta_perubahan ? asset('storage/' . $record->akta_perubahan) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray'),
                                
                                Infolists\Components\TextEntry::make('npwp')
                                    ->label('NPWP')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        return "📁 {$filename}";
                                    })
                                    ->url(fn ($record) => $record->npwp ? asset('storage/' . $record->npwp) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray'),
                                
                                Infolists\Components\TextEntry::make('surat_kuasa')
                                    ->label('Surat Kuasa')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        return "📁 {$filename}";
                                    })
                                    ->url(fn ($record) => $record->surat_kuasa ? asset('storage/' . $record->surat_kuasa) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray'),

                                Infolists\Components\TextEntry::make('nib')
                                    ->label('NIB')
                                    ->formatStateUsing(function($state) {
                                        if (!$state) return '➖ Not provided';
                                        $filename = basename($state);
                                        return "📁 {$filename}";
                                    })
                                    ->url(fn ($record) => $record->nib ? asset('storage/' . $record->nib) : null)
                                    ->openUrlInNewTab()
                                    ->color(fn($state) => $state ? 'success' : 'gray'),
                                
                                
                            ]),
                    ]),

                Infolists\Components\Section::make('⏱️ Timeline & Progress')
                    ->schema([
                        Infolists\Components\Grid::make(3)
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
                                Infolists\Components\TextEntry::make('days_waiting')
                                    ->label('⏰ Days Waiting')
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
                                    ->placeholder('⏳ Pending'),
                                Infolists\Components\TextEntry::make('comments')
                                    ->label('💬 Comments')
                                    ->placeholder('No comments')
                                    ->limit(50),
                            ])
                            ->columns(5),
                    ]),

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
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyApprovals::route('/'),
            'view' => Pages\ViewMyApproval::route('/{record}'),
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