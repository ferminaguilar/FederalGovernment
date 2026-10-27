<?php

namespace Drupal\uswds\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\block\Entity\Block;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class MenusHooks
{
    /**
     * Implements hook_theme_suggestions_HOOK_alter().
     */
    #[Hook('theme_suggestions_block_alter')]
    public function themeSuggestionsBlockAlter(array &$suggestions, array $variables)
    {
        // We need to suggest our custom theming for menu blocks in certain regions.
        if (in_array('block__system_menu_block', $suggestions)) {
            if (!empty($variables['elements']['#id'])) {
                $block = \Drupal\block\Entity\Block::load($variables['elements']['#id']);
                $menu_regions = [
                    'primary_menu',
                    'secondary_menu',
                    'sidebar_first',
                    'sidebar_second',
                    'footer_menu',
                ];
                if (is_object($block) && method_exists($block, 'getRegion') && in_array($block->getRegion(), $menu_regions) && _uswds_process_menu_region($block->getRegion())) {
                    $suggestion = 'block__system_menu_block__' . $block->getRegion();
                    if (!in_array($suggestion, $suggestions)) {
                        $suggestions[] = $suggestion;
                    }
                }
            }
        }
        return $suggestions;
    }
    /**
     * Implements hook_theme_suggestions_HOOK_alter().
     */
    #[Hook('theme_suggestions_menu_alter')]
    public function themeSuggestionsMenuAlter(array &$suggestions, array $variables)
    {
        if (!empty($variables['items'])) {
            foreach ($variables['items'] as $item) {
                if (!empty($item['#uswds_region'])) {
                    if (_uswds_process_menu_region($item['#uswds_region'])) {
                        $suggestion = 'menu__' . $item['#uswds_region'];
                        if (in_array($suggestion, $suggestions)) {
                            continue;
                        }
                        $suggestions[] = $suggestion;
                    }
                }
                // We only need to look at one.
                break;
            }
        }
        return $suggestions;
    }
}
