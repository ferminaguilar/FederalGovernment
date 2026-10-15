<?php

namespace Drupal\layout_paragraphs\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\Entity\TranslatableRevisionableInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\layout_paragraphs\Ajax\LayoutParagraphsEventCommand;
use Drupal\layout_paragraphs\LayoutChangeDetector;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs\LayoutParagraphsTranslationHandlerInterface;
use Drupal\layout_paragraphs\Utility\Dialog;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;

/**
 * Class LayoutParagraphsBuilderForm.
 *
 * Builds a Layout Paragraphs Builder form with save / cancel buttons
 * for saving the host entity.
 */
class LayoutParagraphsBuilderForm extends FormBase implements WorkspaceSafeFormInterface {

  /**
   * A layout paragraphs layout object.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $layoutParagraphsLayout;

  /**
   * The layout paragraphs layout tempstore service.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $tempstore;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The layout paragraphs translation handler.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsTranslationHandlerInterface
   */
  protected $translationHandler;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The layout change detector service.
   *
   * @var \Drupal\layout_paragraphs\LayoutChangeDetector
   */
  protected $changeDetector;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_builder_form';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    LayoutParagraphsLayoutTempstoreRepository $tempstore,
    EntityTypeManagerInterface $entity_type_manager,
    ?LayoutParagraphsTranslationHandlerInterface $translation_handler = NULL,
    ?FormBuilderInterface $form_builder = NULL,
    ?TimeInterface $time = NULL,
    ?LayoutChangeDetector $change_detector = NULL,
  ) {
    $this->tempstore = $tempstore;
    $this->entityTypeManager = $entity_type_manager;
    // Subclasses and direct instantiations that predate the translation
    // handler argument get the handler from the container.
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal, Drupal.Commenting.PostStatementComment.Found
    $this->translationHandler = $translation_handler ?? \Drupal::service('layout_paragraphs.translation_handler'); // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal, Drupal.Commenting.PostStatementComment.Found
    $this->formBuilder = $form_builder ?? \Drupal::formBuilder(); // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal, Drupal.Commenting.PostStatementComment.Found
    $this->time = $time ?? \Drupal::time(); // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal, Drupal.Commenting.PostStatementComment.Found
    $this->changeDetector = $change_detector ?? \Drupal::service('layout_paragraphs.change_detector'); // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('entity_type.manager'),
      $container->get('layout_paragraphs.translation_handler'),
      $container->get('form_builder'),
      $container->get('datetime.time'),
      $container->get('layout_paragraphs.change_detector')
    );
  }

  /**
   * Builds the layout paragraphs builder form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The parent entity that contains a layout.
   * @param string $field_name
   *   The name of the layout paragraphs field.
   * @param string $view_mode
   *   The view mode.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?ContentEntityInterface $entity = NULL,
    ?string $field_name = NULL,
    ?string $view_mode = NULL,
  ) {

    $parents = array_merge($form['#parents'] ?? [], ['layout_paragraphs_storage_key']);
    $input = $form_state->getUserInput();
    $layout_paragraphs_storage_key = NestedArray::getValue($input, $parents);

    // The route entity is the host translation that is edited and saved. The
    // layout cannot tell it: for an untranslatable reference field, the host
    // of its field items is always the default translation.
    $langcode = $entity->language()->getId();
    $is_translating = $this->translationHandler->isTranslating($entity, $langcode);

    // If the form is being rendered for the first time, save the Layout
    // Paragraphs Layout instance to tempstore and store the key.
    if (empty($layout_paragraphs_storage_key)) {
      $render_display = EntityViewDisplay::collectRenderDisplay($entity, $view_mode);
      $renderer = $render_display->getRenderer($field_name);
      $layout_paragraphs_settings = $renderer->getSettings() + ['reference_field_view_mode' => $view_mode];
      $this->layoutParagraphsLayout = new LayoutParagraphsLayout($entity->{$field_name}, $layout_paragraphs_settings);
      // The builder never adds a host translation, so paragraphs without a
      // translation fall back to their own language as the source.
      $this->translationHandler->initTranslations($this->layoutParagraphsLayout, $is_translating, $langcode);
      // The component routes have nothing but the layout to render the builder
      // from, so the translation mode is recorded on the layout.
      $this->layoutParagraphsLayout->setThirdPartySetting(
        'layout_paragraphs',
        'is_translating',
        $is_translating
      );
      // The language the layout's paragraphs are prepared for. The layout's
      // own data does not carry it, so the route binding validates a
      // submission against the recorded value.
      $this->layoutParagraphsLayout->setThirdPartySetting(
        'layout_paragraphs',
        'langcode',
        $langcode
      );
      $this->tempstore->set($this->layoutParagraphsLayout);
      $layout_paragraphs_storage_key = $this->tempstore->getStorageKey($this->layoutParagraphsLayout);
    }
    // On subsequent form renders, this loads the correct Layout Paragraphs
    // Layout from the tempstore using the storage key.
    else {
      $this->layoutParagraphsLayout = $this->tempstore->getWithStorageKey($layout_paragraphs_storage_key);
    }

    $form['layout_paragraphs_builder_ui'] = [
      '#type' => 'layout_paragraphs_builder',
      '#layout_paragraphs_layout' => $this->layoutParagraphsLayout,
      '#is_translating' => $is_translating,
    ];
    $form['layout_paragraphs_storage_key'] = [
      '#type' => 'hidden',
      '#default_value' => $layout_paragraphs_storage_key,
      // An element validator runs for every triggering element. A form-level
      // check would not: a button with its own #validate handlers runs those
      // instead, and a subclass may override validateForm() without calling
      // the parent method.
      '#element_validate' => ['::validateRouteBinding'],
    ];
    $form['#attributes']['data-lpb-form-id'] = Html::getId($this->layoutParagraphsLayout->id());
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#ajax' => [
          'callback' => '::save',
        ],
        '#attributes' => [
          'class' => ['button--primary'],
        ],
      ],
      'close' => [
        '#type' => 'button',
        '#value' => $this->t('Close'),
        '#ajax' => [
          'callback' => '::close',
        ],
        '#attributes' => [
          'class' => ['lpb-btn--cancel'],
        ],
      ],
    ];
    $form['actions']['#attributes']['class'][] = 'lpb-form__actions';

    return $form;
  }

  /**
   * Returns the response that opens the unsaved changes dialog.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  protected function openCloseDialog() {
    $close_form = $this->formBuilder->getForm(
      CloseLayoutForm::class,
      $this->layoutParagraphsLayout
    );
    return $this->openModalDialog($this->t('Unsaved changes'), $close_form);
  }

  /**
   * Returns the response that opens the save dialog.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  protected function openSaveDialog() {
    $save_form = $this->formBuilder->getForm(
      SaveLayoutForm::class,
      $this->layoutParagraphsLayout,
      $this->getNewRevisionDefault($this->layoutParagraphsLayout->getEntity())
    );
    return $this->openModalDialog($this->t('Save layout'), $save_form);
  }

  /**
   * Returns the response that opens a modal dialog.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The dialog title.
   * @param array $content
   *   The dialog content.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  protected function openModalDialog(TranslatableMarkup $title, array $content) {
    $options = Dialog::dialogSettings();
    $options['classes']['ui-dialog'] = $options['dialogClass'];
    unset($options['dialogClass']);
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand($title, $content, $options));
    return $response;
  }

  /**
   * Ajax callback.
   *
   * Closes the builder and returns the rendered layout, or displays the form
   * errors and leaves the builder open when the form has errors.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax command.
   */
  public function close(array $form, FormStateInterface $form_state) {
    if ($error_response = $this->buildErrorResponse($form_state)) {
      return $error_response;
    }
    $this->reloadLayout();
    $discarding = $this->layoutParagraphsLayout->getThirdPartySetting('layout_paragraphs', 'discard');
    if (!$discarding && $this->changeDetector->hasChanges($this->layoutParagraphsLayout)) {
      return $this->openCloseDialog();
    }
    $this->tempstore->delete($this->layoutParagraphsLayout);
    $view_mode = $this->layoutParagraphsLayout->getSetting('reference_field_view_mode', 'default');
    $rendered_layout = $this->layoutParagraphsLayout->getParagraphsReferenceField()->view($view_mode);
    $response = new AjaxResponse();
    $response->addCommand(new LayoutParagraphsEventCommand($this->layoutParagraphsLayout, '', 'builder:close'));
    $response->addCommand(new ReplaceCommand('[data-lpb-form-id="' . $form['#attributes']['data-lpb-form-id'] . '"]', $rendered_layout));
    return $response;
  }

  /**
   * Ajax callback.
   *
   * Displays a confirmation when the entity is saved, or the form errors when
   * it is not.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax command.
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($error_response = $this->buildErrorResponse($form_state)) {
      return $error_response;
    }
    if ($form_state->get('lpb_needs_save_dialog')) {
      return $this->openSaveDialog();
    }
    $entity = $this->getHostTranslation($form_state);
    $response = new AjaxResponse();
    $t_args = [
      '@type' => $entity->getEntityType()->getLabel(),
      '%title' => $entity->label(),
    ];
    $response->addCommand(new MessageCommand($this->t('@type %title has been updated.', $t_args)));
    $response->addCommand(new ReplaceCommand('[data-lpb-form-id="' . $form['#attributes']['data-lpb-form-id'] . '"]', $form));
    $response->addCommand(new LayoutParagraphsEventCommand($this->layoutParagraphsLayout, '', 'builder:save'));
    return $response;
  }

  /**
   * Element validator: binds the submission to the route entity.
   *
   * Rejects the submission unless the form is bound to the route entity, the
   * host entity translation the route access check ran on:
   * - the form was built with a host entity translation and a field name, the
   *   arguments the builder route passes;
   * - the host entity translation the form was built for has the route
   *   entity's entity type, ID and language;
   * - the layout's host entity has the route entity's entity type and ID;
   * - the layout's field, the field the form was built for, and the route's
   *   field are the same;
   * - for a translatable reference field, the layout's host entity also has
   *   the route entity's language. For an untranslatable one, it is always
   *   the default translation;
   * - the layout was prepared for the route entity's language. A layout that
   *   records no language, such as one written by another module or by an
   *   older version of this one, is rejected too.
   *
   * A form restored from the form cache keeps the host entity and field it
   * was built with, and the layout is the one loaded from the tempstore entry
   * named by the submitted storage key when the form was built, so none of
   * them necessarily matches the current route.
   *
   * Also rejects the submission when the stored revision of the layout's host
   * entity, or the entity itself, is gone or has no translation in the
   * language of the form's host entity, so no other translation is saved in
   * its place.
   *
   * The errors are recorded even where the triggering element limits
   * validation errors, since the check guards the save itself.
   *
   * @param array $element
   *   The element the validator is attached to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   */
  public function validateRouteBinding(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $error = NULL;
    if ($this->layoutParagraphsLayout instanceof LayoutParagraphsLayout && !is_string($this->layoutParagraphsLayout->getThirdPartySetting('layout_paragraphs', 'langcode'))) {
      $error = $this->t('This layout was opened before the site was updated. Reopen the builder and try again.');
    }
    elseif (!$this->isBuiltForRouteEntity($form_state)) {
      $error = $this->t('This layout was opened for another content item or translation. Reopen the builder and try again.');
    }
    else {
      $stored_host = $this->loadStoredHost();
      $langcode = $this->getHostTranslation($form_state)->language()->getId();
      if (!$stored_host || !$stored_host->hasTranslation($langcode)) {
        $error = $this->t('The content this layout was opened for is no longer available in this language. Reopen the builder and try again.');
      }
    }
    if ($error) {
      $limit_validation_errors = $form_state->getLimitValidationErrors();
      $form_state->setLimitValidationErrors(NULL);
      $form_state->setErrorByName('', $error);
      $form_state->setLimitValidationErrors($limit_validation_errors);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Saves the layout to its parent entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->reloadLayout();
    $form_state->set('lpb_needs_save_dialog', FALSE);
    $revision = $this->layoutParagraphsLayout->getThirdPartySetting('layout_paragraphs', 'revision');
    if ($revision === NULL) {
      if ($this->getRequest()->query->has(FormBuilderInterface::AJAX_FORM_REQUEST)) {
        $form_state->set('lpb_needs_save_dialog', TRUE);
        return;
      }
      $revision = [
        'create' => $this->getNewRevisionDefault($this->layoutParagraphsLayout->getEntity()),
        'log' => '',
      ];
    }
    $new_revision = !empty($revision['create']);
    $this->layoutParagraphsLayout->unsetThirdPartySetting('layout_paragraphs', 'revision');
    $this->tempstore->set($this->layoutParagraphsLayout);

    // The entity may have been altered by another process, and needs to be
    // loaded from storage to ensure edits to other fields are not overwritten.
    // @see https://www.drupal.org/project/layout_paragraphs/issues/3275179
    $entity = $this->loadStoredHost();
    if ($entity instanceof RevisionableInterface) {
      $entity->setNewRevision($new_revision);
    }
    // Storage returns the default translation. The translation to save is the
    // one the form was built for, which validation ensured exists.
    $host = $this->getHostTranslation($form_state);
    $langcode = $host->language()->getId();
    $entity = $entity->getTranslation($langcode);
    $field_name = $this->layoutParagraphsLayout->getFieldName();
    if ($entity instanceof EntityChangedInterface) {
      $entity->setChangedTime($this->time->getRequestTime());
    }
    if ($new_revision) {
      if ($entity instanceof RevisionLogInterface) {
        $entity->setRevisionCreationTime($this->time->getRequestTime());
        $entity->setRevisionLogMessage($revision['log'] ?? '');
        $entity->setRevisionUserId($this->currentUser()->id());
      }
      if ($entity instanceof TranslatableRevisionableInterface) {
        $entity->setRevisionTranslationAffected(TRUE);
      }
    }
    $entity->$field_name = $this->layoutParagraphsLayout->getParagraphsReferenceField();
    $entity->save();
    $this->layoutParagraphsLayout->setParagraphsReferenceField($entity->$field_name);
    // The saved field carries the new revision IDs, but for an untranslatable
    // reference field its paragraphs are in the default language. Prepare
    // them for the edited translation again, so that rebuilds and component
    // edits keep working on it.
    $is_translating = $this->translationHandler->isTranslating($host, $langcode);
    $this->translationHandler->initTranslations($this->layoutParagraphsLayout, $is_translating, $langcode);
    $this->tempstore->set($this->layoutParagraphsLayout);
  }

  /**
   * Reloads the layout from the tempstore.
   *
   * A cached form keeps the layout as it was when the form was cached.
   */
  protected function reloadLayout(): void {
    $this->layoutParagraphsLayout = $this->tempstore->get($this->layoutParagraphsLayout);
  }

  /**
   * Returns whether the host's bundle creates a new revision by default.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The host entity.
   *
   * @return bool
   *   TRUE if the bundle creates new revisions by default.
   */
  protected function getNewRevisionDefault(EntityInterface $entity): bool {
    $bundle_entity_type = $entity->getEntityType()->getBundleEntityType();
    if (!$bundle_entity_type) {
      return FALSE;
    }
    $bundle_entity = $this->entityTypeManager->getStorage($bundle_entity_type)->load($entity->bundle());
    return $bundle_entity instanceof RevisionableEntityBundleInterface && $bundle_entity->shouldCreateNewRevision();
  }

  /**
   * Determines whether the form and its layout belong to the route entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE if the form's host entity translation, the layout's host entity,
   *   the layout's field and the language the layout was prepared for match
   *   the route entity, the route entity's language, the route's field and
   *   the form's field, FALSE otherwise, including when the form was built
   *   without a host entity translation or a field name, the route has no
   *   entity or field, the form has no layout, or the layout records no
   *   language.
   *
   * @see \Drupal\layout_paragraphs\Form\LayoutParagraphsBuilderForm::validateRouteBinding()
   */
  protected function isBuiltForRouteEntity(FormStateInterface $form_state): bool {
    // The host entity translation and the field name the form was built for
    // are its first two build arguments. A caller that passes neither, or
    // passes something else in their place, leaves the submission unbound to
    // any entity, so it is rejected like a submission for another one.
    $args = $form_state->getBuildInfo()['args'] ?? [];
    if (!isset($args[0], $args[1]) || !$args[0] instanceof ContentEntityInterface) {
      return FALSE;
    }

    $route_entity = $this->getRouteMatch()->getParameter('entity');
    $layout = $this->layoutParagraphsLayout;
    if (!$route_entity instanceof ContentEntityInterface || !$layout instanceof LayoutParagraphsLayout) {
      return FALSE;
    }
    $langcode = $route_entity->language()->getId();

    $host = $this->getHostTranslation($form_state);
    if (!$this->isSameEntity($host, $route_entity) || $host->language()->getId() !== $langcode) {
      return FALSE;
    }

    $layout_host = $layout->getEntity();
    if (!$this->isSameEntity($layout_host, $route_entity)) {
      return FALSE;
    }

    $field_name = $layout->getFieldName();
    $route_field_name = $this->getRouteMatch()->getRawParameter('field_name');
    if (!is_string($route_field_name) || $field_name !== $route_field_name || $field_name !== $args[1]) {
      return FALSE;
    }
    if ($layout->getParagraphsReferenceField()->getFieldDefinition()->isTranslatable() && $layout_host->language()->getId() !== $langcode) {
      return FALSE;
    }

    // The language the layout's paragraphs were prepared for, recorded when
    // the layout was created. A layout that records none cannot be checked
    // against the route at all, so it is rejected as well.
    $layout_langcode = $layout->getThirdPartySetting('layout_paragraphs', 'langcode');
    if (!is_string($layout_langcode) || $layout_langcode !== $langcode) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Determines whether two entities have the same entity type and ID.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity.
   * @param \Drupal\Core\Entity\EntityInterface $other_entity
   *   The entity to compare it with.
   *
   * @return bool
   *   TRUE if both have the same entity type and ID, FALSE otherwise.
   */
  protected function isSameEntity(EntityInterface $entity, EntityInterface $other_entity): bool {
    return $entity->getEntityTypeId() === $other_entity->getEntityTypeId()
      && (string) $entity->id() === (string) $other_entity->id();
  }

  /**
   * Loads the layout's host entity from storage.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The default translation of the stored revision the layout was opened on,
   *   or of the stored entity if it is not revisionable. NULL if it no longer
   *   exists.
   */
  protected function loadStoredHost(): ?ContentEntityInterface {
    $entity = $this->layoutParagraphsLayout->getEntity();
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    if ($entity instanceof RevisionableInterface) {
      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $stored_entity = $storage->loadRevision($entity->getRevisionId());
    }
    else {
      $stored_entity = $storage->load($entity->id());
    }
    return $stored_entity instanceof ContentEntityInterface ? $stored_entity : NULL;
  }

  /**
   * Builds a response that displays the form errors.
   *
   * The error messages are moved from the messenger into the response, so
   * they are not displayed a second time on the next page.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|null
   *   The response, or NULL if the form has no errors.
   */
  protected function buildErrorResponse(FormStateInterface $form_state): ?AjaxResponse {
    if (!$form_state->getErrors()) {
      return NULL;
    }
    $response = new AjaxResponse();
    $clear_previous = TRUE;
    foreach ($this->messenger()->deleteByType(MessengerInterface::TYPE_ERROR) as $message) {
      $response->addCommand(new MessageCommand($message, NULL, ['type' => 'error'], $clear_previous));
      $clear_previous = FALSE;
    }
    return $response;
  }

  /**
   * Returns the host entity translation the form was built for.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The host entity translation passed to the form by the route.
   */
  protected function getHostTranslation(FormStateInterface $form_state): ContentEntityInterface {
    return $form_state->getBuildInfo()['args'][0];
  }

}
