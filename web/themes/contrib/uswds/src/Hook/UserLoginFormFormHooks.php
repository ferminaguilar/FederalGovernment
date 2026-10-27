<?php

namespace Drupal\uswds\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
/**
 * Hook implementations for uswds.
 */
class UserLoginFormFormHooks
{
    use StringTranslationTrait;
    /**
     * Implements hook_form_FORM_ID_alter().
     */
    #[Hook('form_user_login_form_alter')]
    public function formUserLoginFormAlter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id)
    {
        // Remove unnecessary help text.
        unset($form['name']['#description']);
        unset($form['pass']['#description']);
        // Allow logging in with username.
        $form['name']['#title'] = $this->t('Username');
    }
}
