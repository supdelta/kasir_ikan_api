<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function daily(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($business);

        $date = $request->get('date', today()->toDateString());

        $transactions = $business->transactions()
            ->with('product')
            ->whereDate('created_at', $date)
            ->get();

        $pemasukan = $transactions->whereIn('type', ['jual', 'kas_masuk'])->sum('total');
        $pengeluaran = $transactions->whereIn('type', ['beli', 'kas_keluar'])->sum('total');
        $penjualan = $transactions->where('type', 'jual')->sum('total');
        $piutangBaru = $transactions->where('type', 'jual')->where('payment_method', 'utang')->sum('total');

        // Top produk terlaris
        $topProducts = $transactions->where('type', 'jual')
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->map(fn($group) => [
                'product_name' => $group->first()->product?->name ?? 'Produk dihapus',
                'transaction_count' => $group->count(),
                'total_revenue' => $group->sum('total'),
            ])
            ->sortByDesc('total_revenue')
            ->values()
            ->take(5);

        // Breakdown per metode bayar
        $breakdown = [
            'tunai' => $transactions->where('type', 'jual')->where('payment_method', 'tunai')->sum('total'),
            'qris' => $transactions->where('type', 'jual')->where('payment_method', 'qris')->sum('total'),
            'utang' => $transactions->where('type', 'jual')->where('payment_method', 'utang')->sum('total'),
            'pembelian_stok' => $transactions->where('type', 'beli')->sum('total'),
            'kas_keluar' => $transactions->where('type', 'kas_keluar')->sum('total'),
        ];

        return response()->json([
            'date' => $date,
            'summary' => [
                'pemasukan' => $pemasukan,
                'pengeluaran' => $pengeluaran,
                'untung_bersih' => $pemasukan - $pengeluaran,
                'penjualan' => $penjualan,
                'piutang_baru' => $piutangBaru,
                'jumlah_transaksi' => $transactions->count(),
            ],
            'breakdown' => $breakdown,
            'top_products' => $topProducts,
        ]);
    }

    private function authorizeBusiness(Business $business): void
    {
        abort_if($business->user_id !== auth()->id(), 403, 'Akses ditolak.');
    }
}
