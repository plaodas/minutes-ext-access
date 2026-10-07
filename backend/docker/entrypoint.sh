#!/bin/sh
set -eu

cd /var/www/html

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

echo "Waiting for MySQL..."
i=0
until php -r '
  $host = getenv("DB_HOST") ?: "mysql";
  $port = getenv("DB_PORT") ?: "3306";
  $db = getenv("DB_DATABASE") ?: "minutes";
  $user = getenv("DB_USERNAME") ?: "minutes";
  $pass = getenv("DB_PASSWORD") ?: "secret";
  new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass);
'; do
  i=$((i + 1))
  if [ "$i" -gt 30 ]; then
    echo "MySQL did not become ready" >&2
    exit 1
  fi
  sleep 2
done

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
  php artisan key:generate --force --no-interaction
fi

if ! grep -Eq '^JWT_SECRET=.+' .env; then
  php artisan jwt:secret --force --no-interaction
fi

php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

exec php artisan serve --host=0.0.0.0 --port=8000
