# Deploy Kasir Ikan ke VPS (Ubuntu)

Hasil akhir: API jalan di `https://DOMAIN-KAMU/api/v1`, HP bisa dipakai di mana saja
tanpa laptop.

---

## Langkah 1 — Arahkan domain ke VPS

Di panel domain kamu, buat **A record**:

```
Type: A    Name: api    Value: <IP_PUBLIK_VPS>
```

Jadi misal domain `tokoku.com` → API-nya `api.tokoku.com`. Tunggu 5–30 menit
sampai `ping api.tokoku.com` menunjuk ke IP VPS.

---

## Langkah 2 — Kirim kode ke VPS

Pilih **salah satu**:

### Opsi A — Lewat GitHub (disarankan)

Di laptop (folder `c:\kasir_ikan_api`):

```powershell
git init
git add .
git commit -m "Kasir Ikan API"
git branch -M main
git remote add origin https://github.com/USERNAME/kasir_ikan_api.git
git push -u origin main
```

Lalu di `setup-vps.sh` isi `REPO_URL` dengan URL repo tsb.

### Opsi B — Upload langsung (tanpa GitHub)

Di laptop:

```powershell
scp -r c:\kasir_ikan_api root@IP_VPS:/var/www/kasir_ikan_api
```

Lalu di `setup-vps.sh` biarkan `REPO_URL=""` (kosong).

---

## Langkah 3 — Jalankan setup di VPS

SSH ke VPS, lalu:

```bash
cd /var/www/kasir_ikan_api/deploy    # atau lokasi setup-vps.sh
nano setup-vps.sh                    # EDIT: DOMAIN, DB_PASS, REPO_URL
sudo bash setup-vps.sh
```

Script otomatis: install PHP/MySQL/Nginx → buat database → composer install →
migrate → pasang HTTPS. Selesai dalam ~5 menit.

---

## Langkah 4 — Arahkan aplikasi Flutter ke domain

Edit `c:\ikanapps\kasir_ikan\lib\core\network\api_client.dart`:

```dart
const _baseUrl = 'https://api.tokoku.com/api/v1';   // ganti domain kamu
```

Lalu build APK baru:

```powershell
cd c:\ikanapps\kasir_ikan
flutter build apk --release
```

APK ada di: `build\app\outputs\flutter-apk\app-release.apk` → install ke HP.

Setelah ini HP kamu **jalan di mana saja** (data seluler / WiFi apa pun),
laptop tidak perlu nyala.

---

## Update kode nanti (kalau ada perubahan backend)

Di VPS:

```bash
cd /var/www/kasir_ikan_api
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
sudo systemctl reload php8.2-fpm
```

---

## Troubleshoot cepat

| Gejala | Cek |
|---|---|
| App "gagal koneksi" | `curl https://DOMAIN/api/v1/...` dari mana pun; cek A record domain |
| 502 Bad Gateway | `sudo systemctl status php8.2-fpm` lalu `sudo systemctl restart php8.2-fpm` |
| Certbot gagal | Domain belum mengarah ke IP VPS — ulang: `sudo certbot --nginx -d DOMAIN` |
| 500 error | `tail -f storage/logs/laravel.log`; pastikan `APP_KEY` terisi & folder `storage` writable |
