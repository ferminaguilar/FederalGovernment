<?php

namespace Drupal\uswds\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class MenuFooterMenuPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_menu__REGION().
     *
     * This made possible by our custom theme suggestion.
     *
     * @see uswds_theme_suggestions_menu_alter()
     */
    #[Hook('preprocess_menu__footer_menu')]
    public function preprocessMenuFooterMenu(&$variables)
    {
        $variables['footer_style'] = \Drupal\Component\Utility\DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(\Drupal\Core\Extension\ThemeSettingsProvider::class)->getSetting('uswds_footer_style'), fn() => theme_get_setting('uswds_footer_style'));
    }
}
