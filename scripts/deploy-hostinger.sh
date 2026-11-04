#!/bin/bash
# Deploys a fresh clone (public_html/_release) over the live site on Hostinger shared hosting, where there is
# no SSH from CI and cron is the only way to run commands. Holds no secrets: it reads the server's own .env.
#
#   backup   dump the database, archive the site files and .env to ~/deploy/backup (refuses to continue on failure)
#   inspect  report environment, renewal backlog and pending migrations; changes nothing
#   prepare  composer install and smoke-boot the release, against the live .env
#   switch   run migrations, then move the old code aside and the new code in (under a second)
#   rollback move the old code back and restore the database from the backup
#   encrypt  encrypt stored messages and eSIM data (after this the OLD code can't read them: run once happy)
#
# Each step runs once. Re-running prints its log, so a cron job firing every minute is harmless.
set -u
umask 077

LIVE="$HOME/domains/spacechipltd.com/public_html"
REL="$LIVE/_release"
DEPLOY="$HOME/deploy"
BACKUP="$DEPLOY/backup"
OLD="$DEPLOY/old"
PHP=/opt/alt/php83/usr/bin/php
COMPOSER=/usr/local/bin/composer2.phar
STEP="${1:-}"
LOG="$DEPLOY/$STEP.log"
CNF="$DEPLOY/.my.cnf"

mkdir -p "$DEPLOY" "$BACKUP" "$OLD"

case "$STEP" in backup|inspect|prepare|switch|rollback|encrypt) ;; *) echo "unknown step"; exit 2 ;; esac

if [ -e "$DEPLOY/$STEP.done" ] || ! mkdir "$DEPLOY/$STEP.lock" 2>/dev/null; then
    tail -n 60 "$LOG" 2>/dev/null
    exit 0
fi

finish() { rm -f "$CNF"; touch "$DEPLOY/$STEP.done"; rmdir "$DEPLOY/$STEP.lock" 2>/dev/null; tail -n 60 "$LOG" >&3; exit "$1"; }
fail() { echo "FAILED: $*" >>"$LOG"; finish 1; }
exec 3>&1 >>"$LOG" 2>&1
echo "== $STEP $(date -u +%FT%TZ)"

# Connection details go in a private options file: never on a command line, never in the log.
make_cnf() {
    (cd "$LIVE" && "$PHP" -r '
        $v = [];
        foreach (file(".env", FILE_IGNORE_NEW_LINES) as $l) {
            if (preg_match("/^\s*(DB_[A-Z]+)\s*=\s*(.*)$/", $l, $m)) {
                $x = trim($m[2]);
                if (strlen($x) > 1 && ($x[0] === "\"" || $x[0] === "\x27") && substr($x, -1) === $x[0]) { $x = substr($x, 1, -1); }
                $v[$m[1]] = $x;
            }
        }
        $q = fn ($s) => "\"".addcslashes((string) $s, "\\\"")."\"";
        echo "[client]\nhost=", $q($v["DB_HOST"] ?? "127.0.0.1"), "\nuser=", $q($v["DB_USERNAME"] ?? ""), "\npassword=", $q($v["DB_PASSWORD"] ?? ""), "\n";
    ') >"$CNF" || fail "could not read DB settings"
    chmod 600 "$CNF"
    DB_NAME=$(cd "$LIVE" && sed -n 's/^DB_DATABASE=//p' .env | tr -d '"\r' | head -1)
    [ -n "$DB_NAME" ] || fail "DB_DATABASE not found"
}

case "$STEP" in
backup)
    cp "$REL/scripts/deploy-hostinger.sh" "$DEPLOY/deploy-hostinger.sh" || fail "copy script"
    make_cnf
    mysqldump --defaults-extra-file="$CNF" --no-tablespaces --single-transaction "$DB_NAME" >"$BACKUP/db.sql" || fail "mysqldump"
    tail -n 1 "$BACKUP/db.sql" | grep -q "Dump completed" || fail "dump is incomplete"
    echo "tables dumped: $(grep -c '^CREATE TABLE' "$BACKUP/db.sql")"
    tar czf "$BACKUP/files.tar.gz" -C "$LIVE" --exclude=./_release --exclude=./vendor --exclude=./storage/logs --exclude=./.git_ . || fail "tar"
    gzip -t "$BACKUP/files.tar.gz" || fail "archive is corrupt"
    cp "$LIVE/.env" "$BACKUP/env.bak" || fail "copy .env"
    rm -f "$HOME/db1.sql" "$HOME/db1.e" "$HOME/o" "$HOME/d.txt" "$HOME/b.txt" "$HOME/b"
    rmdir "$HOME/backups" 2>/dev/null
    ls -l "$BACKUP" | sed 1d | awk '{print $5, $9}'
    echo "BACKUP_OK"
    ;;

inspect)
    cd "$REL" || fail "no release"
    cp "$LIVE/.env" .env
    "$PHP" -v | head -1
    for k in APP_ENV APP_DEBUG APP_URL SESSION_DRIVER CACHE_STORE MAIL_MAILER QUEUE_CONNECTION; do grep -m1 "^$k=" "$LIVE/.env" || echo "$k (unset)"; done
    "$PHP" -m | grep -ciE '^(pdo_mysql|mbstring|openssl|intl|gd|fileinfo|tokenizer|dom|curl)$' | sed 's/^/required extensions present: /'
    make_cnf
    mysql --defaults-extra-file="$CNF" -N "$DB_NAME" <<'SQL' | sed 's/\t/ = /'
