#!/usr/bin/env bash
# Entrypoint do container `api` (roda depois do entrypoint da imagem base, que já
# sobe nginx + php-fpm). Prepara a aplicação e entrega o processo ao supervisor.
set -euo pipefail
cd /var/www/html

log() { echo ">> [flowtree] $*"; }

# 1) .env: cria a partir do exemplo na primeira subida.
if [ ! -f .env ]; then
    log "Criando .env a partir do .env.example"
    cp .env.example .env
fi

# 2) Dependências (normalmente já vêm do host, montadas pelo volume).
if [ ! -f vendor/autoload.php ]; then
    log "vendor ausente — rodando composer install"
    composer install --no-interaction --prefer-dist --no-progress
fi

# 3) Chaves da aplicação e do JWT, se ainda vazias.
if ! grep -qE '^APP_KEY=.+' .env; then
    log "Gerando APP_KEY"
    php artisan key:generate --force --no-interaction
fi
if ! grep -qE '^JWT_SECRET=.+' .env; then
    log "Gerando JWT_SECRET"
    php artisan jwt:secret --force --no-interaction
fi

# 4) Espera o MySQL aceitar conexões (o healthcheck do compose cobre a maior parte).
DB_HOST_VALUE="${DB_HOST:-mysql}"
DB_PORT_VALUE="${DB_PORT:-3306}"
for i in $(seq 1 60); do
    if php -r "try { new PDO('mysql:host=${DB_HOST_VALUE};port=${DB_PORT_VALUE}', getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: ''); exit(0); } catch (Throwable \$e) { exit(1); }"; then
        break
    fi
    log "Aguardando MySQL em ${DB_HOST_VALUE}:${DB_PORT_VALUE} (${i}/60)…"
    sleep 2
done

# 5) Permissões de escrita (storage/cache) para o php-fpm.
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

# 6) Migrations sempre; seed só na primeira vez (banco sem usuários).
php artisan migrate --force --no-interaction
if [ "$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n1)" = "0" ]; then
    log "Banco vazio — rodando seed"
    php artisan db:seed --force --no-interaction
fi

# 7) Link público para uploads e cache de configuração/rotas.
php artisan storage:link --force --no-interaction >/dev/null 2>&1 || true
php artisan optimize --no-interaction

log "Pronto. Subindo supervisor (cron, fila, reverb)."
exec supervisord -c /etc/supervisor/supervisord.conf
