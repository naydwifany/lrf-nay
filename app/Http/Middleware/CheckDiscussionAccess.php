<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\DocumentDiscussionService;

class CheckDiscussionAccess
{
    protected $discussionService;

    public function __construct(DocumentDiscussionService $discussionService)
    {
        $this->discussionService = $discussionService;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        if (!$user) {
            abort(401, 'Authentication required');
        }

        // Check if user can participate in discussions
        if (!$this->discussionService->canUserParticipate($user)) {
            abort(403, 'You are not authorized to access discussion attachments');
        }

        return $next($request);
    }
}
