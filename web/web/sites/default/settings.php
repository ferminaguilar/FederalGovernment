
// --- Codespaces Environment Fixes ---
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR']];
$settings['trusted_host_patterns'] = ['.*'];
$settings['skip_permissions_hardening'] = TRUE;
$settings['config_sync_directory'] = '../config/sync';

// Disable image toolkit requirement because GD is missing in this container
$config['system.image']['toolkit'] = ''; 

// SQLite Performance Fix
$databases['default']['default'] = [
  'database' => 'sites/default/files/.ht.sqlite',
  'prefix' => '',
  'driver' => 'sqlite',
  'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
  'journal_mode' => 'WAL',
];
