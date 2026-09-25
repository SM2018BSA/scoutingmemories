#!/usr/bin/env bash
# Build a throwaway WordPress (SQLite, PHP's built-in server) with the plugin symlinked in
# and sample posts, then start it on http://localhost:8888.
#
#   cd tests/pdf-viewer && npm install && npm run setup && npx playwright test
#
# Needs php (with pdo_sqlite and curl), node and python3 with reportlab + Pillow.
# WordPress comes from npm (@wp-playground/wordpress-builds), so wordpress.org isn't needed.
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"
WP="${WP_DIR:-/tmp/scouting-pdf-wp}"
PORT="${WP_PORT:-8888}"
BUILD="$WP.build"

if [ ! -f "$WP/wp-load.php" ]; then
    rm -rf "$WP" "$BUILD"
    mkdir -p "$WP" "$BUILD"
    (cd "$BUILD" && npm pack --silent @wp-playground/wordpress-builds@0.9.19 >/dev/null && tar xzf ./*.tgz)
    python3 -c "import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])" "$BUILD/package/src/wordpress/wp-6.5.zip" "$WP"
    cp -r "$BUILD/package/public/wp-6.5/." "$WP/"
    python3 -c "import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])" "$BUILD/package/src/sqlite-database-integration/sqlite-database-integration.zip" "$WP/wp-content/plugins/"
    mv "$WP/wp-content/plugins/sqlite-database-integration-main" "$WP/wp-content/plugins/sqlite-database-integration"
    sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WP/wp-content/plugins/sqlite-database-integration#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" \
        "$WP/wp-content/plugins/sqlite-database-integration/db.copy" > "$WP/wp-content/db.php"
    rm -f "$WP/wp-content/database/.ht.sqlite"
    python3 - "$WP" "$PORT" <<'EOF'
import re, secrets, sys
wp, port = sys.argv[1], sys.argv[2]
p = wp + '/wp-config.php'
s = open(p).read()
s = re.sub(r"put your unique phrase here", lambda m: secrets.token_hex(24), s)
s = s.replace("define( 'WP_DEBUG', false );",
    "define( 'WP_DEBUG', true );\ndefine( 'WP_DEBUG_LOG', '%s/debug.log' );\ndefine( 'WP_DEBUG_DISPLAY', false );\n"
    "define( 'DB_DIR', '%s/wp-content/database/' );\ndefine( 'WP_HOME', 'http://localhost:%s' );\ndefine( 'WP_SITEURL', 'http://localhost:%s' );\n"
    "define( 'WP_ENVIRONMENT_TYPE', 'local' );" % (wp, wp, port, port))
open(p, 'w').write(s)
EOF
    rm -rf "$BUILD"
fi

# The code under test, plus a test-only mu-plugin that stands in for the theme's Bootstrap
mkdir -p "$WP/wp-content/mu-plugins" "$WP/test-assets"
ln -sfn "$REPO/wp-content/plugins/scouting-pdf-embedder" "$WP/wp-content/plugins/scouting-pdf-embedder"
ln -sfn "$REPO/wp-content/mu-plugins/scouting-pdf-loader.php" "$WP/wp-content/mu-plugins/scouting-pdf-loader.php"
cp "$HERE/wp-test-env.php" "$WP/wp-content/mu-plugins/0-test-env.php"
cp -r "$HERE/node_modules/bootstrap/dist" "$WP/test-assets/bootstrap"
cp -r "$HERE/node_modules/bootstrap-icons/font" "$WP/test-assets/bootstrap-icons"
cp "$HERE/router.php" "$WP/router.php"
cp "$HERE/stale-cache.html" "$WP/test-assets/stale-cache.html"

# Test documents
mkdir -p "$WP/wp-content/uploads/fixtures"
python3 "$HERE/make_fixtures.py" "$WP/wp-content/uploads/fixtures" >/dev/null

# A fake storage server for the stream endpoint tests: an HTTPS host on a public-looking IP
# (1.2.3.4 on a loopback alias) with a certificate from a throwaway CA. Needs root; the
# endpoint tests are skipped without it.
TLS="$WP/test-tls"
FAKE_HOST=pdf-proxy-test.example
FAKE_IP=1.2.3.4
if [ "$(id -u)" = "0" ] && [ ! -f "$TLS/server.crt" ]; then
    mkdir -p "$TLS"
    openssl req -x509 -newkey rsa:2048 -nodes -days 30 -subj "/CN=Scouting PDF test CA" \
        -keyout "$TLS/ca.key" -out "$TLS/ca.crt" 2>/dev/null
    openssl req -newkey rsa:2048 -nodes -subj "/CN=$FAKE_HOST" -keyout "$TLS/server.key" -out "$TLS/server.csr" 2>/dev/null
    printf "subjectAltName=DNS:%s\n" "$FAKE_HOST" > "$TLS/san.ext"
    openssl x509 -req -in "$TLS/server.csr" -CA "$TLS/ca.crt" -CAkey "$TLS/ca.key" -CAcreateserial \
        -days 30 -extfile "$TLS/san.ext" -out "$TLS/server.crt" 2>/dev/null
fi
if [ "$(id -u)" = "0" ]; then
    python3 - "$FAKE_IP" <<'PYEOF'
import fcntl, socket, struct, sys
s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
req = struct.pack('16sH2s4s8s', b'lo:9', socket.AF_INET, b'\0\0', socket.inet_aton(sys.argv[1]), b'\0' * 8)
fcntl.ioctl(s, 0x8916, req)  # SIOCSIFADDR
PYEOF
    grep -q "$FAKE_HOST" /etc/hosts || echo "$FAKE_IP $FAKE_HOST" >> /etc/hosts
    pkill -f "fake-storage.mjs" 2>/dev/null || true
    (setsid nohup node "$HERE/fake-storage.mjs" "$TLS" "$WP/wp-content/uploads/fixtures" "$FAKE_IP" 443 </dev/null >"$WP/fake-storage.log" 2>&1 &)
fi

# Start the server (restart if it's already running). No outbound proxy, and PHP's curl
# trusts the test CA, as the live server trusts the real storage certificate.
pkill -f "php -d curl.cainfo" 2>/dev/null || true
pkill -f "php -S localhost:$PORT" 2>/dev/null || true
CAFILE="$TLS/ca.crt"
[ -f "$CAFILE" ] || CAFILE=/etc/ssl/certs/ca-certificates.crt
(cd "$WP" && env -u https_proxy -u HTTPS_PROXY -u http_proxy -u HTTP_PROXY \
    setsid nohup php -d "curl.cainfo=$CAFILE" -S "localhost:$PORT" router.php </dev/null >"$WP/server.log" 2>&1 &)
for _ in $(seq 1 50); do
    curl -s -o /dev/null "http://localhost:$PORT/wp-admin/install.php" && break
    sleep 0.2
done

if ! curl -s "http://localhost:$PORT/" | grep -q "SM Test"; then
    curl -s -o /dev/null -X POST "http://localhost:$PORT/wp-admin/install.php?step=2" \
        --data "weblog_title=SM+Test&user_name=admin&admin_password=admin-pass-123&admin_password2=admin-pass-123&pw_weak=1&admin_email=admin%40example.com&blog_public=0&language="
fi

php "$HERE/create-posts.php" "$WP"
echo "WordPress with test posts is running at http://localhost:$PORT"
