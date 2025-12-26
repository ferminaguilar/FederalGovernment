#!/bin/bash

echo "🚀 Starting Drupal 11 environment prep..."

# 1. Increase PHP Memory Limit
echo "Updating PHP memory limits..."
echo "memory_limit=512M" | sudo tee /usr/local/etc/php/conf.d/90-limits.ini

# 2. Bypass MariaDB Version Requirement (10.6 -> 10.5)
# We target the Tasks.php file in the mysql driver module
TASKS_FILE="web/core/modules/mysql/src/Driver/Database/mysql/Install/Tasks.php"
if [ -f "$TASKS_FILE" ]; then
    echo "Bypassing MariaDB version check in Tasks.php..."
    sed -i "s/10.6.1/10.5.0/g" "$TASKS_FILE"
else
    echo "⚠️ Tasks.php not found at $TASKS_FILE"
fi

# 3. Remove GD and Zip from required extensions
# We target the system.install file
SYSTEM_INSTALL="web/core/modules/system/system.install"
if [ -f "$SYSTEM_INSTALL" ]; then
    echo "Removing GD/Zip requirements from system.install..."
    sed -i "/'gd',/d" "$SYSTEM_INSTALL"
    sed -i "/'zip',/d" "$SYSTEM_INSTALL"
else
    echo "⚠️ system.install not found at $SYSTEM_INSTALL"
fi

# 4. Prepare settings.php
# Create the directory and file if they don't exist, then add the bypass flag
mkdir -p web/sites/default
if [ ! -f "web/sites/default/settings.php" ]; then
    cp web/sites/default/default.settings.php web/sites/default/settings.php
    chmod 666 web/sites/default/settings.php
fi

echo "Adding bypass flags to settings.php..."
echo "\$settings['database_db_skip_version_check'] = TRUE;" >> web/sites/default/settings.php
echo "\$settings['reverse_proxy'] = TRUE;" >> web/sites/default/settings.php
echo "\$settings['reverse_proxy_addresses'] = [\$_SERVER['REMOTE_ADDR']];" >> web/sites/default/settings.php

echo "✅ Setup complete! You can now start your server:"
echo "php -S 0.0.0.0:8080 -t web"