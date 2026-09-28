# Imagem customizada baseada em https://hub.docker.com/r/shinsenter/php
ARG PHP_VERSION=8.2
FROM inventorscompany/phpfpm-nginx:php${PHP_VERSION}

RUN apt-get update && \
    apt-get install -y cron supervisor default-mysql-client && \
    phpaddmod sockets && \
    rm -rf /var/lib/apt/lists/*

# Define UID e GID (www-data = usuário do host, evita arquivo root no volume)
ENV APP_UID=1000
ENV APP_GID=1000

# Configura o fuso horário
ENV TZ="America/Sao_Paulo"

ENV DOCUMENT_ROOT="/public"

# Desabilita execução automática de scripts da imagem base
ENV DISABLE_AUTORUN_GENERATING_INDEX="true"
ENV DISABLE_AUTORUN_COMPOSER_INSTALL="true"
ENV DISABLE_AUTORUN_SCRIPTS="true"

# Configurações do PHP
ENV PHP_MEMORY_LIMIT="512M"
ENV PHP_POST_MAX_SIZE="100M"
ENV PHP_UPLOAD_MAX_FILE_SIZE="100M"

# PHP-FPM Workers
ENV ENABLE_TUNING_FPM="0"
ENV PHP_PM_TYPE="dynamic"
ENV PHP_PM_MAX_CHILDREN="20"
ENV PHP_PM_START_SERVERS="5"
ENV PHP_PM_MIN_SPARE_SERVERS="3"
ENV PHP_PM_MAX_SPARE_SERVERS="10"
ENV PHP_PM_PROCESS_IDLE_TIMEOUT="10"

# Supervisor: cron, fila (Redis) e Reverb
COPY ./supervisord.conf /etc/supervisor/supervisord.conf

# Proxy de WebSocket do Reverb (incluído via /etc/nginx/custom.d/*.conf)
COPY ./docker/nginx/reverb.conf /etc/nginx/custom.d/10-reverb.conf

# Cron do scheduler
COPY ./cronjobs/cron /etc/cron.d/cron
RUN chmod 0644 /etc/cron.d/cron && crontab /etc/cron.d/cron

# Entrypoint da aplicação: espera o MySQL, migra, semeia (1ª vez) e sobe o supervisor
COPY ./docker/entrypoint.sh /usr/local/bin/flowtree-entrypoint
RUN chmod +x /usr/local/bin/flowtree-entrypoint

CMD ["/usr/local/bin/flowtree-entrypoint"]
