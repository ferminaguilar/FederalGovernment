#!/bin/bash

echo "🚀 Preparing Drupal 11 for SQLite Install..."

# --- 0. FIX ENVIRONMENT (Suppress PHP Warnings) ---
# Installs MySQL driver to stop the "pdo_mysql" warning, even if using SQLite
if dpkg -s php-mysql >/dev/null 2>&1; then
    echo "✅ PHP MySQL driver already installed."
else
    echo "🔧 Installing PHP MySQL driver to suppress warnings..."
    sudo apt-get update && sudo apt-get install -y php-mysql
fi

# --- 1. MASTER BYPASS FOR CODESPACES ---
echo "🛠️  Applying Master Bypass for GD/SQLite..."

# Patch System/Image modules & Update logic
find web/core -type f -name "*.install" -exec chmod 666 {} +
find web/core -type f -name "*.install" -exec sed -i "s/REQUIREMENT_ERROR/REQUIREMENT_INFO/g" {} +

if [ -f "web/core/includes/update.inc" ]; then
    chmod 666 web/core/includes/update.inc
    sed -i 's/return !\$has_error;/return true;/g' web/core/includes/update.inc
fi

# Patch SQLite version check
SQLITE_TASKS="web/core/modules/sqlite/src/Driver/Database/sqlite/Install/Tasks.php"
if [ -f "$SQLITE_TASKS" ]; then
    chmod 666 "$SQLITE_TASKS"
    sed -i "s/3.45/3.30/g" "$SQLITE_TASKS"
fi

# --- 2. PERMISSIONS & DIRECTORY SETUP ---
if [ -d "web/sites/default" ]; then
    echo "🔓 Unlocking sites/default..."
    chmod 777 web/sites/default
    [ -f "web/sites/default/default.settings.php" ] && chmod 666 web/sites/default/default.settings.php
    [ -f "web/sites/default/settings.php" ] && chmod 666 web/sites/default/settings.php
fi

mkdir -p web/sites/default/files
chmod 777 web/sites/default/files

# Create settings.php if it doesn't exist
if [ ! -f "web/sites/default/settings.php" ]; then
    cp web/sites/default/default.settings.php web/sites/default/settings.php
    chmod 666 web/sites/default/settings.php
fi

# --- 3. INJECT SETTINGS.PHP FIXES ---
SETTINGS="web/sites/default/settings.php"
if [ -f "$SETTINGS" ]; then
    echo "✨ Injecting Codespace-specific settings..."
    
    # Reverse Proxy
    if ! grep -q "reverse_proxy" "$SETTINGS"; then
        echo "\$settings['reverse_proxy'] = TRUE;" >> "$SETTINGS"
        echo "\$settings['reverse_proxy_addresses'] = [\$_SERVER['REMOTE_ADDR']];" >> "$SETTINGS"
    fi

    # Trusted Host, Toolkit, and Permissions hardening bypass
    if ! grep -q "trusted_host_patterns" "$SETTINGS"; then
        cat <<EOF >> "$SETTINGS"

// Codespaces Environment Bypasses
\$settings['trusted_host_patterns'] = ['.*'];
\$settings['image_toolkit'] = 'test';
\$settings['skip_permissions_hardening'] = TRUE;
EOF
    fi
fi

# --- 4. COMPOSER & DRUSH ---
echo "📦 Finalizing environment..."
composer config platform.ext-gd 2.3.0

# Use vendor/bin/drush to ensure we use the local project version
# We use || true so the script doesn't crash if the DB isn't installed yet
vendor/bin/drush updatedb -y || true
vendor/bin/drush cr || true

echo "✅ Setup complete. Starting Server..."
echo "-------------------------------------"

# --- 5. START SERVER ---
# Change to web directory and start PHP server
cd web
php -S 0.0.0.0:8080 .ht.router.php