<?php

namespace Drupal\classy\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\Form\ViewsForm;

/**
 * Hook implementations for classy.
 */
class ClassyHooks {

  /**
   * Implements hook_preprocess_links__media_library_menu().
   *
   * This targets the menu of available media types in the media library's modal
   * dialog.
   *
   * @todo Do this in the relevant template once
   *   https://www.drupal.org/project/drupal/issues/3088856 is resolved.
   */
  #[Hook('preprocess_links__media_library_menu')]
  public static function preprocessLinksMediaLibraryMenu(array &$variables): void {
    foreach ($variables['links'] as &$link) {
      $link['link']['#options']['attributes']['class'][] = 'media-library-menu__link';
    }
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public static function formAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof ViewsForm && str_starts_with($form_object->getBaseFormId(), 'views_form_media_library')) {
      $form['#attributes']['class'][] = 'media-library-views-form';
    }
  }

  /**
   * Implements hook_preprocess_image_widget().
   */
  #[Hook('preprocess_image_widget')]
  public static function preprocessImageWidget(array &$variables): void {
    $data =& $variables['data'];
    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

}
