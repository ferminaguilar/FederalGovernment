<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class BaseThemesHooks
{
    /**
     * Implements hook_theme_registry_alter().
     */
    #[Hook('theme_registry_alter')]
    public function themeRegistryAlter(&$registry)
    {
        $trail = uswds_theme_trail();
        // For the purpose of this, we want the theme trail in reverse order: from
        // root base theme to active theme. This way we can let base themes run their
        // functions before the active theme.
        $trail = array_reverse($trail);
        foreach ($trail as $theme) {
            uswds_register_theme_hooks($registry, $theme);
        }
    }
}
