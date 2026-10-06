<?php

$settings['hash_salt'] = 'db49rLTZQqtm7YuXqWyp0GYE2Q63J/QI0e+ugtQVGtU=';

// 1. Basic Drupal Paths
$settings['config_sync_directory'] = '../config/sync';
$settings['skip_permissions_hardening'] = TRUE;

// 2. Database Configuration (Default SQLite)
$databases['default']['default'] = [
  'database' => $app_root . '/' . $site_path . '/files/.ht.sqlite',
  'prefix' => '',
  'driver' => 'sqlite',
  'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
  'journal_mode' => 'WAL',
];

// 3. GitHub Codespaces / Reverse Proxy Support
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'];

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
  $_SERVER['HTTPS'] = 'on';
  $_SERVER['SERVER_PORT'] = 443;
}

// 4. Trusted Host Patterns
$settings['trusted_host_patterns'] = [
  '^govfed\.us$',
  '^www\.govfed\.us$',
  '^govfeds\.us$',
  '^www\.govfeds\.us$',
  '^.+\.govfed\.us$',
  '^.+\.govfeds\.us$',
  '^localhost(:[0-9]+)?$',
  '^127\.0\.0\.1(:[0-9]+)?$',
  '^.*\.app\.github\.dev$',
];

// 5. Performance/Environment Adjustments
$settings['cache']['bins']['render'] = 'cache.backend.memory';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.memory';
$settings['cache']['bins']['page'] = 'cache.backend.memory';
$settings['image_allow_insecure_derivatives'] = TRUE;

// 6. Local Settings Include
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
