<?php
// app/Filament/User/Resources/PendingAgreementOverviewResource.php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\PendingAgreementOverviewResource\Pages;
use App\Models\AgreementOverview;
use App\Services\DocumentWorkflowService;
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

class PendingAgreementOverviewResource extends Resource
{
    protected static ?string $model = AgreementOverview::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Pending Agreement Overviews';
    protected static ?string $modelLabel = 'Pending Agreement Overview';
    protected static ?string $pluralModelLabel = 'Pending Agreement Overviews';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationGroup = 'Approvals';

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        
        return parent::getEloquentQuery()
            ->where('is_draft', false)
            ->where(function ($query) use ($user) {
                // Show AOs that need approval from current user based on their role and current status
                if (in_array($user->role, ['head', 'manager', 'senior_manager'])) {
                    $query->where('status', AgreementOverview::STATUS_PENDING_HEAD);
                } elseif (in_array($user->role, ['general_manager', 'senior_manager'])) {
                    $query->where('status', AgreementOverview::STATUS_PENDING_GM);
                } elseif ($user->role === 'finance') {
                    $query->where('status', AgreementOverview::STATUS_PENDING_FINANCE);
                } elseif ($user->role === 'legal_admin') {
                    $query->where('status', AgreementOverview::STATUS_PENDING_LEGAL);
                } elseif ($user->role === 'director') {
                    // For directors, they can approve in two stages
                    $query->where(function ($subQuery) use ($user) {
                        // Director 1: The selected director for this AO
                        $subQuery->where('status', AgreementOverview::STATUS_PENDING_DIRECTOR1)
                                ->where('nik_direksi', $user->nik);
                    })
                    ->orWhere(function ($subQuery) use ($user) {
                        // Director 2: Any other director (not the selected one)
                        $subQuery->where('status', AgreementOverview::STATUS_PENDING_DIRECTOR2)
                                ->where('nik_direksi', '!=', $user->nik);
                    });
                } else {
                    // If role doesn't match any approval role, show nothing
                    $query->whereRaw('1 = 0');
                }
            })
            ->with(['lrfDocument', 'selectedDirector', 'creator'])
            ->latest('submitted_at');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Approval Decision')
                    ->schema([
                        Forms\Components\Radio::make('approval_decision')
                            ->label('Decision')
                            ->options(function () {
                                $user = auth()->user();

                                // Default opsi untuk semua role
                                $options = [
                                    'approve' => 'Approve',
                                    'reject' => 'Reject',
                                ];

                                // Kalau role director1 atau director2, tambahkan opsi re-discussion
                                if (in_array($user->role, ['director1', 'director2'])) {
                                    $options['rediscuss'] = 'Send to Re-discussion';
                                }

                                return $options;
                            })
                            ->required()
                            ->reactive()
                            ->default('approve'),
                        
                        Forms\Components\Textarea::make('approval_comments')
                            ->label('Comments')
                            ->rows(3)
                            ->required(fn (callable $get) => in_array($get('approval_decision'), ['reject', 'rediscuss']))
                            ->placeholder('Please provide your feedback or reason for this decision...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('AO Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('lrfDocument.title')
                    ->label('Source Document')
                    ->limit(40)
                    ->searchable()
                    ->tooltip(fn ($record) => $record->lrfDocument?->title),
                
                Tables\Columns\TextColumn::make('counterparty')
                    ->label('Counterparty')
                    ->searchable()
                    ->limit(30),
                
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Requested By')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('creator.divisi')
                    ->label('Division')
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Current Status')
                    ->badge()
                    ->color(fn (string $state): string => AgreementOverview::getStatusColors()[$state] ?? 'gray')
                    ->formatStateUsing(fn (string $state): string => 
                        AgreementOverview::getStatusOptions()[$state] ?? $state
                    ),
                
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($record) => $record->submitted_at?->format('d M Y H:i:s')),
                
                Tables\Columns\TextColumn::make('priority_indicator')
                    ->label('Priority')
                    ->getStateUsing(function ($record) {
                        $daysSinceSubmission = $record->submitted_at?->diffInDays(now()) ?? 0;
                        if ($daysSinceSubmission > 3) return 'High';
                        if ($daysSinceSubmission > 1) return 'Medium';
                        return 'Normal';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'High' => 'danger',
                        'Medium' => 'warning',
                        'Normal' => 'success',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('selectedDirector.name')
                    ->label('Selected Director')
                    ->placeholder('Not selected')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(function () {
                        $user = auth()->user();
                        $relevantStatuses = [];
                        
                        if ($user->role === 'head') {
                            $relevantStatuses[AgreementOverview::STATUS_PENDING_HEAD] = 'Pending Head';
                        }
                        if ($user->role === 'general_manager') {
                            $relevantStatuses[AgreementOverview::STATUS_PENDING_GM] = 'Pending GM';
                        }
                        if ($user->role === 'finance') {
                            $relevantStatuses[AgreementOverview::STATUS_PENDING_FINANCE] = 'Pending Finance';
                        }
                        if ($user->role === 'legal_admin') {
                            $relevantStatuses[AgreementOverview::STATUS_PENDING_LEGAL] = 'Pending Legal';
                        }
                        if ($user->role === 'director') {
                            $relevantStatuses[AgreementOverview::STATUS_PENDING_DIRECTOR1] = 'Pending Director 1';
                            $relevantStatuses[AgreementOverview::STATUS_PENDING_DIRECTOR2] = 'Pending Director 2';
                        }
                        
                        return $relevantStatuses;
                    }),
                
                SelectFilter::make('priority')
                    ->options([
                        'high' => 'High Priority (>3 days)',
                        'medium' => 'Medium Priority (1-3 days)',
                        'normal' => 'Normal Priority (<1 day)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function ($query, $priority) {
                            switch ($priority) {
                                case 'high':
                                    return $query->where('submitted_at', '<=', now()->subDays(3));
                                case 'medium':
                                    return $query->whereBetween('submitted_at', [now()->subDays(3), now()->subDay()]);
                                case 'normal':
                                    return $query->where('submitted_at', '>=', now()->subDay());
                            }
                        });
                    }),
                
                SelectFilter::make('divisi')
                    ->label('Division')
                    ->options(function () {
                        return AgreementOverview::join('users', 'agreement_overviews.nik', '=', 'users.nik')
                            ->whereNotNull('users.divisi')
                            ->distinct()
                            ->pluck('users.divisi', 'users.divisi')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function ($query, $divisi) {
                            return $query->whereHas('creator', function ($q) use ($divisi) {
                                $q->where('divisi', $divisi);
                            });
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->slideOver(),
                
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Agreement Overview')
                    ->modalDescription('Are you sure you want to approve this agreement overview?')
                    ->modalSubmitActionLabel('Approve')
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

                Tables\Actions\Action::make('quick_approve')
                    ->label('Quick Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Quick Approve Agreement Overview')
                    ->modalDescription('Are you sure you want to approve this agreement overview?')
                    ->modalSubmitActionLabel('Approve')
                    ->visible(fn (AgreementOverview $record) =>
                        app(DocumentWorkflowService::class)
                            ->canUserApproveAgreementOverview(auth()->user(), $record)
                    )
                    ->action(function (AgreementOverview $record) {
                        $workflowService = app(DocumentWorkflowService::class);
                        
                        if (!$workflowService->canUserApproveAgreementOverview(auth()->user(), $record)) {
                            Notification::make()
                                ->title('Permission Denied')
                                ->body('You do not have permission to approve this agreement overview.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        $workflowService->approveAgreementOverview($record, auth()->user(), 'Quick approved');
                        
                        Notification::make()
                            ->title('Agreement Overview Approved')
                            ->body('The agreement overview has been successfully approved.')
                            ->success()
                            ->send();
                    }),
                    
                /*
                Tables\Actions\Action::make('detailed_review')
                    ->label('Detailed Review')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('info')
                    ->form([
                        Forms\Components\Radio::make('approval_decision')
                            ->label('Decision')
                            ->options(function () {
                                $user = auth()->user();

                                // Default opsi untuk semua role
                                $options = [
                                    'approve' => 'Approve',
                                    'reject' => 'Reject',
                                ];

                                // Kalau role director1 atau director2, tambahkan opsi re-discussion
                                if (in_array($user->role, ['director1', 'director2'])) {
                                    $options['rediscuss'] = 'Send to Re-discussion';
                                }

                                return $options;
                            })
                            ->required()
                            ->reactive()
                            ->default('approve'),
                        
                        Forms\Components\Textarea::make('approval_comments')
                            ->label('Comments')
                            ->rows(4)
                            ->required(fn (callable $get) => in_array($get('approval_decision'), ['reject', 'rediscuss']))
                            ->placeholder('Please provide detailed feedback...'),
                    ])
                    ->action(function (AgreementOverview $record, array $data) {
                        $workflowService = app(DocumentWorkflowService::class);
                        
                        if (!$workflowService->canUserApproveAgreementOverview(auth()->user(), $record)) {
                            Notification::make()
                                ->title('Permission Denied')
                                ->body('You do not have permission to review this agreement overview.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        switch ($data['approval_decision']) {
                            case 'approve':
                                $workflowService->approveAgreementOverview($record, auth()->user(), $data['approval_comments']);
                                $message = 'Agreement overview approved successfully.';
                                break;
                            case 'reject':
                                $workflowService->rejectAgreementOverview($record, auth()->user(), $data['approval_comments']);
                                $message = 'Agreement overview rejected.';
                                break;
                            case 'rediscuss':
                                $workflowService->sendAgreementOverviewToRediscussion($record, auth()->user(), $data['approval_comments']);
                                $message = 'Agreement overview sent to re-discussion.';
                                break;
                        }
                        
                        Notification::make()
                            ->title('Review Completed')
                            ->body($message)
                            ->success()
                            ->send();
                    }),
                    */
            ])  

            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_approve')
                    ->label('Bulk Approve Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Bulk Approve Agreement Overviews')
                    ->modalDescription('Are you sure you want to approve all selected agreement overviews?')
                    ->action(function ($records) {
                        $workflowService = app(DocumentWorkflowService::class);
                        $approved = 0;
                        
                        foreach ($records as $record) {
                            if ($workflowService->canUserApproveAgreementOverview(auth()->user(), $record)) {
                                $workflowService->approveAgreementOverview($record, auth()->user(), 'Bulk approved');
                                $approved++;
                            }
                        }
                        
                        Notification::make()
                            ->title('Bulk Approval Completed')
                            ->body("Successfully approved {$approved} agreement overview(s).")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->poll('30s') // Auto-refresh every 30 seconds
            ->emptyStateHeading('No Pending Agreement Overviews')
            ->emptyStateDescription('There are no agreement overviews waiting for your approval.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Agreement Overview Details')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('nomor_dokumen')
                                    ->label('AO Number')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => AgreementOverview::getStatusColors()[$state] ?? 'gray'),
                                Infolists\Components\TextEntry::make('tanggal_ao')
                                    ->label('AO Date')
                                    ->date(),
                            ]),
                        
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('counterparty')
                                    ->label('Counterparty'),
                                Infolists\Components\TextEntry::make('pic')
                                    ->label('PIC'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Source Document')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('lrfDocument.title')
                                    ->label('Document Title'),
                                Infolists\Components\TextEntry::make('lrfDocument.doctype.document_name')
                                    ->label('Document Type')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('lrfDocument.nomor_dokumen')
                                    ->label('Document Number'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Requester Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('creator.name')
                                    ->label('Requested By'),
                                Infolists\Components\TextEntry::make('creator.jabatan')
                                    ->label('Position'),
                                Infolists\Components\TextEntry::make('creator.divisi')
                                    ->label('Division')
                                    ->badge(),
                            ]),
                    ]),

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
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $count = static::getEloquentQuery()->count();
        return $count > 0 ? 'warning' : 'gray';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPendingAgreementOverviews::route('/'),
            'view' => Pages\ViewPendingAgreementOverview::route('/{record}'),
        ];
    }
}