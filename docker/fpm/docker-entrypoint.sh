#!/bin/sh
export COMPOSER_ALLOW_SUPERUSER=1
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

mkdir -p var/cache var/log var/tmp $ILIOS_STORAGE
if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	
	if [ "$ILIOS_DATABASE_URL" ]; then
		echo "Waiting for db to be ready..."
		bin/console ilios:wait-for-database
		echo "The db is now ready and reachable"

		bin/console cache:warmup
		bin/console doctrine:migrations:migrate --no-interaction
	fi

	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var

	if [ "$ILIOS_FILE_SYSTEM_STORAGE_PATH" ]; then
		setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX $ILIOS_FILE_SYSTEM_STORAGE_PATH
		setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX $ILIOS_FILE_SYSTEM_STORAGE_PATH
	fi
fi

exec docker-php-entrypoint "$@"