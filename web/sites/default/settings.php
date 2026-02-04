<?php

$settings['hash_salt'] = 'db49rLTZQqtm7YuXqWyp0GYE2Q63J/QI0e+ugtQVGtU=';

// 1. Basic Drupal Paths
$settings['config_sync_directory'] = '../config/sync';
$settings['skip_permissions_hardening'] = TRUE;

// 2. Database Configuration (SQLite)
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
// Trust the proxy IP
$settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR']];

// Handle HTTPS termination at the GitHub proxy
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
  $_SERVER['HTTPS'] = 'on';
  $_SERVER['SERVER_PORT'] = 443;
}

// 4. Trusted Host Patterns
$settings['trusted_host_patterns'] = [
  '^.*\.app\.github\.dev$',
  '^localhost$',
  '^127\.0\.0\.1$',
];

// 5. Performance/Environment Adjustments for Dev
$settings['cache']['bins']['render'] = 'cache.backend.memory';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.memory';
$settings['cache']['bins']['page'] = 'cache.backend.memory';
$settings['image_allow_insecure_derivatives'] = TRUE;

// 6. Local Settings Include
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
   include $app_root . '/' . $site_path . '/settings.local.php';
}