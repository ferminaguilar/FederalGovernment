<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class UswdsHooks
{
    /**
     * Implements hook_theme().
     */
    #[Hook('theme')]
    public function theme($existing, $type, $theme, $path)
    {
        return [
            'government_banner' => [
                'path' => $path . '/templates/uswds',
                'variables' => [
                    'image_base' => NULL,
                    'military' => NULL,
                    'tld' => NULL,
                ],
            ],
        ];
    }
}
