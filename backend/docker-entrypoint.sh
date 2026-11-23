#!/bin/sh
# Corre sempre que um container Laravel arranca.
set -e

# 1. Dependências (só na primeira vez; a pasta vendor/ fica no teu PC, ignorada pelo git)
if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

# 2. .env do serviço a partir do .env.example (só se ainda não existir)
if [ ! -f .env ]; then
  cp .env.example .env
fi

# 3. Chave da aplicação Laravel
if ! grep -q '^APP_KEY=base64' .env; then
  php artisan key:generate --force
fi

# 4. Migrações (só nas SAPIs com base de dados: RUN_MIGRATIONS=true no docker-compose)
if [ "$RUN_MIGRATIONS" = "true" ]; then
  php artisan migrate --force
fi

exec "$@"
