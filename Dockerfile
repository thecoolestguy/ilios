# This is the official Ilios ilios-php-apache Dockerfile which will build out an Ilios application server running on
# Apache httpd 2.4.x and PHP. It relies upon Docker images from the official PHP repository at Docker Hub at
# and will install the latest versions of PHP and Ilios by default. These versions can be overridden at build time by
# specifying the respective versions as argument on the command line, like so:
#
# docker build --build-arg "PHP_DOCKER_IMAGE=7.1.13-apache" --build-arg "ILIOS_VERSION_TAG=v3.42.0" -t ilios-apache-php .

# Initialize the arguments with a default value. These may be overridden at build time as shown in the example above.
ARG PHP_DOCKER_IMAGE=apache

# Begin with the official Composer image and name it 'composer' for reference
FROM composer AS composer

# get the proper 'PHP' image from the official PHP repo at
FROM php:$PHP_DOCKER_IMAGE

# Set up the default arguments so they can be overridden as needed and/or used as ENV vars later
# SSL Certificate settings
ARG SSL_ENABLED=false
# If SSL_ENABLE is true, set this to 'true' to enable the creation of a self-signed certificate and key
ARG SSL_CERT_SELFSIGNED=true
# if you already have custom SSL certs you want to use, include them in the source directory and set the filenames here
ARG SSL_CERT_FILENAME=server.crt
ARG SSL_KEY_FILENAME=server.key
# set your default ports for the webserver
ARG HTTP_PORT=80
ARG HTTPS_PORT=443
# Set to enable PHP debugging with xdebug
ARG PHP_XDEBUG_ENABLED=true
ARG PHP_XDEBUG_PORT=9000
# Set up the Composer/Symfony environment vars
ARG COMPOSER_HOME=/tmp
ARG SYMFONY_ENV=prod
# Now set the Ilios-specific environment variables
ARG ILIOS_API_ENVIRONMENT=prod
ARG ILIOS_API_DEBUG=false
ARG ILIOS_DATABASE_DRIVER=pdo_mysql
ARG ILIOS_DATABASE_HOST=db
ARG ILIOS_DATABASE_PORT=~
ARG ILIOS_DATABASE_NAME=ilios
ARG ILIOS_DATABASE_USER=ilios
ARG ILIOS_DATABASE_PASSWORD=ilios
ARG ILIOS_DATABASE_MYSQL_VERSION=5.7
ARG ILIOS_MAILER_TRANSPORT=smtp
ARG ILIOS_MAILER_HOST=127.0.0.1
ARG ILIOS_MAILER_USER=~
ARG ILIOS_MAILER_PASSWORD=~
ARG ILIOS_LOCALE=en
ARG ILIOS_SECRET=ThisTokenIsNotSoSecretChangeIt
ARG ILIOS_AUTHENTICATION_TYPE=form
ARG ILIOS_LEGACY_PASSWORD_SALT=null
ARG ILIOS_FILE_SYSTEM_STORAGE_PATH=/data
ARG ILIOS_INSTITUTION_DOMAIN=example.com
ARG ILIOS_SUPPORTING_LINK=null
ARG ILIOS_LDAP_AUTHENTICATION_HOST=null
ARG ILIOS_LDAP_AUTHENTICATION_PORT=null
ARG ILIOS_LDAP_AUTHENTICATION_BIND_TEMPLATE=null
ARG ILIOS_LDAP_DIRECTORY_URL=null
ARG ILIOS_LDAP_DIRECTORY_USER=null
ARG ILIOS_LDAP_DIRECTORY_PASSWORD=null
ARG ILIOS_LDAP_DIRECTORY_SEARCH_BASE=null
ARG ILIOS_LDAP_DIRECTORY_CAMPUS_ID_PROPERTY=null
ARG ILIOS_LDAP_DIRECTORY_USERNAME_PROPERTY=null
ARG ILIOS_SHIBBOLETH_AUTHENTICATION_LOGIN_PATH=null
ARG ILIOS_SHIBBOLETH_AUTHENTICATION_LOGOUT_PATH=null
ARG ILIOS_SHIBBOLETH_AUTHENTICATION_USER_ID_ATTRIBUTE=null
ARG ILIOS_TIMEZONE='America/Los_Angeles'
# This is for development, so SSL should be set to false by default
ARG ILIOS_REQUIRE_SECURE_CONNECTION=false
ARG ILIOS_KEEP_FRONTEND_UPDATED=true
ARG ILIOS_FRONTEND_RELEASE_VERSION=null
ARG ILIOS_CAS_AUTHENTICATION_SERVER=null
ARG ILIOS_CAS_AUTHENTICATION_VERSION=3
ARG ILIOS_CAS_AUTHENTICATION_VERIFY_SSL=false
ARG ILIOS_CAS_AUTHENTICATION_CERTIFICATE_PATH=null
ARG ILIOS_ENABLE_TRACKING=false
ARG ILIOS_TRACKING_CODE=UA-XXXXXXXX-1

