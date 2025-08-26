<?php
// app/Filament/User/Widgets/PendingApprovalWidget.php - FIXED

namespace App\Filament\User\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\DocumentRequest;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class PendingApprovalWidget extends BaseWidget
{
    protected static ?string $heading = 'Documents Requiring My Approval';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('Doc Number')
                    ->searchable()
                    ->placeholder('Not assigned')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('title')
                    ->limit(40)
                    ->searchable()
                    ->tooltip(function ($record) {
                        return $record->title;
                    }),
                Tables\Columns\TextColumn::make('nama')
                    ->label('Requester')
                    ->searchable(),
                Tables\Columns\TextColumn::make('doctype.document_name')
                    ->label('Type')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending_supervisor',
                        'info' => 'pending_gm', 
                        'primary' => 'pending_legal',
                        'gray' => 'submitted',
                    ]),
                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'success' => 'low',
                        'primary' => 'medium',
                        'warning' => 'high',
                        'danger' => 'urgent',
                    ]),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->since()
                    ->label('Submitted')
                    ->sortable(),
                Tables\Columns\TextColumn::make('days_pending')
                    ->label('Days')
                    ->getStateUsing(fn($record) => $record->submitted_at ? now()->diffInDays($record->submitted_at) : 0)
                    ->badge()
                    ->color(fn($state) => $state > 7 ? 'danger' : ($state > 3 ? 'warning' : 'success')),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Document')
                    ->modalDescription('Are you sure you want to approve this document?')
                    ->action(function ($record) {
                        try {
                            app(\App\Services\DocumentWorkflowService::class)->approve(
                                $record, 
                                auth()->user()
                            );
                            
                            Notification::make()
                                ->title('Document approved successfully')
                                ->success()
                                ->send();
                                
                            // Refresh the widget
                            $this->dispatch('$refresh');
                            
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error approving document')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Reject Document')
                    ->modalDescription('Please provide a reason for rejection.')
                    ->action(function ($record, array $data) {
                        try {
                            app(\App\Services\DocumentWorkflowService::class)->rejectDocument(
                                $record, 
                                auth()->user(), 
                                $data['rejection_reason']
                            );
                            
                            Notification::make()
                                ->title('Document rejected')
                                ->success()
                                ->send();
                                
                            // Refresh the widget
                            $this->dispatch('$refresh');
                            
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error rejecting document')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => \App\Filament\User\Resources\MyApprovalResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_supervisor' => 'Pending Supervisor',
                        'pending_gm' => 'Pending GM',
                        'pending_legal' => 'Pending Legal',
                    ]),
            ])
            ->defaultSort('submitted_at', 'asc') // Oldest first for approvals
            ->poll('30s') // Auto refresh every 30 seconds
            ->emptyStateHeading('No pending approvals')
            ->emptyStateDescription('You have no documents waiting for your approval.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        
        return DocumentRequest::query()
            ->with(['doctype', 'approvals'])
            ->where(function ($query) use ($user) {
                // Documents that need current user's approval
                $query->whereHas('approvals', function ($q) use ($user) {
                    $q->where('approver_nik', $user->nik)
                      ->where('status', 'pending');
                })
                // OR documents where user is next approver based on status
                ->orWhere(function ($q) use ($user) {
                    // Supervisor/Manager approval
                    if ($user->isSupervisor()) {
                        $q->where('status', 'pending_supervisor')
                          ->where('nik_atasan', $user->nik);
                    }
                    
                    // General Manager approval
                    if ($user->isGeneralManager()) {
                        $q->orWhere('status', 'pending_gm');
                    }
                    
                    // Legal team approval
                    if ($user->isLegal()) {
                        $q->orWhere('status', 'pending_legal');
                    }
                    
                    // Finance approval (if applicable)
                    if ($user->role === 'finance' || $user->role === 'head_finance') {
                        $q->orWhere('status', 'pending_finance');
                    }
                });
            })
            ->whereNotNull('submitted_at') // Only submitted documents
            ->limit(10); // Show more items in widget
    }

    // Helper method to check if user is supervisor
    protected function userIsSupervisor(): bool
    {
        $user = auth()->user();
        return method_exists($user, 'isSupervisor') ? $user->isSupervisor() : false;
    }

    // Helper method to check if user is general manager
    protected function userIsGeneralManager(): bool
    {
        $user = auth()->user();
        return method_exists($user, 'isGeneralManager') ? $user->isGeneralManager() : 
               in_array($user->role ?? '', ['general_manager', 'gm', 'director']);
    }

    // Helper method to check if user is legal
    protected function userIsLegal(): bool
    {
        $user = auth()->user();
        return method_exists($user, 'isLegal') ? $user->isLegal() : 
               in_array($user->role ?? '', ['admin_legal', 'reviewer_legal', 'head_legal', 'legal']);
    }

    // Get widget header actions
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('view_all')
                ->label('View All Approvals')
                ->icon('heroicon-o-queue-list')
                ->url(\App\Filament\User\Resources\MyApprovalResource::getUrl('index'))
                ->button()
                ->color('primary'),
        ];
    }

    // Customize widget polling
    protected function getPollingInterval(): ?string
    {
        return '30s';
    }
}