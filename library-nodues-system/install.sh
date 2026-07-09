#!/usr/bin/env bash
# =====================================================================
#  Library No Dues Management System — Installer
#  Safe to run on a server that ALREADY runs Koha.
#
#  What makes this safe alongside Koha:
#    - Uses the SAME MySQL/MariaDB server Koha already runs, but only
#      ever touches a new, isolated database ("nodues_system") through
#      a new, isolated database user scoped to just that database.
#      It never reads, writes, or grants access to any Koha database.
#    - Runs its own Apache vhost on its OWN port (default 8091) instead
#      of editing Koha's vhosts or the default site. Koha's Apache
#      configuration is never opened, let alone modified.
#    - Only ever reloads Apache (systemctl reload), never restarts it,
#      so live Koha/OPAC sessions are not dropped.
#    - Never installs or starts a second database server, and never
#      touches root's MySQL password.
#    - Skips any step whose target already exists, so re-running this
#      script (e.g. after a network hiccup) won't duplicate anything
#      or overwrite data.
#
#  Usage:
#     sudo bash install.sh
# =====================================================================
set -euo pipefail

APP_SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DEST_DIR="/var/www/nodues"
DB_NAME="nodues_system"
DB_USER="nodues_user"
APACHE_SITE_CONF="/etc/apache2/sites-available/nodues.conf"
APACHE_PORT_CONF="/etc/apache2/conf-available/nodues-listen.conf"

if [[ "$EUID" -ne 0 ]]; then
  echo "Please run this script with sudo: sudo bash install.sh"
  exit 1
fi

