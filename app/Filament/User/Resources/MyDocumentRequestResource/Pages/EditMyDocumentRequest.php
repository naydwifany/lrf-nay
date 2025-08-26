<?php
namespace App\Filament\User\Resources\MyDocumentRequestResource\Pages;

use App\Filament\User\Resources\MyDocumentRequestResource;
use App\Services\DocumentWorkflowService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditMyDocumentRequest extends EditRecord
{
    protected static string $resource = MyDocumentRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // FIXED: Remove wasClicked method and simplify form actions
    protected function getFormActions(): array
    {
        if (!$this->getRecord()->is_draft) {
            return [
                Action::make('back')
                    ->label('Back to List')
                    ->url($this->getResource()::getUrl('index'))
                    ->color('gray'),
            ];
        }

        return [
            // Save as Draft button
            Action::make('save')
                ->label('Save as Draft')
                ->action('save') // Use string action instead of submit
                ->keyBindings(['mod+s'])
                ->color('gray'),
            
            // Submit button
            Action::make('submit')
                ->label('Submit')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Submit Document Request')
                ->modalDescription('Are you sure you want to submit this document request? Once submitted, you cannot edit it.')
                ->action(function () {
                    // Save first, then submit
                    $this->save(shouldRedirect: false);
                    
                    $record = $this->getRecord();
                    try {
                        $record->update([
                            'is_draft' => false,
                            'status' => 'submitted',
                            'submitted_at' => now(),
                        ]);
                        
                        // Trigger workflow
                        if (class_exists(DocumentWorkflowService::class)) {
                            app(DocumentWorkflowService::class)->submitDocument($record);
                        }

                        Notification::make()
                            ->title('Document submitted successfully')
                            ->success()
                            ->send();
                            
                        $this->redirect($this->getRedirectUrl());
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error submitting document')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            
            // Cancel button
            Action::make('cancel')
                ->label('Cancel')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn() => $this->getRecord()->is_draft),
        ];
    }

    // FIXED: Override afterSave to handle notifications
    protected function afterSave(): void
    {
        Notification::make()
            ->title('Document updated successfully')
            ->success()
            ->send();
    }
}