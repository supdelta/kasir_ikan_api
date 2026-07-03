# Deploy Ulang Kasir Ikan — CARA AMAN (server sudah ada MySQL & app lain)

> Dipakai SETELAH restore VPS. Server sudah punya MySQL 8.0, PHP, Nginx, dan
> app DeltaSoft (fwdapi, landingpage, phpmyadmin). Prinsip: **JANGAN sentuh
> DB server, JANGAN install MariaDB, JANGAN hapus config nginx lain.**

## ATURAN EMAS
- ❌ JANGAN `apt install mariadb-server` / `mysql-server`
- ❌ JANGAN `rm` apa pun di `/etc/nginx/sites-enabled/`
- ❌ JANGAN hapus/ubah database selain `kasir_ikan`
- ✅ Semua langkah dijalankan SATU per SATU, cek hasilnya

---

## Langkah 0 — Pastikan server sudah pulih (WAJIB cek dulu)

```bash
# DB harus MySQL 8.0 lagi, dan DB DeltaSoft muncul
sudo mysql -e "SELECT VERSION(); SHOW DATABASES;"

# app DeltaSoft harus hidup — buka di browser: php.deltasoft.id dll
systemctl is-active nginx

# cek versi PHP-FPM yg terpasang (buat config nginx nanti)
ls /run/php/
```

Kalau `SHOW DATABASES` sudah menampilkan database DeltaSoft (fwdapi, dll) →
server pulih. Lanjut. Kalau belum, STOP, tunggu restore beres.

---

## Langkah 1 — Buat database kasir ikan di MySQL 8.0 yang ADA

Ganti `PASSWORD_KUAT` dengan password pilihanmu.

```bash
sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS kasir_ikan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'kasir'@'localhost' IDENTIFIED WITH mysql_native_password BY 'PASSWORD_KUAT';
CREATE USER IF NOT EXISTS 'kasir'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY 'PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON kasir_ikan.* TO 'kasir'@'localhost';
GRANT ALL PRIVILEGES ON kasir_ikan.* TO 'kasir'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

> `IDENTIFIED WITH mysql_native_password` penting di MySQL 8.0 biar Laravel
> gampang konek. Ini HANYA membuat user baru `kasir`, tidak menyentuh user lain.

---

## Langkah 2 — Ambil kode dari GitHub

```bash
git clone https://github.com/supdelta/kasir_ikan_api.git /var/www/kasir_ikan_api
cd /var/www/kasir_ikan_api
composer install --no-dev --optimize-autoloader --no-interaction
```

---

## Langkah 3 — Konfigurasi .env

```bash
cp deploy/env.production .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=PASSWORD_KUAT|" .env
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
chown -R www-data:www-data /var/www/kasir_ikan_api
chmod -R 775 storage bootstrap/cache
```

---

## Langkah 4 — Tambah SATU config nginx (tanpa hapus yang lain)

Ganti `PHPVER` sesuai hasil `ls /run/php/` (mis. `php8.2-fpm.sock` atau `php8.3-fpm.sock`).

```bash
cat > /etc/nginx/sites-available/kasir_ikan <<'NGINX'
server {
    listen 80;
    server_name pos.deltasoft.id;
    root /var/www/kasir_ikan_api/public;
    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/PHPVER;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX

# HANYA aktifkan config kasir ikan — TIDAK menghapus apa pun
ln -sf /etc/nginx/sites-available/kasir_ikan /etc/nginx/sites-enabled/kasir_ikan

nginx -t          # harus "syntax is ok" & "test is successful"
systemctl reload nginx
```

---

## Langkah 5 — HTTPS untuk pos.deltasoft.id saja

```bash
certbot --nginx -d pos.deltasoft.id --non-interactive --agree-tos \
  -m jodiansyahpratama04@gmail.com --redirect
```

---

## Selesai
API kasir ikan: `https://pos.deltasoft.id/api/v1` — app DeltaSoft lain tidak
tersentuh sama sekali.
