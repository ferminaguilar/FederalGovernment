<?php

namespace Drupal\layout_paragraphs\Controller;

use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_paragraphs\Ajax\LayoutParagraphsEventCommand;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutRefreshTrait;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Drupal\layout_paragraphs\Event\LayoutParagraphsDuplicateEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Drupal\layout_paragraphs\Utility\Dialog;
use Drupal\layout_paragraphs\Form\ModalConfirmForm;

/**
 * Class DuplicateController.
 *
 * Duplicates a component of a Layout Paragraphs Layout.
 */
class DuplicateController extends ControllerBase {

  use LayoutParagraphsLayoutRefreshTrait;
  use AjaxHelperTrait;

  /**
   * The tempstore service.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $tempstore;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(LayoutParagraphsLayoutTempstoreRepository $tempstore, EventDispatcherInterface $event_dispatcher) {
    $this->tempstore = $tempstore;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Duplicates a component and returns appropriate response.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs layout object.
   * @param string $source_uuid
   *   The source component to be cloned.
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   *   A build array or Ajax response.
   */
  public function duplicate(LayoutParagraphsLayout $layout_paragraphs_layout, string $source_uuid) {
    $this->setLayoutParagraphsLayout($layout_paragraphs_layout);

    // Duplicate the component first.
    $duplicate_component = $this->layoutParagraphsLayout->duplicateComponent($source_uuid);

    // Dispatch the duplicate event to allow other modules to modify or cancel.
    $event = new LayoutParagraphsDuplicateEvent($this->layoutParagraphsLayout, $source_uuid, $duplicate_component);
    $this->eventDispatcher->dispatch($event, LayoutParagraphsDuplicateEvent::EVENT_NAME);

    // Check if duplication was prevented by any event subscribers.
    if ($event->isDuplicationPrevented()) {
      // Remove the duplicated component from the layout since duplication
      // was prevented.
      $this->layoutParagraphsLayout->deleteComponent($duplicate_component->getEntity()->uuid());

      if ($this->isAjax()) {
        $response = new AjaxResponse();

        // Show the prevention message using ModalConfirmForm.
        $form = $this->formBuilder()->getForm(
          ModalConfirmForm::class,
          $this->layoutParagraphsLayout,
          $event->getMessage(),
        );

        $dialog_options = Dialog::dialogSettings($this->layoutParagraphsLayout);
        $dialog_options['width'] = 'fit-content';
        $dialog_options['height'] = 'fit-content';

        $response->addCommand(new OpenDialogCommand(
          Dialog::dialogSelector($this->layoutParagraphsLayout),
          $this->t('Duplication Not Permitted'),
          $form,
          $dialog_options
        ));

        return $response;
      }
      return [
        '#type' => 'layout_paragraphs_builder',
        '#layout_paragraphs_layout' => $layout_paragraphs_layout,
      ];
    }

    // Only save to tempstore if duplication wasn't prevented.
    $this->tempstore->set($this->layoutParagraphsLayout);

    if ($this->isAjax()) {
      return $this->successfulAjaxResponse($duplicate_component, $source_uuid);
    }

    return [
      '#type' => 'layout_paragraphs_builder',
      '#layout_paragraphs_layout' => $layout_paragraphs_layout,
    ];
  }

  /**
   * Checks if duplication was blocked by event subscribers.
   *
   * @param \Drupal\layout_paragraphs\Event\LayoutParagraphsDuplicateEvent $event
   *   The duplication event.
   *
   * @return bool
   *   TRUE if duplication was blocked, FALSE otherwise.
   */
  protected function isDuplicationBlocked(LayoutParagraphsDuplicateEvent $event): bool {
    return $event->isDuplicationPrevented();
  }

  /**
   * Builds Ajax response for successful duplication.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsComponent $duplicate_component
   *   The duplicated component.
   * @param string $source_uuid
   *   The source component UUID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  protected function successfulAjaxResponse($duplicate_component, string $source_uuid): AjaxResponse {
    $response = new AjaxResponse();

    if ($this->needsRefresh()) {
      return $this->refreshLayout($response);
    }

    $uuid = $duplicate_component->getEntity()->uuid();
    $rendered_item = [
      '#type' => 'layout_paragraphs_builder',
      '#layout_paragraphs_layout' => $this->layoutParagraphsLayout,
      '#uuid' => $uuid,
    ];
    $response->addCommand(new AfterCommand('[data-uuid="' . $source_uuid . '"]', $rendered_item));
    $response->addCommand(new LayoutParagraphsEventCommand($this->layoutParagraphsLayout, $uuid, 'component:insert'));

    return $response;
  }

}