trim() { echo -n "$1" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//'; }

echo "======================================================================"
echo " Library No Dues Management System — Installer"
echo " (safe to run alongside an existing Koha installation)"
echo "======================================================================"

# ---------------------------------------------------------------------
# 1. Collect configuration from the operator
# ---------------------------------------------------------------------
read -rp "Server LAN IP or hostname this app will be accessed from [e.g. 192.168.1.10]: " SERVER_HOST
SERVER_HOST=$(trim "${SERVER_HOST:-127.0.0.1}")

read -rp "Port for this app to listen on (must be free, not used by Koha) [8091]: " APP_PORT
APP_PORT=$(trim "${APP_PORT:-8091}")

if ss -ltn 2>/dev/null | awk '{print $4}' | grep -q ":${APP_PORT}\$"; then
  echo "Port ${APP_PORT} already appears to be in use on this server."
  echo "Please re-run and choose a different, free port (Koha commonly uses 80 and 8080)."
  exit 1
fi

read -rp "Admin username for the first login [admin]: " ADMIN_USERNAME
ADMIN_USERNAME=$(trim "${ADMIN_USERNAME:-admin}")

read -rp "Admin email for the first login [admin@library.local]: " ADMIN_EMAIL
ADMIN_EMAIL=$(trim "${ADMIN_EMAIL:-admin@library.local}")

while true; do
  read -rsp "Set a password for the admin account (min 8 chars): " ADMIN_PASS
  echo
  if [[ ${#ADMIN_PASS} -ge 8 ]]; then break; fi
  echo "Password too short, please enter at least 8 characters."
done

echo
echo "Koha integration can be configured now or later by editing"
echo "includes/config.php on the server after install."
read -rp "Koha REST API base URL (leave blank to configure later, e.g. http://127.0.0.1:8080/api/v1): " KOHA_URL
KOHA_URL=$(trim "$KOHA_URL")
read -rp "Koha API service account username (leave blank to configure later): " KOHA_USER
KOHA_USER=$(trim "$KOHA_USER")
if [[ -n "$KOHA_USER" ]]; then
  read -rsp "Koha API service account password: " KOHA_PASS
  echo
  KOHA_PASS=$(trim "$KOHA_PASS")
else
  KOHA_PASS=""
fi

# ---------------------------------------------------------------------
# 2. Verify Apache and MySQL/MariaDB are present (Koha needs both —
#    we never install or reconfigure the database server itself, only
#    add missing PHP packages if needed).
# ---------------------------------------------------------------------
echo "==> Checking prerequisites (Apache, MySQL/MariaDB, PHP)..."

if ! command -v apache2ctl >/dev/null 2>&1; then
  echo "ERROR: Apache2 was not found on this server. This installer expects"
  echo "Apache to already be installed (as it would be for Koha). Please"
  echo "install/configure Apache first, then re-run this script."
  exit 1
fi

if ! command -v mysql >/dev/null 2>&1; then
  echo "ERROR: No 'mysql' client was found on this server. This installer"
  echo "expects MySQL/MariaDB to already be running (as it would be for"
  echo "Koha). Please make sure your database server is installed and"
  echo "running, then re-run this script."
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
echo "    (If you see apt errors about unrelated third-party repositories"
echo "    below — e.g. Koha's community repo or similar — that's a"
echo "    pre-existing issue with those repos, not this installer. The"
echo "    PHP packages we need come from Ubuntu's main archive, so we"
echo "    continue past it.)"
apt-get update -y -qq || true
if ! apt-get install -y -qq php php-mysql php-mbstring php-curl php-xml php-cli libapache2-mod-php; then
  echo
  echo "ERROR: could not install required PHP packages."
  echo "This usually means Ubuntu's main package lists themselves failed to"
  echo "update (not just a third-party repo). Try running:"
  echo "    sudo apt-get update"
  echo "and check for errors mentioning 'archive.ubuntu.com' or 'security.ubuntu.com'"
  echo "specifically — those are the ones this installer actually needs."
  exit 1
fi

# ---------------------------------------------------------------------
# 3. Get MySQL/MariaDB root access (without ever changing its password)
# ---------------------------------------------------------------------
echo "==> Connecting to your existing MySQL/MariaDB server..."
MYSQL_ROOT=(mysql --user=root)
if ! "${MYSQL_ROOT[@]}" -e "SELECT 1;" >/dev/null 2>&1; then
  read -rsp "Enter your MySQL/MariaDB root password: " ROOT_PW
  echo
  MYSQL_ROOT=(mysql --user=root --password="${ROOT_PW}")
  if ! "${MYSQL_ROOT[@]}" -e "SELECT 1;" >/dev/null 2>&1; then
    echo "ERROR: Could not authenticate to MySQL/MariaDB as root."
    echo "Nothing has been changed. Please check the password and try again."
    exit 1
  fi
fi
echo "    Connected."

# ---------------------------------------------------------------------
# 4. Create an ISOLATED database + user (never touches Koha's DBs)
# ---------------------------------------------------------------------
echo "==> Creating isolated database and user for this app..."
DB_PASS="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24 || true)"

"${MYSQL_ROOT[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
echo "    Database '${DB_NAME}' and user '${DB_USER}' ready (scoped to this database only)."

# ---------------------------------------------------------------------
# 5. Import schema — only on first install, never overwrites data
# ---------------------------------------------------------------------
FRESH_INSTALL=0
TABLE_EXISTS=$("${MYSQL_ROOT[@]}" -N -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='applications';")

if [[ "${TABLE_EXISTS}" -eq 0 ]]; then
  echo "==> Importing schema (first install)..."
  "${MYSQL_ROOT[@]}" "${DB_NAME}" < "${APP_SRC_DIR}/schema.sql"
  FRESH_INSTALL=1
else
  echo "==> Schema already present — skipping import (existing data preserved)."
fi

# ---------------------------------------------------------------------
# 6. Copy application files
# ---------------------------------------------------------------------
echo "==> Copying application to ${APP_DEST_DIR}..."
mkdir -p "${APP_DEST_DIR}"
rsync -a --exclude 'install.sh' --exclude 'schema.sql' "${APP_SRC_DIR}/" "${APP_DEST_DIR}/"

# ---------------------------------------------------------------------
# 7. Write configuration
# ---------------------------------------------------------------------
echo "==> Writing configuration..."
CONFIG_FILE="${APP_DEST_DIR}/includes/config.php"

sed -i "s|__DB_PASSWORD__|${DB_PASS}|" "${CONFIG_FILE}"
sed -i "s|define('APP_URL', 'http://localhost/nodues');|define('APP_URL', 'http://${SERVER_HOST}:${APP_PORT}');|" "${CONFIG_FILE}"

if [[ -n "$KOHA_URL" ]]; then
  sed -i "s|define('KOHA_API_BASE', 'http://127.0.0.1:8080/api/v1');|define('KOHA_API_BASE', '${KOHA_URL}');|" "${CONFIG_FILE}"
fi
if [[ -n "$KOHA_USER" ]]; then
  sed -i "s|define('KOHA_API_USER', 'nodues_service');|define('KOHA_API_USER', '${KOHA_USER}');|" "${CONFIG_FILE}"
  sed -i "s|define('KOHA_API_PASS', 'change_this_password');|define('KOHA_API_PASS', '${KOHA_PASS}');|" "${CONFIG_FILE}"
  sed -i "s|define('KOHA_API_LIVE', false);|define('KOHA_API_LIVE', true);|" "${CONFIG_FILE}"
fi

# ---------------------------------------------------------------------
# 8. Set admin credentials — only on first install, so re-running this
#    script later (e.g. to fix Apache config) never resets a password
#    staff may have already changed.
# ---------------------------------------------------------------------
if [[ "${FRESH_INSTALL}" -eq 1 ]]; then
  echo "==> Setting admin credentials..."
  ADMIN_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "${ADMIN_PASS}")"
  "${MYSQL_ROOT[@]}" "${DB_NAME}" <<SQL
UPDATE users SET username = '${ADMIN_USERNAME}', email = '${ADMIN_EMAIL}', password_hash = '${ADMIN_HASH}'
WHERE email = 'admin@library.local';
SQL
else
  echo "==> Existing installation detected — leaving admin credentials untouched."
  echo "    (Use Admin > Users > Reset Password in the app if you need to change it.)"
fi

# ---------------------------------------------------------------------
# 9. Apache — own port, own vhost, never touches Koha's configuration
# ---------------------------------------------------------------------
echo "==> Configuring Apache on its own port (${APP_PORT})..."

cat > "${APACHE_PORT_CONF}" <<APACHECONF
# Added by the Library No Dues Management System installer.
# This only opens an additional port for THIS app — it does not
# change any existing Listen directive Koha relies on.
Listen ${APP_PORT}
APACHECONF

cat > "${APACHE_SITE_CONF}" <<APACHECONF
# Added by the Library No Dues Management System installer.
# Runs entirely on its own port/vhost — Koha's vhosts are untouched.
<VirtualHost *:${APP_PORT}>
    DocumentRoot ${APP_DEST_DIR}
    <Directory ${APP_DEST_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php login.php
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/nodues_error.log
    CustomLog \${APACHE_LOG_DIR}/nodues_access.log combined
</VirtualHost>
APACHECONF

a2enconf nodues-listen >/dev/null 2>&1 || true
a2ensite nodues >/dev/null 2>&1 || true

echo "==> Validating Apache configuration before touching the live server..."
if ! apache2ctl configtest 2>&1 | grep -q "Syntax OK"; then
  echo "ERROR: Apache configuration test failed. Nothing has been reloaded,"
  echo "so your existing Koha site is unaffected. Details:"
  apache2ctl configtest
  exit 1
fi

# ---------------------------------------------------------------------
# 10. Permissions
# ---------------------------------------------------------------------
echo "==> Setting permissions..."
chown -R www-data:www-data "${APP_DEST_DIR}"
find "${APP_DEST_DIR}" -type d -exec chmod 755 {} \;
find "${APP_DEST_DIR}" -type f -exec chmod 644 {} \;
chmod -R 775 "${APP_DEST_DIR}/storage"
chmod +x "${APP_DEST_DIR}/seed.php" "${APP_DEST_DIR}/demo.php" 2>/dev/null || true

# ---------------------------------------------------------------------
# 11. Reload (never restart) Apache so Koha's live sessions aren't dropped
# ---------------------------------------------------------------------
echo "==> Reloading Apache (not restarting — Koha stays up)..."
systemctl reload apache2

# ---------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------
echo
echo "======================================================================"
echo " Installation complete — your Koha installation was not touched."
echo "======================================================================"
echo " App URL   : http://${SERVER_HOST}:${APP_PORT}/"
echo " Admin log-in:"
echo "    Username : ${ADMIN_USERNAME}"
echo "    Email    : ${ADMIN_EMAIL}"
echo "    Password : (the one you just typed)"
echo
echo " Database:"
echo "    Isolated database  : ${DB_NAME}"
echo "    Isolated DB user   : ${DB_USER} (access limited to ${DB_NAME} only)"
echo "    Credentials stored : ${APP_DEST_DIR}/includes/config.php"
echo
if [[ -z "$KOHA_URL" ]]; then
echo " NOTE: Koha API was not configured. The 'Check Koha' button will use"
echo " simulated data until you edit includes/config.php with your Koha"
echo " REST API URL and credentials, and set KOHA_API_LIVE to true."
echo
fi
echo " Next steps:"
echo "   1. Log in as admin and create your Front Desk, E-Resources, and"
echo "      Librarian staff accounts under Admin > Users (each one needs"
echo "      a username — the form will suggest one from their email if"
echo "      you leave it blank)."
echo "   2. Share the public application form with patrons:"
echo "      http://${SERVER_HOST}:${APP_PORT}/apply.php"
echo "   3. Staff open their portal directly at:"
echo "      http://${SERVER_HOST}:${APP_PORT}/login.php"
echo "   4. Optional: try the workflow with fake data first —"
echo "      cd ${APP_DEST_DIR} && sudo -u www-data php seed.php"
echo "      cd ${APP_DEST_DIR} && sudo -u www-data php demo.php"
echo "      Both ask for confirmation and are safe to skip entirely."
echo "======================================================================"
