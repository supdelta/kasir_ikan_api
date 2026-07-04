<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature = 'admin:make {email} {--revoke}';

    protected $description = 'Jadikan (atau cabut) user sebagai Super Admin developer';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) {
            $this->error('User tidak ditemukan: ' . $this->argument('email'));
            return self::FAILURE;
        }

        $user->is_super_admin = !$this->option('revoke');
        $user->save();

        $status = $user->is_super_admin ? 'DIJADIKAN' : 'DICABUT dari';
        $this->info("✅ {$user->email} ({$user->name}) $status Super Admin.");
        return self::SUCCESS;
    }
}
