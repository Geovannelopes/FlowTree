#!/usr/bin/env bash
# FlowTree — sobe com Docker. URL única: http://localhost:9050 (front + /api + /docs + /storage + /app).
# A API também fica aberta direto em :5100. MySQL e Redis só na rede interna.
#
# Uso:
#   ./deploy.sh           deploy normal (migrate sempre; seed só se o banco estiver vazio)
#   ./deploy.sh --seed    roda também o db:seed (idempotente: não duplica dados)
#   ./deploy.sh --fresh   APAGA o banco e recria tudo (migrate:fresh --seed)
set -euo pipefail
cd "$(dirname "$0")"

API_CONTAINER="flowtree-api"

SEED=0
FRESH=0
for arg in "$@"; do
    case "$arg" in
        --seed)  SEED=1 ;;
        --fresh) FRESH=1 ;;
        -h|--help) sed -n '5,8p' "$0"; exit 0 ;;
        *) echo "!! Opção desconhecida: $arg (use --seed, --fresh ou --help)"; exit 1 ;;
    esac
done

if [ ! -f .env ]; then
    echo "!! Falta o .env da raiz (copie de .env.example e preencha as chaves do Reverb)."
    exit 1
fi

echo ">> Construindo imagens…"
# --build garante o rebuild do front (o bundle do Vite é gerado dentro da imagem) e da API.
docker compose build

echo ">> Instalando dependências do backend (composer do container)…"
# Roda num container avulso, ANTES de subir a API: o entrypoint do container já
# executa artisan (migrate/optimize) e um composer rodando em paralelo, via
# docker exec, é morto se o container reiniciar — deixando o vendor pela metade.
# Usa o composer da imagem de propósito: lá é PHP 8.2 com fileinfo/sodium e
# Composer 2.8. O composer do host não serve — o Laravel 12 exige
# composer-runtime-api ^2.2 e o servidor tem um Composer antigo (2.0.0).
docker compose run --rm --no-deps --entrypoint composer api install \
    --no-interaction --prefer-dist --no-dev --optimize-autoloader

echo ">> Subindo containers…"
# O entrypoint da API cuida do resto: permissões, migrate, seed (1ª vez) e optimize.
docker compose up -d --remove-orphans

echo ">> Aguardando a API ficar pronta…"
# O entrypoint só faz `exec supervisord` depois de migrar/semear/otimizar, então
# supervisord rodando = API pronta (o config.php em cache pode ser de um deploy antigo).
READY=0
for i in $(seq 1 60); do
    if docker exec "$API_CONTAINER" pgrep -x supervisord >/dev/null 2>&1; then
        READY=1
        break
    fi
    sleep 2
done
if [ "$READY" != "1" ]; then
    echo "!! A API não ficou pronta em 120s. Veja: docker logs $API_CONTAINER"
    exit 1
fi

if [ "$FRESH" = "1" ]; then
    echo ">> --fresh: apagando o banco e recriando com seed…"
    docker exec "$API_CONTAINER" php artisan migrate:fresh --seed --force --no-interaction
    docker exec "$API_CONTAINER" php artisan optimize --no-interaction
elif [ "$SEED" = "1" ]; then
    echo ">> --seed: rodando db:seed…"
    docker exec "$API_CONTAINER" php artisan db:seed --force --no-interaction
fi

echo ">> Deploy concluído: http://localhost:9050"
