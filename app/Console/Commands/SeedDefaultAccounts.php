<?php

namespace App\Console\Commands;

use App\Models\Business;
use Illuminate\Console\Command;

class SeedDefaultAccounts extends Command
{
    protected $signature = 'accounts:seed-defaults';
    protected $description = 'Seed akun kas default untuk semua bisnis yang belum punya akun';

    public function handle(): void
    {
        $businesses = Business::all();
        $seeded = 0;

        foreach ($businesses as $business) {
            if (!$business->accounts()->exists()) {
                $business->seedDefaultAccounts();
                $seeded++;
                $this->line("✓ {$business->name} (ID: {$business->id})");
            }
        }

        $this->info("Selesai. $seeded bisnis di-seed, " . ($businesses->count() - $seeded) . " sudah punya akun.");
    }
}
