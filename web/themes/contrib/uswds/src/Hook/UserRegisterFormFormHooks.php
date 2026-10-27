<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
/**
 * Hook implementations for uswds.
 */
class UserRegisterFormFormHooks
{
    use StringTranslationTrait;
    /**
     * Implements hook_form_FORM_ID_alter().
     */
    #[Hook('form_user_register_form_alter')]
    public function formUserRegisterFormAlter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id)
    {
        _uswds_container_to_details($form['account'], $this->t('Account settings'));
        if (isset($form['user_picture'])) {
            _uswds_container_to_details($form['user_picture'], $this->t('Picture'));
        }
    }
}
