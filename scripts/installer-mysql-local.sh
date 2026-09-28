#!/usr/bin/env bash
# One-time local Linux Mint / Ubuntu installation; preserves all application data.
set -Eeuo pipefail
umask 077

APP_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
if [[ $EUID -ne 0 ]]; then
    echo "Exécutez : sudo bash \"$APP_ROOT/scripts/installer-mysql-local.sh\""
    exit 1
fi
APP_USER="${SUDO_USER:-$(stat -c '%U' "$APP_ROOT/.env")}"
if [[ "$APP_USER" == root ]] || ! id "$APP_USER" >/dev/null 2>&1; then
    echo "Lancez ce script avec sudo depuis votre compte utilisateur habituel."
    exit 1
fi
APP_GROUP="$(id -gn "$APP_USER")"
APP_USER_HOME="$(getent passwd "$APP_USER" | cut -d: -f6)"
# Paths in the systemd unit are quoted, but reject directives/newlines/specifiers.
if [[ "$APP_ROOT" == *$'\n'* || "$APP_ROOT" == *'"'* || "$APP_ROOT" == *'%'* || "$APP_ROOT" == *'\\'* ]]; then
    echo "Le chemin du projet contient un caractère incompatible avec ce lanceur."
    exit 1
fi
cd "$APP_ROOT"
[[ -f .env && -f vendor/autoload.php ]] || { echo "Projet Laravel non initialisé."; exit 1; }
MYSQL=(mysql --protocol=socket --socket=/run/mysqld/mysqld.sock --user=root --batch --skip-column-names)
"${MYSQL[@]}" -e 'SELECT 1' >/dev/null
AS_USER=(runuser -u "$APP_USER" -- env "HOME=$APP_USER_HOME")
UNIT=/etc/systemd/system/psychoevaluation.service
if [[ -f "$UNIT" ]] && ! grep -Fq "# Managed for $APP_ROOT" "$UNIT"; then
    echo "Un service psychoevaluation indépendant existe déjà. Aucune modification effectuée."
    exit 1
fi

# Stable system packages, installed only if PHP/Composer/extensions are missing.
if ! command -v php >/dev/null || ! command -v composer >/dev/null || ! php -r 'exit(PHP_VERSION_ID >= 80300 && extension_loaded("pdo_mysql") && extension_loaded("pdo_sqlite") && extension_loaded("mbstring") && extension_loaded("dom") && extension_loaded("xmlwriter") && extension_loaded("zip") && extension_loaded("curl") ? 0 : 1);'; then
    echo "Installation durable de PHP 8.3, de ses extensions et de Composer…"
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y php8.3-cli php8.3-mysql php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip composer
fi
PHP_BIN="$(command -v php)"
ARTISAN=("${AS_USER[@]}" "$PHP_BIN" "$APP_ROOT/artisan")
HELPER=("${AS_USER[@]}" "$PHP_BIN" "$APP_ROOT/scripts/local-mysql.php")
"${AS_USER[@]}" composer check-platform-reqs --no-dev
systemctl enable --now mysql
# Fail before downtime if the migration helper cannot bootstrap Laravel.
"${HELPER[@]}" connection >/dev/null

