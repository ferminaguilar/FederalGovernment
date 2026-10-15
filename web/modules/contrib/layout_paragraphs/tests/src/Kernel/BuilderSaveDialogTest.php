<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the save and close dialogs of the frontend builder.
 *
 * @group layout_paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('layout_paragraphs')]
class BuilderSaveDialogTest extends KernelTestBase {

  use UserCreationTrait;
  use ParagraphsTestBaseTrait;

  /**
   * The query string of an AJAX form request.
   */
  protected const AJAX_QUERY = '?ajax_form=1&_wrapper_format=drupal_ajax';

  /**
   * The request time of the save requests.
   */
  protected const REQUEST_TIME = 1700000000;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'text',
    'node',
    'file',
    'field',
    'layout_discovery',
    'entity_reference_revisions',
    'paragraphs',
    'layout_paragraphs',
  ];

  /**
   * The user who created the node.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $author;

  /**
   * The user who edits the node in the builder.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $editor;

  /**
   * The node whose layout is edited.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The builder path of the node.
   *
   * @var string
   */
  protected $path;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['user']);

    $this->addParagraphsType('text');
    $this->addFieldtoParagraphType('text', 'field_text', 'string');
    $this->addParagraphedContentType('page', 'field_content', 'layout_paragraphs');
    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page')
      ->setComponent('field_content', ['type' => 'layout_paragraphs_builder'])
      ->save();
    $this->container->get('entity_display.repository')
      ->getViewDisplay('paragraph', 'text')
      ->setComponent('field_text', ['type' => 'string'])
      ->save();

    DateFormat::create([
      'id' => 'fallback',
      'pattern' => 'D, m/d/Y - H:i',
    ])->save();

    // Uid 1 bypasses access checks, so take it before the test users.
    $this->createUser();
    $this->author = $this->createUser(['access content', 'edit any page content']);
    $this->editor = $this->createUser(['access content', 'edit any page content']);

    $paragraph_storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $paragraphs = [];
    foreach (['First', 'Second'] as $text) {
      $paragraph = $paragraph_storage->create([
        'type' => 'text',
        'field_text' => $text,
      ]);
      $paragraph->save();
      $paragraphs[] = $paragraph;
    }
    $this->node = Node::create([
      'type' => 'page',
      'title' => 'Node title',
      'uid' => $this->author->id(),
      'revision_uid' => $this->author->id(),
      'revision_log' => 'Initial revision',
      'changed' => 1000,
      'field_content' => $paragraphs,
    ]);
    $this->node->save();

    $this->setCurrentUser($this->editor);
    $this->path = '/layout-paragraphs-builder/formatter/node/' . $this->node->id() . '/field_content/full';
  }

  /**
   * Tests that the first Save opens the dialog and saves nothing.
   */
  public function testSaveOpensDialogFirst(): void {
    $data = $this->openBuilder();
    $this->editComponentText($data['layout_paragraphs_storage_key'], 0, 'First edited');

    $commands = $this->postAjax($this->path, $data, 'Save');
    $dialog = $this->getCommand($commands, 'openDialog');
    $this->assertEquals('Save layout', $dialog['dialogOptions']['title']);
    $this->assertStringContainsString('name="revision" value="1" checked="checked"', $dialog['data']);
    $this->assertMatchesRegularExpression('/<textarea[^>]*name="revision_log"[^>]*><\/textarea>/', $dialog['data']);
    $this->assertStringContainsString('Briefly describe the changes you have made.', $dialog['data']);

    $node = $this->loadNode();
    $this->assertEquals($this->node->getRevisionId(), $node->getRevisionId());
    $this->assertEquals(['First', 'Second'], $this->loadTexts($node));
  }

  /**
   * Tests that the dialog defaults to the bundle's revision setting.
   */
  public function testDialogFollowsBundleRevisionSetting(): void {
    $this->setBundleNewRevision(FALSE);
    $data = $this->openBuilder();
    $this->editComponentText($data['layout_paragraphs_storage_key'], 0, 'First edited');

    $dialog = $this->getCommand($this->postAjax($this->path, $data, 'Save'), 'openDialog');
    $this->assertStringContainsString('name="revision" value="1" class="form-checkbox"', $dialog['data']);
    $this->assertStringNotContainsString('checked="checked"', $dialog['data']);
  }

  /**
   * Tests that the dialog says so when there is nothing to save.
   */
  public function testDialogWithoutChanges(): void {
    $data = $this->openBuilder();

    $dialog = $this->getCommand($this->postAjax($this->path, $data, 'Save'), 'openDialog');
    $this->assertStringContainsString('There are no changes to save.', $dialog['data']);
    $this->assertStringNotContainsString('name="revision"', $dialog['data']);
    $this->assertStringNotContainsString('value="Save"', $dialog['data']);
  }

  /**
   * Tests that a save with a new revision records its metadata.
   */
  public function testSaveCreatesNewRevision(): void {
    $data = $this->openBuilder();
    $this->editComponentText($data['layout_paragraphs_storage_key'], 0, 'First edited');

    $commands = $this->saveThroughDialog($data, TRUE, 'Edited the first text.');
    $this->assertStringContainsString('has been updated', $this->getCommand($commands, 'message')['message']);

    $node = $this->loadNode();
    $this->assertNotEquals($this->node->getRevisionId(), $node->getRevisionId());
    $this->assertEquals('Edited the first text.', $node->getRevisionLogMessage());
    $this->assertEquals($this->editor->id(), $node->getRevisionUserId());
    $this->assertEquals(self::REQUEST_TIME, $node->getRevisionCreationTime());
    $this->assertEquals(self::REQUEST_TIME, $node->getChangedTime());
    $this->assertTrue((bool) $node->isRevisionTranslationAffected());
    $this->assertEquals(['First edited', 'Second'], $this->loadTexts($node));

    $previous = $this->container->get('entity_type.manager')->getStorage('node')->loadRevision($this->node->getRevisionId());
    $this->assertEquals('Initial revision', $previous->getRevisionLogMessage());
    $this->assertEquals(['First', 'Second'], $this->loadTexts($previous));
  }

  /**
   * Tests that a save in place leaves the revision metadata alone.
   */
  public function testSaveInPlaceKeepsRevisionMetadata(): void {
    $data = $this->openBuilder();
    $this->editComponentText($data['layout_paragraphs_storage_key'], 0, 'First edited');

    $this->saveThroughDialog($data, FALSE, 'Ignored');

    $node = $this->loadNode();
    $this->assertEquals($this->node->getRevisionId(), $node->getRevisionId());
    $this->assertEquals('Initial revision', $node->getRevisionLogMessage());
    $this->assertEquals($this->author->id(), $node->getRevisionUserId());
    $this->assertEquals(self::REQUEST_TIME, $node->getChangedTime());
    $this->assertEquals(['First edited', 'Second'], $this->loadTexts($node));
  }

  /**
   * Tests that the answer covers one save only.
   */
  public function testAnswerIsSpentBySave(): void {
    $data = $this->openBuilder();
    $this->editComponentText($data['layout_paragraphs_storage_key'], 0, 'First edited');
    $this->saveThroughDialog($data, TRUE);

    $layout = $this->container->get('layout_paragraphs.tempstore_repository')
      ->getWithStorageKey($data['layout_paragraphs_storage_key']);
    $this->assertNull($layout->getThirdPartySetting('layout_paragraphs', 'revision'));

    $this->editComponentText($data['layout_paragraphs_storage_key'], 1, 'Second edited');
    $commands = $this->postAjax($this->path, $data, 'Save');
    $this->getCommand($commands, 'openDialog');
  }

  /**
   * Tests that a save without JavaScript follows the bundle setting.
   */
  public function testSaveWithoutJavaScriptFollowsBundleSetting(): void {
    $data = $this->openBuilder();
    $response = $this->doRequest(Request::create($this->path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(303, $response->getStatusCode());
    $node = $this->loadNode();
    $this->assertNotEquals($this->node->getRevisionId(), $node->getRevisionId());

    $this->setBundleNewRevision(FALSE);
    $data = $this->openBuilder();
    $response = $this->doRequest(Request::create($this->path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(303, $response->getStatusCode());
    $this->assertEquals($node->getRevisionId(), $this->loadNode()->getRevisionId());
  }

  /**
   * Tests that a cached builder form saves the layout in the tempstore.
   *
   * Asking to close caches the builder form. The layout is edited after that,
   * and saving the cached form saves the edit and does not ask again.
   */
  public function testCachedFormSavesCurrentLayout(): void {
    $data = $this->openBuilder();
    $storage_key = $data['layout_paragraphs_storage_key'];
    $this->editComponentText($storage_key, 0, 'First edited');

    $commands = $this->postAjax($this->path, $data, 'Close');
    $this->assertEquals('Unsaved changes', $this->getCommand($commands, 'openDialog')['dialogOptions']['title']);
    $data['form_build_id'] = $this->getCommand($commands, 'update_build_id')['new'];

    $this->editComponentText($storage_key, 1, 'Second edited');
    $commands = $this->saveThroughDialog($data, TRUE);
    $this->assertStringContainsString('has been updated', $this->getCommand($commands, 'message')['message']);
    $this->assertNotContains('openDialog', array_column($commands, 'command'));

    $this->assertEquals(['First edited', 'Second edited'], $this->loadTexts($this->loadNode()));
  }

  /**
   * Tests that closing without changes closes the builder.
   */
  public function testCloseWithoutChanges(): void {
    $data = $this->openBuilder();

    $commands = $this->postAjax($this->path, $data, 'Close');
    $this->assertNotContains('openDialog', array_column($commands, 'command'));
    $this->getCommand($commands, 'insert');
    $this->assertNull($this->container->get('layout_paragraphs.tempstore_repository')
      ->getWithStorageKey($data['layout_paragraphs_storage_key']));
  }

  /**
   * Tests that closing with changes asks first and keeps the layout.
   */
  public function testCloseWithChangesAsksFirst(): void {
    $data = $this->openBuilder();
    $this->editComponentText($data['layout_paragraphs_storage_key'], 0, 'First edited');

    $commands = $this->postAjax($this->path, $data, 'Close');
    $dialog = $this->getCommand($commands, 'openDialog');
    $this->assertEquals('Unsaved changes', $dialog['dialogOptions']['title']);
    $this->assertStringContainsString('Leave without saving', $dialog['data']);
    $this->assertStringContainsString('Continue editing', $dialog['data']);
    $this->assertNotContains('insert', array_column($commands, 'command'));
    $this->assertNotNull($this->container->get('layout_paragraphs.tempstore_repository')
      ->getWithStorageKey($data['layout_paragraphs_storage_key']));
  }

  /**
   * Tests that a discarded layout closes and shows the stored layout.
   */
  public function testCloseDiscardingClosesBuilder(): void {
    $data = $this->openBuilder();
    $storage_key = $data['layout_paragraphs_storage_key'];

    $tempstore = $this->container->get('layout_paragraphs.tempstore_repository');
    $layout = $tempstore->getWithStorageKey($storage_key);
    $layout->deleteComponent($layout->getParagraphsReferenceField()->get(1)->entity->uuid());
    $layout->setThirdPartySetting('layout_paragraphs', 'discard', TRUE);
    $tempstore->set($layout);

    $commands = $this->postAjax($this->path, $data, 'Close');
    $this->assertNotContains('openDialog', array_column($commands, 'command'));
    $insert = $this->getCommand($commands, 'insert');
    $this->assertStringContainsString('First', $insert['data']);
    $this->assertStringContainsString('Second', $insert['data']);
    $this->assertNull($tempstore->getWithStorageKey($storage_key));
    $this->assertEquals(['First', 'Second'], $this->loadTexts($this->loadNode()));
  }

  /**
   * Tests that the save dialog records the answer and saves the builder.
   */
  public function testSaveDialogRecordsAnswer(): void {
    $data = $this->openBuilder();
    $storage_key = $data['layout_paragraphs_storage_key'];
    $this->editComponentText($storage_key, 0, 'First edited');

    $dialog = $this->getCommand($this->postAjax($this->path, $data, 'Save'), 'openDialog');
    $save_path = '/layout-paragraphs-builder/' . $storage_key . '/save';
    $dialog_data = $this->getFormFields($dialog['data']) + [
      'revision' => 1,
      'revision_log' => 'From the dialog.',
    ];
    $commands = $this->postAjax($save_path, $dialog_data, 'Save');
    $this->getCommand($commands, 'closeDialog');
    $this->assertStringContainsString('.lpb-form__actions [data-drupal-selector="edit-submit"]', $this->getCommand($commands, 'invoke')['selector']);

    $layout = $this->container->get('layout_paragraphs.tempstore_repository')->getWithStorageKey($storage_key);
    $this->assertEquals([
      'create' => TRUE,
      'log' => 'From the dialog.',
    ], $layout->getThirdPartySetting('layout_paragraphs', 'revision'));
  }

  /**
   * Tests that the close dialog marks the layout as discarded.
   */
  public function testCloseDialogMarksLayoutDiscarded(): void {
    $data = $this->openBuilder();
    $storage_key = $data['layout_paragraphs_storage_key'];
    $this->editComponentText($storage_key, 0, 'First edited');

    $dialog = $this->getCommand($this->postAjax($this->path, $data, 'Close'), 'openDialog');
    $close_path = '/layout-paragraphs-builder/' . $storage_key . '/close';
    $commands = $this->postAjax($close_path, $this->getFormFields($dialog['data']), 'Leave without saving');
    $this->getCommand($commands, 'closeDialog');
    $this->assertStringContainsString('.lpb-btn--cancel', $this->getCommand($commands, 'invoke')['selector']);

    $layout = $this->container->get('layout_paragraphs.tempstore_repository')->getWithStorageKey($storage_key);
    $this->assertTrue($layout->getThirdPartySetting('layout_paragraphs', 'discard'));
  }

  /**
   * Tests what the change detector reports as a change.
   */
  public function testChangeDetector(): void {
    $detector = $this->container->get('layout_paragraphs.change_detector');
    $tempstore = $this->container->get('layout_paragraphs.tempstore_repository');
    $storage_key = $this->openBuilder()['layout_paragraphs_storage_key'];

    $this->assertFalse($detector->hasChanges($tempstore->getWithStorageKey($storage_key)));

    $layout = $tempstore->getWithStorageKey($storage_key);
    $uuids = array_map(fn ($component) => $component->getEntity()->uuid(), $layout->getComponents());
    $layout->reorderComponents(array_map(fn ($uuid) => [
      'uuid' => $uuid,
      'parentUuid' => NULL,
      'region' => NULL,
    ], array_reverse($uuids)));
    $this->assertTrue($detector->hasChanges($layout));

    $layout = $tempstore->getWithStorageKey($storage_key);
    $layout->deleteComponent($uuids[1]);
    $this->assertTrue($detector->hasChanges($layout));

    $layout = $tempstore->getWithStorageKey($storage_key);
    $layout->appendComponent($this->container->get('entity_type.manager')->getStorage('paragraph')->create([
      'type' => 'text',
      'field_text' => 'Third',
    ]));
    $this->assertTrue($detector->hasChanges($layout));

    $this->editComponentText($storage_key, 0, 'First edited');
    $this->assertTrue($detector->hasChanges($tempstore->getWithStorageKey($storage_key)));
  }

  /**
   * Sets whether pages create a new revision by default.
   *
   * @param bool $new_revision
   *   Whether to create a new revision by default.
   */
  protected function setBundleNewRevision(bool $new_revision): void {
    $node_type = NodeType::load('page');
    $node_type->setNewRevision($new_revision);
    $node_type->save();
  }

  /**
   * Opens the builder and returns its form fields.
   *
   * @return array
   *   The form build ID, form ID, form token and layout storage key.
   */
  protected function openBuilder(): array {
    $response = $this->doRequest(Request::create($this->path));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $data = [];
    foreach (['form_build_id', 'form_id', 'form_token', 'layout_paragraphs_storage_key'] as $name) {
      $inputs = $this->cssSelect('input[name="' . $name . '"]');
      $this->assertCount(1, $inputs);
      $data[$name] = (string) $inputs[0]->attributes()->value[0];
    }
    return $data;
  }

  /**
   * Saves the builder through its Save button and the save dialog.
   *
   * @param array $data
   *   The builder form fields.
   * @param bool $new_revision
   *   Whether to create a new revision.
   * @param string $log
   *   The revision log message.
   *
   * @return array
   *   The AJAX commands of the second Save.
   */
  protected function saveThroughDialog(array $data, bool $new_revision, string $log = ''): array {
    $this->getCommand($this->postAjax($this->path, $data, 'Save'), 'openDialog');

    $tempstore = $this->container->get('layout_paragraphs.tempstore_repository');
    $layout = $tempstore->getWithStorageKey($data['layout_paragraphs_storage_key']);
    $layout->setThirdPartySetting('layout_paragraphs', 'revision', [
      'create' => $new_revision,
      'log' => $log,
    ]);
    $tempstore->set($layout);

    return $this->postAjax($this->path, $data, 'Save');
  }

  /**
   * Edits the text of a component in the tempstore.
   *
   * @param string $storage_key
   *   The layout's tempstore storage key.
   * @param int $delta
   *   The delta of the component.
   * @param string $text
   *   The new text.
   */
  protected function editComponentText(string $storage_key, int $delta, string $text): void {
    $tempstore = $this->container->get('layout_paragraphs.tempstore_repository');
    $layout = $tempstore->getWithStorageKey($storage_key);
    $paragraph = $layout->getParagraphsReferenceField()->get($delta)->entity;
    $paragraph->set('field_text', $text);
    $paragraph->setNeedsSave(TRUE);
    $layout->setComponent($paragraph);
    $tempstore->set($layout);
  }

  /**
   * Posts a form through one of its buttons as an AJAX request.
   *
   * @param string $path
   *   The form path, without the AJAX query parameters.
   * @param array $data
   *   The form fields.
   * @param string $button
   *   The value of the button.
   *
   * @return array
   *   The AJAX commands of the response.
   */
  protected function postAjax(string $path, array $data, string $button): array {
    $request = Request::create($path . self::AJAX_QUERY, 'POST', $data + [
      'op' => $button,
      '_triggering_element_name' => 'op',
      '_triggering_element_value' => $button,
    ], [], [], ['REQUEST_TIME' => self::REQUEST_TIME]);
    $request->headers->set('Accept', 'application/json, text/javascript, */*; q=0.01');
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    $response = $this->doRequest($request);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    return json_decode((string) $response->getContent(), TRUE);
  }

  /**
   * Returns the first AJAX command of a type.
   *
   * @param array $commands
   *   The AJAX commands.
   * @param string $name
   *   The command name.
   *
   * @return array
   *   The command.
   */
  protected function getCommand(array $commands, string $name): array {
    foreach ($commands as $command) {
      if ($command['command'] === $name) {
        return $command;
      }
    }
    $this->fail(sprintf('No "%s" command in: %s', $name, implode(', ', array_column($commands, 'command'))));
  }

  /**
   * Returns the hidden form fields of the form in a dialog.
   *
   * @param string $html
   *   The dialog markup.
   *
   * @return array
   *   The form build ID, form ID and form token.
   */
  protected function getFormFields(string $html): array {
    $this->setRawContent($html);
    $data = [];
    foreach (['form_build_id', 'form_id', 'form_token'] as $name) {
      $inputs = $this->cssSelect('input[name="' . $name . '"]');
      $this->assertCount(1, $inputs);
      $data[$name] = (string) $inputs[0]->attributes()->value[0];
    }
    return $data;
  }

  /**
   * Loads the stored node.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function loadNode(): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    $this->container->get('entity.memory_cache')->deleteAll();
    return $storage->load($this->node->id());
  }

  /**
   * Loads the texts of a node revision's components, in order.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node revision.
   *
   * @return string[]
   *   The texts.
   */
  protected function loadTexts(NodeInterface $node): array {
    $paragraph_storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $texts = [];
    foreach ($node->get('field_content') as $item) {
      $texts[] = $paragraph_storage->loadRevision($item->target_revision_id)->get('field_text')->value;
    }
    return $texts;
  }

  /**
   * Handles a request in the test's HTTP kernel.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  protected function doRequest(Request $request): Response {
    $this->container->get('entity.memory_cache')->deleteAll();
    $response = $this->container->get('http_kernel')->handle($request);
    $this->setRawContent((string) $response->getContent());
    return $response;
  }

}
