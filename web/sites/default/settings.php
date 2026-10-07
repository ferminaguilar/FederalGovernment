<?php

$settings['hash_salt'] = 'db49rLTZQqtm7YuXqWyp0GYE2Q63J/QI0e+ugtQVGtU=';

// 1. Basic Drupal Paths
$settings['config_sync_directory'] = '../config/sync';
$settings['skip_permissions_hardening'] = TRUE;

// 2. Production Database Configuration (MySQL)
$databases['default']['default'] = [
  'database' => getenv('DB_NAME') ?: 'fermpgjg_drupal11',
  'username' => getenv('DB_USER') ?: 'fermpgjg_fedgov',
  'password' => getenv('DB_PASS') ?: 'hestiz-zyrgy4-cyPvyf',
  'prefix' => '',
  'host' => getenv('DB_HOST') ?: 'localhost',
  'port' => getenv('DB_PORT') ?: '3306',
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'isolation_level' => 'READ COMMITTED',
];

// 3. Reverse Proxy & HTTPS Support
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
$settings['image_allow_insecure_derivatives'] = TRUE;

// 6. Local Settings Include (Loads MAMP settings.local.php locally)
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}

$settings['enable_html5_validation'] = TRUE;