# Already installed: verify and restart the existing managed service, no reimport.
CURRENT_SOCKET="$("${HELPER[@]}" connection | python3 -c 'import json,sys; print(json.load(sys.stdin)["socket"])')"
if [[ "$CURRENT_SOCKET" == /run/mysqld/mysqld.sock && -f "$UNIT" ]]; then
    "${ARTISAN[@]}" migrate --force
    "${HELPER[@]}" decrypt
    systemctl enable --now psychoevaluation
    curl --fail --silent --show-error http://127.0.0.1:8000/connexion >/dev/null
    echo "Installation déjà terminée et vérifiée : http://127.0.0.1:8000"
    exit 0
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$APP_ROOT/storage/app/private/migration-backups/$STAMP"
install -d -m 700 -o "$APP_USER" -g "$APP_GROUP" "$BACKUP"
cp -p .env "$BACKUP/env.before"
chmod 600 "$BACKUP/env.before"
[[ ! -f "$UNIT" ]] || cp -p "$UNIT" "$BACKUP/service.before"
CONFIG_CHANGED=0
MAINTENANCE=0
SERVICE_WRITTEN=0
SUCCESS=0
install_service() {
cat > "$UNIT" <<UNIT
# Managed for $APP_ROOT
[Unit]
Description=Psychoevaluation - application locale Laravel
After=mysql.service network.target
Requires=mysql.service
[Service]
Type=simple
User=$APP_USER
Group=$APP_GROUP
WorkingDirectory=$APP_ROOT/public
Environment=APP_ENV=local
ExecStart=$PHP_BIN -d display_errors=0 -S 127.0.0.1:8000 "$APP_ROOT/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
Restart=on-failure
RestartSec=3
NoNewPrivileges=true
UMask=0077
[Install]
WantedBy=multi-user.target
UNIT
chmod 644 "$UNIT"
SERVICE_WRITTEN=1
}

rollback() {
    local result=$?
    if [[ $SUCCESS -ne 1 && $result -ne 0 ]]; then
        echo "Échec : conservation des sauvegardes dans $BACKUP"
        if [[ $CONFIG_CHANGED -eq 1 ]]; then
            cp -p "$BACKUP/env.before" "$APP_ROOT/.env"
            "${ARTISAN[@]}" config:clear >/dev/null 2>&1 || true
            echo "La configuration précédente a été restaurée."
        fi
        if [[ $MAINTENANCE -eq 1 ]]; then
            "${ARTISAN[@]}" up >/dev/null 2>&1 || true
        fi
        if [[ $MAINTENANCE -eq 1 || $SERVICE_WRITTEN -eq 1 || $CONFIG_CHANGED -eq 1 ]]; then
            install_service
            systemctl daemon-reload || true
            systemctl restart psychoevaluation || true
        fi
        echo "Aucune base ni aucun compte existant n’a été supprimé."
    fi
}
trap rollback EXIT

"${ARTISAN[@]}" down --retry=5
MAINTENANCE=1
if [[ -f "$UNIT" ]]; then systemctl stop psychoevaluation; fi
# Stop only PHP development servers for this exact application's public folder.
python3 - "$APP_ROOT" <<'PY'
import os, signal, sys
from pathlib import Path
expected=str(Path(sys.argv[1])/'public')
for entry in Path('/proc').iterdir():
    if not entry.name.isdigit(): continue
    try:
        args=(entry/'cmdline').read_bytes().split(b'\0')
        if b'-S' in args and b'127.0.0.1:8000' in args and os.readlink(entry/'cwd')==expected and Path(args[0].decode()).name.startswith('php'):
            os.kill(int(entry.name),signal.SIGTERM)
    except (FileNotFoundError,PermissionError,ProcessLookupError): pass
PY
"${ARTISAN[@]}" config:clear
"${HELPER[@]}" snapshot "$BACKUP"
SOURCE_DB="$(cat "$BACKUP/source-database.txt")"
[[ "$SOURCE_DB" =~ ^[a-zA-Z0-9_]+$ ]] || { echo "Nom de base source non pris en charge."; exit 1; }
mysqldump --defaults-extra-file="$BACKUP/source.cnf" --single-transaction --hex-blob --no-tablespaces --set-gtid-purged=OFF "$SOURCE_DB" > "$BACKUP/database.sql"
[[ -s "$BACKUP/database.sql" ]] || { echo "La sauvegarde SQL est vide."; exit 1; }
tar --exclude=storage/app/private/migration-backups -czf "$BACKUP/private-storage.tar.gz" -C "$APP_ROOT" storage/app/private
chown -R "$APP_USER:$APP_GROUP" "$BACKUP"

TARGET_DB=psychoevaluation
EXISTING_TABLES="$("${MYSQL[@]}" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$TARGET_DB'")"
if [[ "$EXISTING_TABLES" -gt 0 ]]; then
    TARGET_DB="psychoevaluation_$(date +%Y%m%d_%H%M%S)"
    echo "La base psychoevaluation contient déjà des données ; elles sont préservées. Nouvelle base : $TARGET_DB"
