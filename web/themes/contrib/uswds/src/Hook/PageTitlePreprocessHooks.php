<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class PageTitlePreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_page_title().
     */
    #[Hook('preprocess_page_title')]
    public function preprocessPageTitle(&$variables)
    {
        $variables['title_attributes']['class'][] = 'uswds-page-title';
    }
}
