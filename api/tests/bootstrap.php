<?php

// Remove o config cacheado (gerado por `php artisan optimize`/`config:cache` no
// entrypoint do Docker) antes de os testes subirem. Sem isso, o Laravel ignora
// as env vars do phpunit.xml (DB sqlite em memória, cache array, broadcast null)
// e usa os valores do .env — e o RefreshDatabase apagaria o banco MySQL real.
$cachedConfig = __DIR__.'/../bootstrap/cache/config.php';
if (is_file($cachedConfig)) {
    @unlink($cachedConfig);
}

require __DIR__.'/../vendor/autoload.php';
