#!/usr/bin/env bash
#
# Kasir Ikan - VPS setup (Ubuntu 22.04 / 24.04)
# Jalankan sebagai root:  sudo bash setup-vps.sh
#
# Script ini install: PHP 8.2, Composer, MariaDB, Nginx, Certbot,
# lalu deploy Laravel + migrate + HTTPS otomatis.
#
set -euo pipefail

# ============ EDIT BAGIAN INI ============
DOMAIN="pos.deltasoft.id"               # domain yg sudah diarahkan (A record) ke IP VPS ini
EMAIL="jodiansyahpratama04@gmail.com"   # buat sertifikat HTTPS (Let's Encrypt)

DB_NAME="kasir_ikan"
DB_USER="kasir"
DB_PASS="GANTI_PASSWORD_KUAT_DISINI"    # <-- WAJIB ganti

# Cara ambil kode. Pilih salah satu:
#  - Kalau sudah push ke GitHub, isi REPO_URL.
#  - Kalau upload manual (scp/rsync) ke $APP_DIR, biarkan REPO_URL kosong "".
REPO_URL="https://github.com/supdelta/kasir_ikan_api.git"
APP_DIR="/var/www/kasir_ikan_api"
# =========================================

echo "==> [1/9] Update sistem & tambah PPA PHP"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y software-properties-common curl unzip git
add-apt-repository -y ppa:ondrej/php
apt-get update -y

echo "==> [2/9] Install PHP 8.2 + ekstensi, Nginx, MariaDB, Certbot"
apt-get install -y \
  php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml \
  php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd php8.2-intl \
  nginx mariadb-server certbot python3-certbot-nginx

echo "==> [3/9] Install Composer"
if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer
fi

echo "==> [4/9] Buat database & user MySQL"
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';"
mysql -e "FLUSH PRIVILEGES;"

echo "==> [5/9] Ambil kode aplikasi"
if [ -n "${REPO_URL}" ]; then
  if [ -d "${APP_DIR}/.git" ]; then
    git -C "${APP_DIR}" pull
  else
    git clone "${REPO_URL}" "${APP_DIR}"
  fi
else
  echo "    REPO_URL kosong -> pakai kode yg sudah kamu upload ke ${APP_DIR}"
  test -f "${APP_DIR}/artisan" || { echo "ERROR: ${APP_DIR}/artisan tidak ada. Upload dulu kodenya."; exit 1; }
fi

cd "${APP_DIR}"

echo "==> [6/9] Composer install (production)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> [7/9] Konfigurasi .env"
if [ ! -f .env ]; then
  cp deploy/env.production .env
fi
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|"        .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|"       .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|"       .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|"       .env

# APP_KEY: generate kalau belum ada
if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan storage:link || true

echo "==> [8/9] Set kepemilikan & izin folder"
chown -R www-data:www-data "${APP_DIR}"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

echo "==> [9/9] Konfigurasi Nginx + HTTPS"
sed "s|__DOMAIN__|${DOMAIN}|g; s|__APP_DIR__|${APP_DIR}|g" \
  deploy/nginx-kasir.conf > /etc/nginx/sites-available/kasir_ikan
ln -sf /etc/nginx/sites-available/kasir_ikan /etc/nginx/sites-enabled/kasir_ikan
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

# HTTPS (butuh domain sudah mengarah ke VPS ini)
certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${EMAIL}" --redirect || \
  echo "!! Certbot gagal (cek A record domain -> IP VPS). API tetap jalan via http dulu."

echo ""
echo "============================================================"
echo " SELESAI. API kamu: https://${DOMAIN}/api/v1"
echo " Tes:  curl https://${DOMAIN}/api/v1/health  (kalau ada route health)"
echo " Update di Flutter: lib/core/network/api_client.dart"
echo "   const _baseUrl = 'https://${DOMAIN}/api/v1';"
echo "============================================================"