# Now that all the 'FROM' values are set, set the maintainer
MAINTAINER Ilios Project Team <support@iliosproject.org>

COPY . /var/www/ilios

# copy the Composer PHAR from the Composer image into the apache-php image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# get/install all the PHP extensions required for Ilios and delete the source
# files after install
RUN apt-get update \
    && apt-get install -y \
    && apt-get install libldap2-dev -y \
    && apt-get install zlib1g-dev \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install ldap \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo_mysql \
    && mv /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/ \
    && rm -rf /var/lib/apt/lists/* \
    && pecl channel-update pecl.php.net \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && mv /etc/apache2/sites-enabled/000-default.conf /etc/apache2/sites-enabled/ilios.conf \
    && mv /etc/apache2/conf-available/docker-php.conf /etc/apache2/conf-enabled/ \
    # allow httpd overrides in the /var/www/ilios/web directory
    && sed -i -e 's|/var/www|/var/www/ilios/web|g' /etc/apache2/conf-enabled/docker-php.conf \
    # update the DocumentRoot to point to the '/var/www/ilios/web' directory
    && sed -i -e 's|/var/www/html|/var/www/ilios/web|g' /etc/apache2/sites-enabled/ilios.conf

# create the volume that will store the learning materials
VOLUME /data

# set all of the necessary environment variables using the arguments set above
ENV \
  SSL_ENABLED=${SSL_ENABLED} \
  SSL_CERT_SELFSIGNED=${SSL_CERT_SELFSIGNED} \
  SSL_CERT_FILENAME=${SSL_CERT_FILENAME} \
  SSL_KEY_FILENAME=${SSL_KEY_FILENAME} \
  HTTP_PORT=${HTTP_PORT} \
  HTTPS_PORT=${HTTPS_PORT} \
  PHP_XDEBUG_ENABLED=${PHP_XDEBUG_ENABLED} \
  PHP_XDEBUG_PORT=${PHP_XDEBUG_PORT} \
  COMPOSER_HOME=${COMPOSER_HOME} \
  SYMFONY_ENV=${SYMFONY_ENV} \
  ILIOS_API_ENVIRONMENT=${ILIOS_API_ENVIRONMENT} \
  ILIOS_API_DEBUG=${ILIOS_API_DEBUG} \
  ILIOS_DATABASE_DRIVER=${ILIOS_DATABASE_DRIVER} \
  ILIOS_DATABASE_HOST=${ILIOS_DATABASE_HOST} \
  ILIOS_DATABASE_PORT=${ILIOS_DATABASE_PORT} \
  ILIOS_DATABASE_NAME=${ILIOS_DATABASE_NAME} \
  ILIOS_DATABASE_USER=${ILIOS_DATABASE_USER} \
  ILIOS_DATABASE_PASSWORD=${ILIOS_DATABASE_PASSWORD} \
  ILIOS_DATABASE_MYSQL_VERSION=${ILIOS_DATABASE_MYSQL_VERSION} \
  ILIOS_MAILER_TRANSPORT=${ILIOS_MAILER_TRANSPORT} \
  ILIOS_MAILER_HOST=${ILIOS_MAILER_HOST} \
  ILIOS_MAILER_USER=${ILIOS_MAILER_USER} \
  ILIOS_MAILER_PASSWORD=${ILIOS_MAILER_PASSWORD} \
  ILIOS_LOCALE=${ILIOS_LOCALE} \
  ILIOS_SECRET=${ILIOS_SECRET} \
  ILIOS_AUTHENTICATION_TYPE=${ILIOS_AUTHENTICATION_TYPE} \
  ILIOS_LEGACY_PASSWORD_SALT=${ILIOS_LEGACY_PASSWORD_SALT} \
  ILIOS_FILE_SYSTEM_STORAGE_PATH=${ILIOS_FILE_SYSTEM_STORAGE_PATH} \
  ILIOS_INSTITUTION_DOMAIN=${ILIOS_INSTITUTION_DOMAIN} \
  ILIOS_SUPPORTING_LINK=${ILIOS_SUPPORTING_LINK} \
  ILIOS_LDAP_AUTHENTICATION_HOST=${ILIOS_LDAP_AUTHENTICATION_HOST} \
  ILIOS_LDAP_AUTHENTICATION_PORT=${ILIOS_LDAP_AUTHENTICATION_PORT} \
  ILIOS_LDAP_AUTHENTICATION_BIND_TEMPLATE=${ILIOS_LDAP_AUTHENTICATION_BIND_TEMPLATE} \
  ILIOS_LDAP_DIRECTORY_URL=${ILIOS_LDAP_DIRECTORY_URL} \
  ILIOS_LDAP_DIRECTORY_USER=${ILIOS_LDAP_DIRECTORY_USER} \
  ILIOS_LDAP_DIRECTORY_PASSWORD=${ILIOS_LDAP_DIRECTORY_PASSWORD} \
  ILIOS_LDAP_DIRECTORY_SEARCH_BASE=${ILIOS_LDAP_DIRECTORY_SEARCH_BASE} \
  ILIOS_LDAP_DIRECTORY_CAMPUS_ID_PROPERTY=${ILIOS_LDAP_DIRECTORY_CAMPUS_ID_PROPERTY} \
  ILIOS_LDAP_DIRECTORY_USERNAME_PROPERTY=${ILIOS_LDAP_DIRECTORY_USERNAME_PROPERTY} \
  ILIOS_SHIBBOLETH_AUTHENTICATION_LOGIN_PATH=${ILIOS_SHIBBOLETH_AUTHENTICATION_LOGIN_PATH} \
  ILIOS_SHIBBOLETH_AUTHENTICATION_LOGOUT_PATH=${ILIOS_SHIBBOLETH_AUTHENTICATION_LOGOUT_PATH} \
  ILIOS_SHIBBOLETH_AUTHENTICATION_USER_ID_ATTRIBUTE=${ILIOS_SHIBBOLETH_AUTHENTICATION_USER_ID_ATTRIBUTE} \
  ILIOS_TIMEZONE=${ILIOS_TIMEZONE} \
  ILIOS_REQUIRE_SECURE_CONNECTION=${ILIOS_REQUIRE_SECURE_CONNECTION} \
  ILIOS_KEEP_FRONTEND_UPDATED=${ILIOS_KEEP_FRONTEND_UPDATED} \
  ILIOS_FRONTEND_RELEASE_VERSION=${ILIOS_FRONTEND_RELEASE_VERSION} \
  ILIOS_CAS_AUTHENTICATION_SERVER=${ILIOS_CAS_AUTHENTICATION_SERVER} \
  ILIOS_CAS_AUTHENTICATION_VERSION=${ILIOS_CAS_AUTHENTICATION_VERSION} \
  ILIOS_CAS_AUTHENTICATION_VERIFY_SSL=${ILIOS_CAS_AUTHENTICATION_VERIFY_SSL} \
  ILIOS_CAS_AUTHENTICATION_CERTIFICATE_PATH=${ILIOS_CAS_AUTHENTICATION_CERTIFICATE_PATH} \
  ILIOS_ENABLE_TRACKING=${ILIOS_ENABLE_TRACKING} \
  ILIOS_TRACKING_CODE=${ILIOS_TRACKING_CODE}

# add all the extra directories necessary for the application
RUN \
    mkdir -p \
    /var/www/ilios/var \
    /var/www/ilios/var/cache \
    /var/www/ilios/var/logs \
    /var/www/ilios/var/session \
    /var/www/ilios/var/tmp \
    /var/www/ilios/vendor \
    # recursively change user/group ownership of the app root to 'www-data'
    && chown -R www-data:www-data /var/www/ilios \
    # give the www-data user a temporary shell in order to build the Ilios app
    && chsh -s /bin/bash www-data

# change to the context of the 'www-data' user
USER www-data

# as the 'www-data' user, build the app using composer and then remove it
RUN \
    /usr/bin/composer install \
    --working-dir /var/www/ilios \
    --prefer-dist \
    --no-dev \
    --no-progress \
    --no-interaction \
    --no-suggest \
    --classmap-authoritative

# switch back to the root user to finish up
USER root

WORKDIR /var/www/ilios

# revert the 'www-data' user's shell to its default
RUN \
    chsh -s /usr/sbin/nologin www-data \
    # update the frontend
    && bin/console ilios:maintenance:update-frontend --env=prod

# launch apache httpd as a foreground service
CMD ["apache2-foreground"]

# note the ports that should be exposed for the webserver and for PHP debugging (as set using the ARGS above)
# http is typically port 80
EXPOSE $HTTP_PORT
# https is typically port 443
EXPOSE $HTTPS_PORT
# xdebug is typically port 9000
EXPOSE $PHP_XDEBUG_PORT


