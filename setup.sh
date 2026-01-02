#!/bin/bash

echo "🚀 Preparing Drupal 11 for SQLite Install..."

# --- 1. SQLite version patch (only if needed) ---
SQLITE_TASKS="web/core/modules/sqlite/src/Driver/Database/sqlite/Install/Tasks.php"
if [ -f "$SQLITE_TASKS" ]; then
    chmod 666 "$SQLITE_TASKS"
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

# --- 3. INJECT SETTINGS.PHP FIXES ---
SETTINGS="web/sites/default/settings.php"
echo "✨ Injecting Codespace-specific settings..."

cat <<EOF >> "$SETTINGS"

// --- Codespaces Environment Fixes ---
\$settings['reverse_proxy'] = TRUE;
\$settings['reverse_proxy_addresses'] = [\$_SERVER['REMOTE_ADDR']];
\$settings['trusted_host_patterns'] = ['.*'];
\$settings['skip_permissions_hardening'] = TRUE;
\$settings['config_sync_directory'] = '../config/sync';

// SQLite Database
\$databases['default']['default'] = [
  'database' => 'sites/default/files/.ht.sqlite',
  'prefix' => '',
  'driver' => 'sqlite',
  'namespace' => 'Drupal\\\\sqlite\\\\Driver\\\\Database\\\\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
  'journal_mode' => 'WAL',
];
EOF

# --- 4. COMPOSER & DRUSH ---
echo "📦 Finalizing environment..."

alias drush='/workspaces/FederalGovernment/vendor/bin/drush'

vendor/bin/drush updatedb -y || true
vendor/bin/drush cr || true

