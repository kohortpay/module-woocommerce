#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "$1" == apache2* ]] || [ "$1" = 'php-fpm' ]; then
        uid="$(id -u)"
        gid="$(id -g)"
        if [ "$uid" = '0' ]; then
                case "$1" in
                        apache2*)
                                user="${APACHE_RUN_USER:-www-data}"
                                group="${APACHE_RUN_GROUP:-www-data}"

                                # strip off any '#' symbol ('#1000' is valid syntax for Apache)
                                pound='#'
                                user="${user#$pound}"
                                group="${group#$pound}"
                                ;;
                        *) # php-fpm
                                user='www-data'
                                group='www-data'
                                ;;
                esac
        else
                user="$uid"
                group="$gid"
        fi

        if [ ! -e index.php ] && [ ! -e wp-includes/version.php ]; then
                # if the directory exists and WordPress doesn't appear to be installed AND the permissions of it are root:root, let's chown it (likely a Docker-created directory)
                if [ "$uid" = '0' ] && [ "$(stat -c '%u:%g' .)" = '0:0' ]; then
                        chown "$user:$group" .
                fi

                echo >&2 "WordPress not found in $PWD - copying now..."
                if [ -n "$(find -mindepth 1 -maxdepth 1 -not -name wp-content)" ]; then
                        echo >&2 "WARNING: $PWD is not empty! (copying anyhow)"
                fi
                sourceTarArgs=(
                        --create
                        --file -
                        --directory /usr/src/wordpress
                        --owner "$user" --group "$group"
                )
                targetTarArgs=(
                        --extract
                        --file -
                )
                if [ "$uid" != '0' ]; then
                        # avoid "tar: .: Cannot utime: Operation not permitted" and "tar: .: Cannot change mode to rwxr-xr-x: Operation not permitted"
                        targetTarArgs+=( --no-overwrite-dir )
                fi
                # loop over "pluggable" content in the source, and if it already exists in the destination, skip it
                # https://github.com/docker-library/wordpress/issues/506 ("wp-content" persisted, "akismet" updated, WordPress container restarted/recreated, "akismet" downgraded)
                for contentPath in \
                        /usr/src/wordpress/.htaccess \
                        /usr/src/wordpress/wp-content/*/*/ \
                ; do
                        contentPath="${contentPath%/}"
                        [ -e "$contentPath" ] || continue
                        contentPath="${contentPath#/usr/src/wordpress/}" # "wp-content/plugins/akismet", etc.
                        if [ -e "$PWD/$contentPath" ]; then
                                echo >&2 "WARNING: '$PWD/$contentPath' exists! (not copying the WordPress version)"
                                sourceTarArgs+=( --exclude "./$contentPath" )
                        fi
                done
                tar "${sourceTarArgs[@]}" . | tar "${targetTarArgs[@]}"
                echo >&2 "Complete! WordPress has been successfully copied to $PWD"
        fi

        wpEnvs=( "${!WORDPRESS_@}" )
        if [ ! -s wp-config.php ] && [ "${#wpEnvs[@]}" -gt 0 ]; then
                for wpConfigDocker in \
                        wp-config-docker.php \
                        /usr/src/wordpress/wp-config-docker.php \
                ; do
                        if [ -s "$wpConfigDocker" ]; then
                                echo >&2 "No 'wp-config.php' found in $PWD, but 'WORDPRESS_...' variables supplied; copying '$wpConfigDocker' (${wpEnvs[*]})"
                                # using "awk" to replace all instances of "put your unique phrase here" with a properly unique string (for AUTH_KEY and friends to have safe defaults if they aren't specified with environment variables)
                                awk '
                                        /put your unique phrase here/ {
                                                cmd = "head -c1m /dev/urandom | sha1sum | cut -d\\  -f1"
                                                cmd | getline str
                                                close(cmd)
                                                gsub("put your unique phrase here", str)
                                        }
                                        { print }
                                ' "$wpConfigDocker" > wp-config.php
                                if [ "$uid" = '0' ]; then
                                        # attempt to ensure that wp-config.php is owned by the run user
                                        # could be on a filesystem that doesn't allow chown (like some NFS setups)
                                        chown "$user:$group" wp-config.php || true
                                fi
                                break
                        fi
                done
        fi
fi

# Install WordPress with WP-CLI if it's not already installed 
if [ ! -e /var/www/html/.wp-installed ]; then
    echo "WP is not installed. Let's try installing it."
    wp core install --path=/var/www/html --url=$WORDPRESS_URL --title=$WORDPRESS_TITLE --admin_user=$WORDPRESS_ADMIN_USER --admin_password=$WORDPRESS_ADMIN_PASSWORD --admin_email=$WORDPRESS_ADMIN_EMAIL --allow-root
    
    echo "WP installed. Installing WooCommerce"
    #wp plugin install woocommerce --activate --allow-root
    #wp plugin install woocommerce --version=8.6.1 --activate --allow-root
    wp plugin install woocommerce --version=8.2.0 --activate --allow-root
    wp option set woocommerce_onboarding_opt_in "yes" --allow-root
    wp option set woocommerce_onboarding_profile "" --allow-root
    wp option set woocommerce_store_address "61 boulevard des dames" --allow-root
    wp option set woocommerce_store_address_2 "" --allow-root
    wp option set woocommerce_store_city "Marseille" --allow-root
    wp option set woocommerce_store_postcode "13002" --allow-root
    wp option set woocommerce_default_country "FR" --allow-root
    wp wc tool run install_pages --user=admin --allow-root
    chown -R www-data:www-data /var/www/html/wp-content/
    
    echo "WooCommerce installed. Installing WooCommerce Sample Data"
    wp plugin install wordpress-importer --activate --allow-root
    wp import /var/www/html/wp-content/plugins/woocommerce/sample-data/sample_products.xml --allow-root --user=admin --authors=skip

    touch /var/www/html/.wp-installed

    echo "Install KohortPay plugin"
    wp plugin install /tmp/kohortpay.zip --activate --allow-root
fi

exec "$@"


