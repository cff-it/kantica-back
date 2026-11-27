#!/bin/sh
# Démarrage du conteneur Railway : prépare le stockage, applique les migrations et les données de référence,
# met la configuration en cache, puis passe la main à supervisord (nginx, PHP-FPM, worker).
set -e

cd /var/www

# Le volume Railway monté sur storage/app arrive vide et appartient à root
mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

envsubst '${PORT}' < /etc/nginx/kantica.conf.template > /etc/nginx/http.d/default.conf

# Migrations, rôles, admin originel, voix : échoue (et arrête le déploiement) si une étape ne passe pas
su-exec www-data php artisan app:deploy
su-exec www-data php artisan optimize

exec "$@"
