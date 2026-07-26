#!/usr/bin/env bash
#
# Setup TempMail di VPS Ubuntu 24.04 yang sudah punya nginx + MySQL.
# Aman dijalankan ulang (idempotent). Jalankan dari folder project:
#
#   sudo bash deploy/setup-vps.sh
#
# Yang dikerjakan script ini:
#   1. Pasang PHP 8.4 + ekstensi yang dibutuhkan (PPA ondrej) bila belum ada
#   2. Pasang Composer bila belum ada, lalu composer install --no-dev
#   3. Buat database + user MySQL
#   4. Siapkan .env produksi (APP_KEY, token webhook, akun admin)
#   5. Migrasi + seed akun pemilik
#   6. Konfigurasi nginx (vhost + batas upload utk webhook 15 MB)
#   7. HTTPS via certbot (opsional — wajib untuk Cloudflare Worker)
#   8. Cron scheduler (auto-hapus retensi, backup harian, heartbeat)
#
set -euo pipefail

PHP_VER=8.4
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ $EUID -ne 0 ]]; then
    echo "Jalankan dengan sudo: sudo bash deploy/setup-vps.sh" >&2
    exit 1
fi

tanya() { # tanya "Pertanyaan" "default" -> jawaban
    local jawab
    read -rp "$1 [$2]: " jawab
    echo "${jawab:-$2}"
}

echo "== TempMail — setup VPS =="
echo "Folder aplikasi: $APP_DIR"
echo

APP_DOMAIN=$(tanya "Domain tempat app ini diakses (untuk nginx + webhook)" "mail.danang.biz.id")
MAIL_DOMAINS=$(tanya "Domain penerima email (pisahkan koma)" "danangabuhafid.my.id,danang.biz.id")
DB_NAME=$(tanya "Nama database MySQL" "tempmail")
DB_USER=$(tanya "User MySQL" "tempmail")
DB_PASS=$(tanya "Password MySQL (enter = generate acak)" "$(openssl rand -hex 12)")
ADMIN_EMAIL=$(tanya "Email login pemilik" "akmaldira01@gmail.com")
ADMIN_PASS=$(tanya "Password login pemilik (enter = generate acak)" "$(openssl rand -base64 12 | tr -d '/+=')")
PAKAI_SSL=$(tanya "Pasang HTTPS via certbot sekarang? (y/n — butuh DNS domain sudah mengarah ke VPS ini)" "y")

# ---------- 1. PHP 8.4 ----------
if ! command -v php${PHP_VER} >/dev/null 2>&1; then
    echo "-> Memasang PHP ${PHP_VER} (PPA ondrej)..."
    apt-get update -qq
    apt-get install -y -qq software-properties-common
    add-apt-repository -y ppa:ondrej/php
    apt-get update -qq
fi
echo "-> Memastikan ekstensi PHP lengkap..."
apt-get install -y -qq \
    php${PHP_VER}-fpm php${PHP_VER}-cli php${PHP_VER}-mysql php${PHP_VER}-xml \
    php${PHP_VER}-mbstring php${PHP_VER}-curl php${PHP_VER}-zip php${PHP_VER}-gd \
    php${PHP_VER}-intl php${PHP_VER}-bcmath unzip

PHP_BIN="$(command -v php${PHP_VER})"

# Webhook menerima raw email s/d 15 MB — naikkan batas bawaan PHP (8 MB)
cat > /etc/php/${PHP_VER}/fpm/conf.d/99-tempmail.ini <<EOF
post_max_size = 25M
upload_max_filesize = 25M
memory_limit = 256M
EOF
systemctl restart php${PHP_VER}-fpm

# ---------- 2. Composer ----------
if ! command -v composer >/dev/null 2>&1; then
    echo "-> Memasang Composer..."
    curl -sS https://getcomposer.org/installer | "$PHP_BIN" -- --install-dir=/usr/local/bin --filename=composer
fi
echo "-> composer install (tanpa dev)..."
cd "$APP_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --quiet

