<?php
namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Services\DivisionApprovalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateDivisionApprovalOnLogin implements ShouldQueue
{
    use InteractsWithQueue;

    protected $divisionApprovalService;

    public function __construct(DivisionApprovalService $divisionApprovalService)
    {
        $this->divisionApprovalService = $divisionApprovalService;
    }

    /**
     * Handle the event.
     */
    public function handle(UserLoggedIn $event): void
    {
        $user = $event->user;
        $apiUserData = $event->apiUserData ?? null;

        // Update division approval in background
        $this->divisionApprovalService->updateDivisionApprovalOnLogin($user, $apiUserData);
    }
}