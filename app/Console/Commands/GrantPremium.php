<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantPremium extends Command
{
    protected $signature = 'premium:grant {email} {days=30}';

    protected $description = 'Aktifkan/perpanjang Premium untuk user (manual, setelah transfer)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) {
            $this->error('User tidak ditemukan: ' . $this->argument('email'));
            return self::FAILURE;
        }

        $days = (int) $this->argument('days');
        // Perpanjang dari expiry aktif, atau dari sekarang
        $base = ($user->premium_until && $user->premium_until->isFuture())
            ? $user->premium_until
            : now();
        $user->premium_until = $base->copy()->addDays($days);
        $user->save();

        $this->info("✅ Premium aktif untuk {$user->email} ({$user->name}) sampai {$user->premium_until}");
        return self::SUCCESS;
    }
}
