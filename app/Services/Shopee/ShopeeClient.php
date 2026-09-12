<?php

namespace App\Services\Shopee;

use Illuminate\Support\Facades\Http;

/**
 * Klien Shopee Open Platform API v2.
 * Tanda tangan (sign) pakai HMAC-SHA256 sesuai spesifikasi Shopee.
 *
 * CATATAN: kerangka — baru bisa dites setelah SHOPEE_PARTNER_ID & PARTNER_KEY
 * diisi di .env dan toko melakukan authorize.
 */
class ShopeeClient
{
    private int $partnerId;
    private string $partnerKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->partnerId  = (int) config('shopee.partner_id');
        $this->partnerKey = (string) config('shopee.partner_key');
        $this->baseUrl = config('shopee.sandbox')
            ? config('shopee.base_url_sandbox')
            : config('shopee.base_url_live');
    }

    public function isConfigured(): bool
    {
        return $this->partnerId > 0 && $this->partnerKey !== '';
    }

    /** Tanda tangan untuk API publik (tanpa shop). */
    private function signPublic(string $path, int $ts): string
    {
        return hash_hmac('sha256', $this->partnerId . $path . $ts, $this->partnerKey);
    }

    /** Tanda tangan untuk API level shop (butuh access_token + shop_id). */
    private function signShop(string $path, int $ts, string $accessToken, int $shopId): string
    {
        $base = $this->partnerId . $path . $ts . $accessToken . $shopId;
        return hash_hmac('sha256', $base, $this->partnerKey);
    }

    /** URL untuk seller meng-authorize app kita ke tokonya. */
    public function authUrl(string $redirect): string
    {
        $path = '/api/v2/shop/auth_partner';
        $ts = time();
        $sign = $this->signPublic($path, $ts);
        return $this->baseUrl . $path . '?' . http_build_query([
            'partner_id' => $this->partnerId,
            'timestamp'  => $ts,
            'sign'       => $sign,
            'redirect'   => $redirect,
        ]);
    }

    /** Tukar authorization code → access_token + refresh_token. */
    public function getTokenByCode(string $code, int $shopId): array
    {
        $path = '/api/v2/auth/token/get';
        $ts = time();
        $sign = $this->signPublic($path, $ts);
        $res = Http::post($this->baseUrl . $path . '?' . http_build_query([
            'partner_id' => $this->partnerId,
            'timestamp'  => $ts,
            'sign'       => $sign,
        ]), [
            'code'       => $code,
            'shop_id'    => $shopId,
            'partner_id' => $this->partnerId,
        ]);
        return $res->json() ?? [];
    }

    /** Perpanjang access_token dengan refresh_token. */
    public function refreshToken(string $refreshToken, int $shopId): array
    {
        $path = '/api/v2/auth/access_token/get';
        $ts = time();
        $sign = $this->signPublic($path, $ts);
        $res = Http::post($this->baseUrl . $path . '?' . http_build_query([
            'partner_id' => $this->partnerId,
            'timestamp'  => $ts,
            'sign'       => $sign,
        ]), [
            'refresh_token' => $refreshToken,
            'shop_id'       => $shopId,
            'partner_id'    => $this->partnerId,
        ]);
        return $res->json() ?? [];
    }

    /** Update stok satu item di Shopee. */
    public function updateStock(int $shopId, string $accessToken, int $itemId, int $stock, ?int $modelId = null): array
    {
        $path = '/api/v2/product/update_stock';
        $ts = time();
        $sign = $this->signShop($path, $ts, $accessToken, $shopId);
        $stockInfo = ['stock_type' => 2, 'normal_stock' => $stock];
        $sellerStock = $modelId
            ? ['model_id' => $modelId, 'seller_stock' => [['stock' => $stock]]]
            : ['seller_stock' => [['stock' => $stock]]];
        $res = Http::post($this->baseUrl . $path . '?' . http_build_query([
            'partner_id'   => $this->partnerId,
            'timestamp'    => $ts,
            'access_token' => $accessToken,
            'shop_id'      => $shopId,
            'sign'         => $sign,
        ]), [
            'item_id'    => $itemId,
            'stock_list' => [$sellerStock],
        ]);
        return $res->json() ?? [];
    }

    /** Ambil detail order (untuk tahu item & qty yang dibeli). */
    public function getOrderDetail(int $shopId, string $accessToken, array $orderSnList): array
    {
        $path = '/api/v2/order/get_order_detail';
        $ts = time();
        $sign = $this->signShop($path, $ts, $accessToken, $shopId);
        $res = Http::get($this->baseUrl . $path, [
            'partner_id'    => $this->partnerId,
            'timestamp'     => $ts,
            'access_token'  => $accessToken,
            'shop_id'       => $shopId,
            'sign'          => $sign,
            'order_sn_list' => implode(',', $orderSnList),
        ]);
        return $res->json() ?? [];
    }
}
