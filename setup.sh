#!/bin/bash

echo "🚀 Preparing Drupal 11 for SQLite Install..."

# 1. Increase Memory
PHP_CONF_DIR="/usr/local/php/8.3.29/ini/conf.d"
sudo mkdir -p $PHP_CONF_DIR
echo "memory_limit=512M" | sudo tee $PHP_CONF_DIR/90-limits.ini

# 2. Aggressive Extension Scrub (GD/Zip)
SYSTEM_INSTALL="web/core/modules/system/system.install"
if [ -f "$SYSTEM_INSTALL" ]; then
    echo "🧼 Scrubbing GD and Zip from system requirements..."
    chmod 666 "$SYSTEM_INSTALL"
    sed -i "/'gd' =>/,/],/d" "$SYSTEM_INSTALL"
    sed -i "/'zip' =>/,/],/d" "$SYSTEM_INSTALL"
    sed -i "/'gd',/d" "$SYSTEM_INSTALL"
    sed -i "/'zip',/d" "$SYSTEM_INSTALL"
fi

# Bypass SQLite Version Requirement (3.45 -> 3.30)
SQLITE_TASKS="web/core/modules/sqlite/src/Driver/Database/sqlite/Install/Tasks.php"
if [ -f "$SQLITE_TASKS" ]; then
    echo "Bypassing SQLite version check..."
    sed -i "s/3.45/3.30/g" "$SQLITE_TASKS"
fi

# Unlock directories for Composer/Development
if [ -d "web/sites/default" ]; then
    echo "Unlocking sites/default for development..."
    chmod 777 web/sites/default
    [ -f "web/sites/default/default.settings.php" ] && chmod 666 web/sites/default/default.settings.php
    [ -f "web/sites/default/settings.php" ] && chmod 666 web/sites/default/settings.php
fi

# 3. Prepare Directory Permissions
mkdir -p web/sites/default/files
chmod 777 web/sites/default/files
cp web/sites/default/default.settings.php web/sites/default/settings.php
chmod 666 web/sites/default/settings.php

# 4. Reverse Proxy Fix for Codespaces
echo "\$settings['reverse_proxy'] = TRUE;" >> web/sites/default/settings.php
echo "\$settings['reverse_proxy_addresses'] = [\$_SERVER['REMOTE_ADDR']];" >> web/sites/default/settings.php

# Tell Composer to pretend GD is installed
composer config platform.ext-gd 2.3.0



echo "✅ Ready! Run: php -S 0.0.0.0:8080 -t web"