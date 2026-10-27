<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class NodeFormHooks
{
    /**
     * Implements hook_form_node_form_alter().
     */
    #[Hook('form_node_form_alter')]
    public function formNodeFormAlter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id)
    {
        // Make the delete button on node forms pop.
        $form['actions']['delete']['#attributes']['class'][] = 'usa-button';
        $form['actions']['delete']['#attributes']['class'][] = 'usa-button--secondary';
    }
}
