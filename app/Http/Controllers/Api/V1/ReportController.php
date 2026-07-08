<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function daily(Request $request, Business $business): JsonResponse
    {
        $m = $this->authorizeMember($business);
        abort_if(!$m->isOwner() && !$m->can_view_reports, 403, 'Kamu tidak punya akses laporan.');

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

        // HPP = harga beli × qty untuk setiap transaksi jual (cost of goods sold)
        $hpp = $transactions->where('type', 'jual')
            ->filter(fn($t) => $t->buy_price_snapshot && $t->quantity_kg)
            ->sum(fn($t) => (int) round((float) $t->quantity_kg * $t->buy_price_snapshot));

        // Breakdown per metode bayar
        $breakdown = [
            'tunai' => $transactions->where('type', 'jual')->where('payment_method', 'tunai')->sum('total'),
            'qris' => $transactions->where('type', 'jual')->where('payment_method', 'qris')->sum('total'),
            'utang' => $transactions->where('type', 'jual')->where('payment_method', 'utang')->sum('total'),
            'pembelian_stok' => $transactions->where('type', 'beli')->sum('total'),
            'kas_keluar' => $transactions->where('type', 'kas_keluar')->sum('total'),
            'hpp' => $hpp,
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

    public function export(Request $request, Business $business): JsonResponse
    {
        $m = $this->authorizeMember($business);
        abort_if(!$m->isOwner() && !$m->can_view_reports, 403, 'Kamu tidak punya akses laporan.');

        $year  = (int) $request->get('year',  date('Y'));
        $month = (int) $request->get('month', date('n'));

        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to   = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        $transactions = $business->transactions()
            ->with('product')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $products = $business->products()->orderBy('name')->get();

        return response()->json([
            'year'          => $year,
            'month'         => $month,
            'business_name' => $business->name,
            'transactions'  => $transactions->map(fn($t) => [
                'date'          => $t->created_at->format('d/m/Y'),
                'type'          => $t->type,
                'payment_method'=> $t->payment_method,
                'customer_name' => $t->customer_name,
                'product_name'  => $t->product?->name,
                'quantity_kg'   => $t->quantity_kg,
                'unit_price'    => $t->unit_price,
                'total'         => $t->total,
                'note'          => $t->note,
            ]),
            'products' => $products->map(fn($p) => [
                'name'     => $p->name,
                'stock_kg' => $p->stock_kg,
                'category' => $p->category,
            ]),
        ]);
    }
}
