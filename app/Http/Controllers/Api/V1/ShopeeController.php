<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ShopeeShop;
use App\Services\Shopee\ShopeeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Integrasi Shopee (kerangka). Aktif setelah SHOPEE_PARTNER_ID/KEY diisi di .env
 * dan toko melakukan authorize. Alur: authorize → callback (simpan token) →
 * webhook (order → kurangi stok) + push stok balik.
 */
class ShopeeController extends Controller
{
    /** Status koneksi Shopee untuk usaha ini + URL authorize. */
    public function status(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwner($business);
        $client = new ShopeeClient();
        $shop = ShopeeShop::where('business_id', $business->id)->first();

        return response()->json([
            'configured' => $client->isConfigured(), // apakah partner_id/key sudah diisi server
            'connected'  => (bool) $shop,
            'shop' => $shop ? ['shop_id' => $shop->shop_id, 'shop_name' => $shop->shop_name] : null,
            'authorize_url' => $client->isConfigured()
                ? $client->authUrl(rtrim(config('shopee.redirect_url'), '/') . '?b=' . $business->id)
                : null,
        ]);
    }

    /** Callback OAuth dari Shopee (publik). Shopee tambahkan ?code=&shop_id= ke redirect. */
    public function callback(Request $request)
    {
        $businessId = (int) $request->get('b');
        $code = (string) $request->get('code');
        $shopId = (int) $request->get('shop_id');

        if (!$businessId || !$code || !$shopId) {
            return response('Parameter tidak lengkap.', 400);
        }

        $client = new ShopeeClient();
        if (!$client->isConfigured()) {
            return response('Shopee belum dikonfigurasi di server.', 503);
        }

        $token = $client->getTokenByCode($code, $shopId);
        if (empty($token['access_token'])) {
            Log::warning('Shopee token exchange gagal', ['resp' => $token]);
            return response('Gagal menukar token Shopee.', 400);
        }

        ShopeeShop::updateOrCreate(
            ['business_id' => $businessId, 'shop_id' => $shopId],
            [
                'access_token'     => $token['access_token'],
                'refresh_token'    => $token['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds((int) ($token['expire_in'] ?? 0)),
            ]
        );

        return response('<h3>Toko Shopee berhasil terhubung ✅</h3><p>Silakan kembali ke aplikasi Delta POS.</p>')
            ->header('Content-Type', 'text/html');
    }

    /** Webhook push dari Shopee (publik): order baru/update status. */
    public function webhook(Request $request): JsonResponse
    {
        // Verifikasi tanda tangan: HMAC-SHA256(partner_key, url + raw_body)
        $partnerKey = (string) config('shopee.partner_key');
        $raw = $request->getContent();
        $url = $request->fullUrl();
        $expected = hash_hmac('sha256', $url . $raw, $partnerKey);
        $got = $request->header('Authorization', '');
        if ($partnerKey !== '' && !hash_equals($expected, $got)) {
            Log::warning('Shopee webhook signature mismatch');
            // tetap balas 200 agar Shopee tidak retry beruntun
            return response()->json(['ok' => false], 200);
        }

        $payload = $request->all();
        Log::info('Shopee webhook', ['code' => $payload['code'] ?? null]);

        // TODO (aktif setelah kredensial & sandbox siap):
        // - code 3 = order status push → ambil order detail → cocokkan SKU via ShopeeItemMap
        //   → kurangi stok produk POS (buat transaksi 'jual' bertanda shopee).
        // Struktur sudah siap; butuh token toko + uji di sandbox dulu.

        return response()->json(['ok' => true], 200);
    }
}
