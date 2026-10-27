<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\webform\WebformSubmissionForm;
use Drupal\Core\Hook\Attribute\Hook;
/**
 * Hook implementations for uswds.
 */
class FormFormHooks
{
    /**
     * Implements hook_form_alter().
     */
    #[Hook('form_alter')]
    public function formAlter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id)
    {
        // For public-facing pages, limit the width of forms.
        if (!\Drupal::service('router.admin_context')->isAdminRoute()) {
            // Let's exempt entity forms though; they should really be full width.
            $form_object = $form_state->getFormObject();
            if ($form_object instanceof \Drupal\Core\Entity\EntityFormInterface) {
                if (!$form_object instanceof \Drupal\webform\WebformSubmissionForm) {
                    $form['#attributes']['class'][] = 'uswds-form-full';
                    $form['#attributes']['class'][] = 'uswds-form-entity';
                    return;
                }
            }
            $form['#attributes']['class'][] = 'usa-form';
        }
    }
}
