<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class MenuLocalTasksPreprocessHooks
{
    /**
     * @file
     * Preprocess function for this hook.
     */
    /**
     * Implements hook_preprocess_menu_local_tasks().
     */
    #[Hook('preprocess_menu_local_tasks')]
    public function preprocessMenuLocalTasks(&$variables)
    {
        $variables['attributes']['class'][] = 'usa-button-group';
        if (!empty($variables['primary'])) {
            foreach ($variables['primary'] as $menu_item_key => $menu_attributes) {
                $variables['primary'][$menu_item_key]['#attributes']['class'][] = 'usa-button-group__item';
                $variables['primary'][$menu_item_key]['#link']['localized_options']['attributes']['class'][] = 'usa-button';
                if (!empty($variables['primary'][$menu_item_key]['#active'])) {
                    $variables['primary'][$menu_item_key]['#link']['localized_options']['attributes']['class'][] = 'usa-button--active';
                }
            }
        }
        if (!empty($variables['secondary'])) {
            foreach ($variables['secondary'] as $menu_item_key => $menu_attributes) {
                $variables['secondary'][$menu_item_key]['#attributes']['class'][] = 'usa-button-group__item';
                $variables['secondary'][$menu_item_key]['#link']['localized_options']['attributes']['class'][] = 'usa-button';
                $variables['secondary'][$menu_item_key]['#link']['localized_options']['attributes']['class'][] = 'usa-button--secondary';
                if (!empty($variables['secondary'][$menu_item_key]['#active'])) {
                    $variables['secondary'][$menu_item_key]['#link']['localized_options']['attributes']['class'][] = 'usa-button--active';
                }
            }
        }
    }
}