SELECT 'users', COUNT(*) FROM users;
SELECT 'admins', COUNT(*) FROM users WHERE is_admin = 1;
SELECT 'wallet balance total (minor)', COALESCE(SUM(wallet_balance_minor), 0) FROM users;
SELECT 'rentals renewable now', COUNT(*) FROM social_number_rentals WHERE status IN ('active','past_due') AND auto_renew = 1 AND current_period_end <= NOW() + INTERVAL 2 DAY;
SELECT 'rentals already lapsed', COUNT(*) FROM social_number_rentals WHERE status IN ('active','past_due') AND auto_renew = 1 AND current_period_end < NOW();
SELECT 'virtual subs active/past_due', COUNT(*) FROM virtual_number_subscriptions WHERE status IN ('active','past_due');
SELECT 'virtual subs lapsed', COUNT(*) FROM virtual_number_subscriptions WHERE status IN ('active','past_due') AND current_period_end < NOW();
SELECT 'migrations recorded', COUNT(*) FROM migrations;
SQL
    echo "INSPECT_OK"
    ;;

prepare)
    cd "$REL" || fail "no release"
    cp "$LIVE/.env" .env || fail "copy .env"
    COMPOSER_HOME="$HOME/.composer" "$PHP" -d memory_limit=-1 "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-progress 2>&1 | tail -n 3
    [ -f vendor/autoload.php ] || fail "composer install"
    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    "$PHP" artisan package:discover --no-ansi 2>&1 | tail -n 1 | cut -c1-70
    "$PHP" artisan --version --no-ansi || fail "release does not boot"
    "$PHP" artisan route:list --no-ansi --path=admin/login 2>&1 | head -n 3 | cut -c1-80
    "$PHP" artisan migrate:status --no-ansi 2>&1 | grep -c Pending | sed 's/^/pending migrations: /'
    echo "PREPARE_OK"
    ;;

switch)
    [ -e "$DEPLOY/backup.done" ] && grep -q BACKUP_OK "$DEPLOY/backup.log" || fail "no verified backup"
    grep -q PREPARE_OK "$DEPLOY/prepare.log" || fail "release not prepared"
    cd "$REL" || fail "no release"
    "$PHP" artisan migrate --force --no-ansi 2>&1 | tail -n 25 | cut -c1-90
    [ "${PIPESTATUS[0]}" -eq 0 ] || fail "migration failed: code NOT switched (restore the DB with rollback if needed)"

    : >"$DEPLOY/swapped.txt"
    for item in $(ls -A "$REL"); do
        case "$item" in .git|.env|storage) continue ;; esac
        [ -e "$LIVE/$item" ] && mv "$LIVE/$item" "$OLD/$item"
        mv "$REL/$item" "$LIVE/$item" && echo "$item" >>"$DEPLOY/swapped.txt"
    done

    cd "$LIVE" || fail "no live dir"
    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
    chmod -R u+rwX storage bootstrap/cache
    chmod 755 . public 2>/dev/null
    find app bootstrap config database public resources routes vendor -type d -exec chmod 755 {} + 2>/dev/null
    find app bootstrap config database public resources routes vendor -type f -exec chmod 644 {} + 2>/dev/null
    chmod 755 storage storage/framework storage/framework/* storage/logs bootstrap/cache
    "$PHP" artisan optimize:clear --no-ansi 2>&1 | tail -n 2 | cut -c1-70
    rm -rf "$REL"
    "$PHP" artisan --version --no-ansi
    "$PHP" artisan migrate:status --no-ansi 2>&1 | grep -c Pending | sed 's/^/pending migrations: /'
    echo "SWITCH_OK"
    ;;

rollback)
    [ -f "$DEPLOY/swapped.txt" ] || fail "nothing to roll back"
    mkdir -p "$DEPLOY/failed"
    while read -r item; do
        [ -n "$item" ] || continue
        mv "$LIVE/$item" "$DEPLOY/failed/$item"
        [ -e "$OLD/$item" ] && mv "$OLD/$item" "$LIVE/$item"
    done <"$DEPLOY/swapped.txt"
    make_cnf
    mysql --defaults-extra-file="$CNF" "$DB_NAME" <"$BACKUP/db.sql" || fail "database restore"
    cd "$LIVE" && "$PHP" artisan optimize:clear --no-ansi 2>&1 | tail -n 1 | cut -c1-70
    echo "ROLLBACK_OK"
    ;;

encrypt)
    cd "$LIVE" || fail "no live dir"
    "$PHP" artisan data:encrypt-messages --no-ansi 2>&1 | tail -n 3 | cut -c1-100
    [ "${PIPESTATUS[0]}" -eq 0 ] || fail "encryption"
    "$PHP" artisan launch:check --no-ansi 2>&1 | grep -E '✘|Failures|→' | cut -c1-150
    echo "ENCRYPT_OK"
    ;;
esac

finish 0
