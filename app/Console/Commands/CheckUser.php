<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CheckUser extends Command
{
    protected $signature = 'user:check {nik}';
    protected $description = 'Check user details by NIK';

    public function handle()
    {
        $nik = $this->argument('nik');
        $user = User::where('nik', $nik)->first();
        
        if ($user) {
            $this->info("User found:");
            $this->line("NIK: " . $user->nik);
            $this->line("Name: " . $user->name);
            $this->line("Email: " . $user->email);
            $this->line("Role: " . $user->role);
            $this->line("Active: " . ($user->is_active ? 'Yes' : 'No'));
            $this->line("Legal: " . ($user->isLegal() ? 'Yes' : 'No'));
            $this->line("Can Access Admin Panel: " . ($user->canAccessPanel(app('filament')->getPanel('admin')) ? 'Yes' : 'No'));
        } else {
            $this->error("User with NIK {$nik} not found");
        }
    }
}
