<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SubscriptionRemind extends Command
{
    protected $signature = 'subscription:remind {--days=3}';

    protected $description = 'Kirim reminder (in-app + email) ke user yang premiumnya akan berakhir';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $now = now();
        $limit = $now->copy()->addDays($days);

        $users = User::whereBetween('premium_until', [$now, $limit])->get();
        $sent = 0;

        foreach ($users as $user) {
            // Dedupe: sudah dikirim reminder dalam 24 jam terakhir?
            $already = AppNotification::where('user_id', $user->id)
                ->where('type', 'subscription')
                ->where('created_at', '>=', $now->copy()->subDay())
                ->exists();
            if ($already) {
                continue;
            }

            $tanggal = $user->premium_until->format('d M Y');
            $title = 'Langganan akan berakhir';
            $body = "Premium kamu berakhir pada $tanggal. Segera lakukan pembayaran agar fitur premium tetap aktif.";

            AppNotification::create([
                'user_id' => $user->id,
                'type' => 'subscription',
                'title' => $title,
                'body' => $body,
            ]);

            if ($user->email) {
                try {
                    Mail::raw("$body\n\n— Kasir Ikan", function ($m) use ($user, $title) {
                        $m->to($user->email)->subject("$title - Kasir Ikan");
                    });
                } catch (\Exception $e) {
                    // email gagal tidak menghentikan proses
                }
            }
            $sent++;
        }

        $this->info("✅ Reminder terkirim ke $sent user (premium habis dalam ≤ $days hari).");
        return self::SUCCESS;
    }
}