fi
TARGET_USER="psycho_app_$(date +%s)"
TARGET_PASSWORD="$(python3 -c 'import secrets; print(secrets.token_urlsafe(32))')"
# Generated identifiers and secret contain only safe ASCII; never display the secret.
cat > "$BACKUP/create-target.sql" <<SQL
CREATE DATABASE \`$TARGET_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$TARGET_USER'@'localhost' IDENTIFIED BY '$TARGET_PASSWORD';
GRANT ALL PRIVILEGES ON \`$TARGET_DB\`.* TO '$TARGET_USER'@'localhost';
SQL
# An existing empty schema can be used; never drop it.
if [[ "$("${MYSQL[@]}" -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$TARGET_DB'")" == 1 ]]; then
    sed -i '1d' "$BACKUP/create-target.sql"
fi
"${MYSQL[@]}" < "$BACKUP/create-target.sql"
"${MYSQL[@]}" "$TARGET_DB" < "$BACKUP/database.sql"

# Verify the new database BEFORE altering .env. The application key is unchanged.
"${AS_USER[@]}" env DB_CONNECTION=mysql DB_HOST=localhost DB_PORT=3306 DB_SOCKET=/run/mysqld/mysqld.sock DB_DATABASE="$TARGET_DB" DB_USERNAME="$TARGET_USER" DB_PASSWORD="$TARGET_PASSWORD" DB_URL= "$PHP_BIN" "$APP_ROOT/scripts/local-mysql.php" verify "$BACKUP"

python3 - "$APP_ROOT/.env" "$TARGET_DB" "$TARGET_USER" "$BACKUP/create-target.sql" <<'PY'
import os,re,sys
from pathlib import Path
path=Path(sys.argv[1]); original=path.stat()
password=re.search(r"IDENTIFIED BY '([^']+)'",Path(sys.argv[4]).read_text()).group(1)
updates={'DB_CONNECTION':'mysql','DB_HOST':'localhost','DB_PORT':'3306','DB_SOCKET':'/run/mysqld/mysqld.sock','DB_DATABASE':sys.argv[2],'DB_USERNAME':sys.argv[3],'DB_PASSWORD':password,'DB_URL':''}
lines=[]
for line in path.read_text().splitlines():
    key=line.partition('=')[0]
    lines.append(key+'='+updates.pop(key) if key in updates else line)
lines.extend(key+'='+value for key,value in updates.items())
temp=path.with_name('.env.migration-new')
with temp.open('x') as file:file.write('\n'.join(lines)+'\n')
os.chmod(temp,0o600);os.chown(temp,original.st_uid,original.st_gid);os.replace(temp,path)
PY
CONFIG_CHANGED=1
"${ARTISAN[@]}" config:clear
"${ARTISAN[@]}" migrate --force
"${HELPER[@]}" decrypt
"${ARTISAN[@]}" view:cache
"${ARTISAN[@]}" up
MAINTENANCE=0

install_service
systemctl daemon-reload
systemctl enable psychoevaluation
systemctl restart psychoevaluation
curl --fail --silent --show-error --retry 10 --retry-connrefused --retry-delay 1 http://127.0.0.1:8000/connexion > /dev/null
systemctl is-active --quiet mysql
systemctl is-active --quiet psychoevaluation
# Store a marker; it contains no password. The backup retains the original key.
printf 'base=%s\nutilisateur=%s\nservice=psychoevaluation\n' "$TARGET_DB" "$TARGET_USER" > "$BACKUP/installed.txt"
chown -R "$APP_USER:$APP_GROUP" "$BACKUP"
SUCCESS=1
printf '\nInstallation terminée et vérifiée.\nApplication : http://127.0.0.1:8000\nMySQL permanent : /run/mysqld/mysqld.sock\nBase : %s\nSauvegarde : %s\nVos identifiants de connexion sont conservés.\n' "$TARGET_DB" "$BACKUP"
