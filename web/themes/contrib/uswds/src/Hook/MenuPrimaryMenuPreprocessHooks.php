<?php

namespace Drupal\uswds\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class MenuPrimaryMenuPreprocessHooks
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
    #[Hook('preprocess_menu__primary_menu')]
    public function preprocessMenuPrimaryMenu(&$variables)
    {
        $variables['megamenu'] = \Drupal\Component\Utility\DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(\Drupal\Core\Extension\ThemeSettingsProvider::class)->getSetting('uswds_header_mega'), fn() => theme_get_setting('uswds_header_mega'));
        // Display 2nd level as column headers for 3rd - Megamenu.
        $variables['mega_display_second'] = \Drupal\Component\Utility\DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(\Drupal\Core\Extension\ThemeSettingsProvider::class)->getSetting('uswds_header_mega_display_second'), fn() => theme_get_setting('uswds_header_mega_display_second'));
        // Do we want to duplicate parent items as the first child?
        $variables['duplicate_parent'] = \Drupal\Component\Utility\DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(\Drupal\Core\Extension\ThemeSettingsProvider::class)->getSetting('uswds_menu_accordion_duplicate_parent'), fn() => theme_get_setting('uswds_menu_accordion_duplicate_parent'));
    }
}
