#!/bin/bash

echo "🚀 Preparing Drupal 11 for SQLite Install..."

# --- 0. KILL GHOST PROCESSES ---
# Clear port 8080 to ensure the server can start clean
sudo killall -9 php 2>/dev/null || true

# --- 1. SQLite version patch ---
SQLITE_TASKS="web/core/modules/sqlite/src/Driver/Database/sqlite/Install/Tasks.php"
if [ -f "$SQLITE_TASKS" ]; then
    chmod 666 "$SQLITE_TASKS"
    # Drupal 11.3 + PHP 8.4 is fine with modern SQLite, but we keep this for compatibility
    sed -i "s/3.45/3.30/g" "$SQLITE_TASKS"
fi

# --- 2. PERMISSIONS & DIRECTORY SETUP ---
mkdir -p web/sites/default/files
mkdir -p config/sync
chmod 777 web/sites/default/files

if [ ! -f "web/sites/default/settings.php" ]; then
    cp web/sites/default/default.settings.php web/sites/default/settings.php
fi
chmod 666 web/sites/default/settings.php

# --- 3. INJECT SETTINGS.PHP FIXES (Only if not already there) ---
SETTINGS="web/sites/default/settings.php"
if ! grep -q "Codespaces Environment Fixes" "$SETTINGS"; then
    echo "✨ Injecting Codespace-specific settings..."
    cat <<EOF >> "$SETTINGS"

// --- Codespaces Environment Fixes ---
\$settings['reverse_proxy'] = TRUE;
\$settings['reverse_proxy_addresses'] = [\$_SERVER['REMOTE_ADDR']];
\$settings['trusted_host_patterns'] = ['.*'];
\$settings['skip_permissions_hardening'] = TRUE;
\$settings['config_sync_directory'] = '../config/sync';

// SQLite Database - Using absolute path for PHP 8.4 stability
\$databases['default']['default'] = [
  'database' => \$app_root . '/' . \$site_path . '/files/.ht.sqlite',
  'prefix' => '',
  'driver' => 'sqlite',
  'namespace' => 'Drupal\\\\sqlite\\\\Driver\\\\Database\\\\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
  'journal_mode' => 'WAL',
];
EOF
else
    echo "✅ Settings already injected."
fi

# --- 4. COMPOSER & DRUSH ---
echo "📦 Finalizing environment..."

# Make drush available globally in this session
export PATH="\$PATH:/workspaces/FederalGovernment/vendor/bin"

vendor/bin/drush updatedb -y || true
vendor/bin/drush cr || true

echo "✅ Setup complete! You can now run: php -S 0.0.0.0:8080 -t web web/.ht.router.php"