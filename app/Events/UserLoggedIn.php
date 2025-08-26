<?php 
namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLoggedIn
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $apiUserData;

    public function __construct(User $user, ?array $apiUserData = null)
    {
        $this->user = $user;
        $this->apiUserData = $apiUserData;
    }
}
