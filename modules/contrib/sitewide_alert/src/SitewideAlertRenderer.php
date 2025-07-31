<?php

namespace Drupal\sitewide_alert;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Sitewide Alert placeholder render array builder service.
 *
 * Used in default page_top display and by block submodule.
 */
class SitewideAlertRenderer implements SitewideAlertRendererInterface {

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   Admin context service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\sitewide_alert\SitewideAlertManager $sitewideAlertManager
   *   The sitewide alert manager service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    protected AdminContext $adminContext,
    protected AccountProxyInterface $currentUser,
    protected SitewideAlertManager $sitewideAlertManager,
  ) {
    $this->config = $configFactory->get('sitewide_alert.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function build(bool $adminAware = TRUE): array {
    $cacheMetadata = CacheableMetadata::createFromObject($this->config)
      ->addCacheContexts(['user.permissions']);

    // Do not show alert on admin pages if we are not configured to do so
    // or when we don't have enough permissions.
    if (!$this->currentUser->hasPermission('view published sitewide alert entities')
      || ($adminAware && !$this->config->get('show_on_admin') && $this->adminContext->isAdminRoute())) {
      // Populate an empty render array with cache-metadata to force it to
      // invalidate when settings change.
      $build = [];
      $cacheMetadata->applyTo($build);
      return $build;
    }

    // Check if an active sitewide_alert exists.
    if (!$this->sitewideAlertManager->activeSitewideAlerts()) {
      return [];
    }

    $build = [
      '#markup' => '<div data-sitewide-alert role="banner"></div>',
      '#attached' => [
        'library' => [
          'sitewide_alert/init',
        ],
        'drupalSettings' => [
          'sitewideAlert' => [
            'refreshInterval' => (int) ($this->config->get('refresh_interval') ?? 15) * 1000,
            'automaticRefresh' => ($this->config->get('automatic_refresh') == 1),
          ],
        ],
      ],
    ];
    $cacheMetadata->applyTo($build);
    return $build;
  }

}
