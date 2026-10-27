<?php

namespace Drupal\uswds\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class GovernmentBannerPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_government_banner().
     */
    #[Hook('preprocess_government_banner')]
    public function preprocessGovernmentBanner(&$variables)
    {
        $variables['military'] = \Drupal\Component\Utility\DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(\Drupal\Core\Extension\ThemeSettingsProvider::class)->getSetting('uswds_government_banner_mil'), fn() => theme_get_setting('uswds_government_banner_mil'));
        $variables['tld'] = $variables['military'] ? '.mil' : '.gov';
    }
}
