<?php

/**
 * @file
 * Hooks provided by the Message module.
 */

use Drupal\message\Entity\Message;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Act on a message that is being assembled before rendering.
 *
 * The module may add elements to $message->content prior to rendering. The
 * structure of $message->content is a renderable array as expected by
 * drupal_render().
 *
 * @param \Drupal\message\Entity\Message $message
 *   The message entity.
 * @param string $view_mode
 *   The view mode the message is rendered in.
 * @param string $langcode
 *   The language code used for rendering.
 *
 * @see hook_entity_prepare_view()
 * @see hook_entity_view()
 */
function hook_message_view(Message $message, $view_mode, $langcode) {
  $message->content['my_additional_field'] = [
    '#markup' => 'foo',
    '#weight' => 10,
    '#theme' => 'my_module_my_additional_field',
  ];
}

/**
 * Alter the results of entity_view() for messages.
 *
 * This hook is called after the content has been assembled in a structured
 * array and may be used for doing processing which requires that the complete
 * message content structure has been built.
 *
 * If the module wishes to act on the rendered HTML of the message rather than
 * the structured content array, it may use this hook to add a #post_render
 * callback. Alternatively, it could also implement hook_preprocess_message().
 * See drupal_render() and theme() documentation respectively for details.
 *
 * @param array $build
 *   A renderable array representing the message content.
 *
 * @see hook_entity_view_alter()
 */
function hook_message_view_alter(array &$build) {
  if ($build['#view_mode'] == 'full' && isset($build['an_additional_field'])) {
    // Change its weight.
    $build['an_additional_field']['#weight'] = -10;

    // Add a #post_render callback to act on the rendered HTML of the entity.
    $build['#post_render'][] = 'my_module_post_render';
  }
}

/**
 * Define default message template configurations.
 *
 * @return array
 *   An array of default message templates, keyed by machine names.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 *
 * @see hook_default_message_template_alter()
 */
function hook_default_message_template() {
  $defaults['main'] = \Drupal::entityTypeManager()->getStorage('message_template')->create([]);
  return $defaults;
}

/**
 * Alter default message template configurations.
 *
 * @param array $defaults
 *   An array of default message templates, keyed by machine names.
 *
 * @see hook_default_message_template()
 */
function hook_default_message_template_alter(array &$defaults) {
  $defaults['main']->name = 'custom name';
}

/**
 * Alter message template forms.
 *
 * Modules may alter the message template entity form by making use of this hook
 * or the entity bundle specific
 * hook_form_message_template_edit_BUNDLE_form_alter(). #entity_builders may be
 * used in order to copy the values of added form elements to the entity, just
 * as documented for entity_form_submit_build_entity().
 *
 * @param array $form
 *   Nested array of form elements that comprise the form.
 * @param array $form_state
 *   A keyed array containing the current state of the form.
 */
function hook_form_message_template_form_alter(array &$form, array &$form_state) {
  // Your alterations.
}

/**
 * Define default message template category configurations.
 *
 * @return array
 *   An array of default message template categories, keyed by machine names.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 *
 * @see hook_default_message_category_alter()
 */
function hook_default_message_category() {
  $defaults['main'] = \Drupal::entityTypeManager()->getStorage('message_category')->create([]);
  return $defaults;
}

/**
 * Alter default message template category configurations.
 *
 * @param array $defaults
 *   An array of default message template categories, keyed by machine names.
 *
 * @see hook_default_message_category()
 */
function hook_default_message_category_alter(array &$defaults) {
  $defaults['main']->name = 'custom name';
}

/**
 * @} End of "addtogroup hooks".
 */