# ---------- 3. Database ----------
echo "-> Menyiapkan database ${DB_NAME}..."
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# ---------- 4. .env produksi ----------
if [[ ! -f .env ]]; then
    cp .env.example .env
fi

set_env() { # set_env KEY value — tambah bila belum ada
    if grep -q "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        echo "$1=$2" >> .env
    fi
}

echo "-> Menulis .env produksi..."
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "https://${APP_DOMAIN}"
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASS"
set_env TEMPMAIL_DOMAINS "\"${MAIL_DOMAINS}\""
set_env ADMIN_EMAIL "$ADMIN_EMAIL"
set_env ADMIN_PASSWORD "$ADMIN_PASS"

# Token webhook: buat sekali, jangan ditimpa saat script dijalankan ulang
if ! grep -q '^INBOUND_WEBHOOK_TOKEN=.\+' .env; then
    set_env INBOUND_WEBHOOK_TOKEN "$(openssl rand -hex 32)"
fi

if grep -q '^APP_KEY=base64:' .env; then
    echo "   APP_KEY sudah ada — dilewati."
else
    "$PHP_BIN" artisan key:generate --force
fi

# ---------- 5. Migrasi + seed ----------
echo "-> Migrasi database + seed akun pemilik..."
"$PHP_BIN" artisan migrate --force --seed

# ---------- 6. Permission + nginx ----------
echo "-> Permission storage & cache..."
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "-> Menulis vhost nginx..."
cat > /etc/nginx/sites-available/tempmail.conf <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${APP_DOMAIN};
    root ${APP_DIR}/public;

    index index.php;
    charset utf-8;

    # Raw email dari Cloudflare Worker bisa sampai 15 MB
    client_max_body_size 25m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
    }

    location ~ /\.(?!well-known).* { deny all; }

    access_log /var/log/nginx/tempmail.access.log;
    error_log  /var/log/nginx/tempmail.error.log;
}
EOF
ln -sf /etc/nginx/sites-available/tempmail.conf /etc/nginx/sites-enabled/tempmail.conf
nginx -t
systemctl reload nginx

# ---------- 7. HTTPS ----------
if [[ "$PAKAI_SSL" == "y" || "$PAKAI_SSL" == "Y" ]]; then
    echo "-> Memasang certbot + sertifikat untuk ${APP_DOMAIN}..."
    apt-get install -y -qq certbot python3-certbot-nginx
    certbot --nginx -d "$APP_DOMAIN" --redirect --non-interactive --agree-tos -m "$ADMIN_EMAIL" || {
        echo "!! certbot gagal (DNS belum mengarah ke VPS ini?). Jalankan manual nanti:"
        echo "   sudo certbot --nginx -d $APP_DOMAIN --redirect"
    }
fi

# ---------- 8. Cron scheduler ----------
echo "-> Memasang cron scheduler..."
cat > /etc/cron.d/tempmail <<EOF
* * * * * www-data cd ${APP_DIR} && ${PHP_BIN} artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/tempmail

# ---------- 9. Cache produksi ----------
"$PHP_BIN" artisan config:cache --quiet
"$PHP_BIN" artisan route:cache --quiet
"$PHP_BIN" artisan view:cache --quiet
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

TOKEN="$(grep '^INBOUND_WEBHOOK_TOKEN=' .env | cut -d= -f2)"

echo
echo "================= SELESAI ================="
echo "URL aplikasi   : https://${APP_DOMAIN}"
echo "Login pemilik  : ${ADMIN_EMAIL}"
echo "Password       : ${ADMIN_PASS}"
echo "MySQL          : ${DB_USER} / ${DB_PASS} (db: ${DB_NAME})"
echo
echo "Untuk Cloudflare Email Worker (menu Setup di aplikasi juga menampilkan ini):"
echo "  WEBHOOK_URL   = https://${APP_DOMAIN}/inbound/email"
echo "  WEBHOOK_TOKEN = ${TOKEN}"
echo
echo "Langkah berikutnya: buka https://${APP_DOMAIN}/setup lalu ikuti wizard Cloudflare."
echo "Simpan kredensial di atas — password tidak ditampilkan lagi."
echo "==========================================="
