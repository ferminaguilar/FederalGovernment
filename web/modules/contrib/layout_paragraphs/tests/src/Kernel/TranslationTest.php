<?php

namespace Drupal\Tests\layout_paragraphs\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\layout_paragraphs\Form\LayoutParagraphsBuilderForm;
use Drupal\node\NodeInterface;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the edit formatter and the layout builder form with translations.
 *
 * @group layout_paragraphs
 */
class TranslationTest extends KernelTestBase {

  use UserCreationTrait;
  use ParagraphsTestBaseTrait;

  /**
   * The query string of an AJAX form request.
   */
  protected const AJAX_QUERY = '?ajax_form=1&_wrapper_format=drupal_ajax';

  /**
   * The error shown when a form does not belong to the route entity.
   */
  protected const ROUTE_MISMATCH_ERROR = 'This layout was opened for another content item or translation.';

  /**
   * The error shown when the edited translation is not available in storage.
   */
  protected const TRANSLATION_UNAVAILABLE_ERROR = 'The content this layout was opened for is no longer available in this language.';

  /**
   * The error shown when a layout was opened before the site was updated.
   */
  protected const OPENED_BEFORE_UPDATE_ERROR = 'This layout was opened before the site was updated.';

  /**
   * The modules to enable.
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
    'field_ui',
    'layout_discovery',
    'entity_reference_revisions',
    'paragraphs',
    'content_translation',
    'language',
    'layout_paragraphs',
    'layout_paragraphs_translation_access_test',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->installSchema('system', ['sequences']);

    $this->installEntitySchema('user');

    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');

    $this->installEntitySchema('paragraph');

    $this->addParagraphsType('section');
    $this->addParagraphsType('text');
    $this->addFieldtoParagraphType('text', 'field_text', 'string');
    $this->addParagraphedContentType('page', 'field_content', 'layout_paragraphs');

    // The reference field is untranslatable for symmetric translations (see
    // also https://www.drupal.org/project/paragraphs/issues/3354666).
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->load('node.field_content');
    $field_storage->set('translatable', FALSE);
    $field_storage->save();
    $field = $this->entityTypeManager->getStorage('field_config')->load('node.page.field_content');
    $field->set('translatable', FALSE);
    $field->save();

    $node_type = $this->entityTypeManager->getStorage('node_type')->load('page');
    $node_type->set('display_submitted', FALSE);
    $node_type->save();

    // Needed for the 'created' field on the node.
    DateFormat::create([
      'id' => 'fallback',
      'pattern' => 'D, m/d/Y - H:i',
    ])->save();

    // Needed for the 'created' widget on the node form.
    DateFormat::create(['id' => 'html_date', 'pattern' => 'Y-m-d'])->save();
    DateFormat::create(['id' => 'html_time', 'pattern' => 'H:i:s'])->save();

    // Needed for the anonymous user name on the translation form.
    $this->installConfig(['user']);

    // Configure languages.
    $this->installConfig(['language']);

    // Add a second language.
    ConfigurableLanguage::create(['id' => 'de'])->save();

    // Configure the node type to be translatable.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')
      ->setDefaultLangcode('en')
      ->setLanguageAlterable(TRUE)
      ->save();

    // Configure content translation for symmetric translations: the host and
    // the paragraphs are translatable, the reference field is not.
    $content_translation_manager = $this->container->get('content_translation.manager');
    $content_translation_manager->setEnabled('node', 'page', TRUE);
    $content_translation_manager->setEnabled('paragraph', 'text', TRUE);
    $content_translation_manager->setEnabled('paragraph', 'section', TRUE);
    $this->container->get('entity_field.manager')
      ->getFieldDefinitions('paragraph', 'text')['field_text']
      ->setTranslatable(TRUE)
      ->save();

    $config = $this->config('language.negotiation');
    $config->set('url.prefixes', ['en' => 'en', 'de' => 'de'])
      ->save();

    \Drupal::service('kernel')->rebuildContainer();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests editing original language and translation with the formatter.
   */
  public function testFormatterEditingTranslation() {
    // Create a node with a paragraph on it.
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
      'title' => 'Paragraph',
      'type' => 'text',
      'field_text' => 'EN text',
    ]);
    $paragraph->addTranslation('de', [
      'field_text' => 'DE text',
    ]);
    $paragraph->save();

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Node title',
      'field_content' => [$paragraph],
    ]);
    $node->save();
    $node->addTranslation('de', [
      'title' => 'Node title DE',
    ]);
    $node->save();

    \Drupal::currentUser()->setAccount($this->createUser(['administer nodes']));

    // Sanity check: the paragraph is shown on the node page in both languages.
    $request = Request::create('/node/' . $node->id());
    $this->doRequest($request);
    $this->assertText('EN text');
    $this->assertText('Node title');

    $request = Request::create('/de/node/' . $node->id());
    $this->doRequest($request);
    $this->assertText('DE text');
    $this->assertText('Node title DE');

    // Use the no-JS path for editing paragraph layouts with the formatter.
    $uri = '/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doPostForm($uri, 'Save', []);
    $this->assertText('EN text');

    $uri = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doPostForm($uri, 'Save', []);
    $this->assertText('DE text');
    $this->assertNoText('EN text');
  }

  /**
   * Tests the builder on a new translation with untranslated paragraphs.
   */
  public function testFormatterCreatingTranslation() {
    // Create a node with a paragraph on it.
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
      'title' => 'Paragraph',
      'type' => 'text',
      'field_text' => 'EN text',
    ]);
    $paragraph->save();

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Node title',
      'field_content' => [$paragraph],
    ]);
    $node->save();

    \Drupal::currentUser()->setAccount($this->createUser([
      'administer nodes',
      'create content translations',
      'translate any entity',
    ]));

    // Sanity check: the paragraph is shown on the node translation creation
    // page in the original language.
    $request = Request::create('/de/node/' . $node->id() . '/translations/add/en/de');
    $response = $this->doRequest($request);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $title_inputs = $this->xpath('//input[@name="title[0][value]"]');
    $this->assertCount(1, $title_inputs);
    $this->assertSame('Node title', (string) $title_inputs[0]['value']);
    $this->assertText('EN text');

    // Create the host translation without translating its paragraphs, as a
    // translation created through the API leaves it.
    $node->addTranslation('de', [
      'title' => 'Node title DE',
    ]);
    $this->container->get('content_translation.manager')
      ->getTranslationMetadata($node->getTranslation('de'))
      ->setSource('en');
    $node->save();

    // The paragraph has no translation yet.
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $paragraph_storage->resetCache([$paragraph->id()]);
    $this->assertFalse($paragraph_storage->load($paragraph->id())->hasTranslation('de'));

    // Use the no-JS path for editing paragraph layouts with the formatter.
    $uri = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doPostForm($uri, 'Save', []);
    $this->assertText('EN text');
  }

  /**
   * Tests that the builder requires update access on the route translation.
   *
   * A user who may update the node in general, but not its German
   * translation, is denied the builder on the German route and granted it on
   * the English one.
   */
  public function testBuilderIsDeniedOnTranslationWithoutUpdateAccess() {
    $node = $this->createNodeWithTranslatedParagraph();
    $this->setUpEditor();

    $this->container->get('state')->set('layout_paragraphs_translation_access_test.forbidden_langcodes', ['de']);
    $this->entityTypeManager->getAccessControlHandler('node')->resetCache();

    $path = '/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $response = $this->doRequest(Request::create('/de' . $path));
    $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

    $response = $this->doRequest(Request::create('/en' . $path));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertText('EN text');
  }

  /**
   * Tests that a form posted under another translation is not saved.
   *
   * The builder is opened on the German translation and a component is edited
   * in its layout. The form is then posted to the English route, where the
   * access check ran on the English translation. The layout was prepared for
   * German, so the submission is rejected: the German edit does not reach
   * storage and no translation is saved.
   */
  public function testFormPostedUnderOtherTranslationIsNotSaved() {
    $node = $this->createNodeWithTranslatedParagraph();
    $paragraph_id = (int) $node->get('field_content')->target_id;
    $this->setUpEditor();
    $this->setChangedTimeOnAllTranslations($node, 1000);

    $path = '/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create('/de' . $path));
    $data = $this->getBuilderFormFields();
    $this->editFirstComponentText($data['layout_paragraphs_storage_key'], 'DE text edited');

    $response = $this->doRequest(Request::create('/en' . $path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertText(self::ROUTE_MISMATCH_ERROR);

    $this->assertEquals(['en' => 'EN text', 'de' => 'DE text'], $this->loadParagraphTexts($paragraph_id));
    $this->assertEquals(['en' => 1000, 'de' => 1000], $this->loadChangedTimes($node));
  }

  /**
   * Tests that an edit is not saved elsewhere after access is revoked.
   *
   * A component of the German translation is edited in the builder, and
   * German update access is then revoked while the English translation stays
   * editable. The German edit cannot be persisted through the English route:
   * the layout was prepared for German, so the submission is rejected.
   */
  public function testParagraphEditIsNotSavedThroughAnotherTranslationAfterAccessIsRevoked() {
    $node = $this->createNodeWithTranslatedParagraph();
    $paragraph_id = (int) $node->get('field_content')->target_id;
    $this->setUpEditor();
    $this->setChangedTimeOnAllTranslations($node, 1000);

    $path = '/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create('/de' . $path));
    $data = $this->getBuilderFormFields();
    $this->editFirstComponentText($data['layout_paragraphs_storage_key'], 'DE text edited');

    $this->container->get('state')->set('layout_paragraphs_translation_access_test.forbidden_langcodes', ['de']);
    $this->entityTypeManager->getAccessControlHandler('node')->resetCache();

    // Asserted so the method cannot pass for the wrong reason: without the
    // revocation in effect the German route would still be open.
    $response = $this->doRequest(Request::create('/de' . $path));
    $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

    $response = $this->doRequest(Request::create('/en' . $path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertText(self::ROUTE_MISMATCH_ERROR);

    $this->assertEquals(['en' => 'EN text', 'de' => 'DE text'], $this->loadParagraphTexts($paragraph_id));
    $this->assertEquals(['en' => 1000, 'de' => 1000], $this->loadChangedTimes($node));
  }

  /**
   * Tests that a saved layout keeps the language it was prepared for.
   *
   * The builder writes its layout back to the tempstore after the save, so a
   * second save of the same builder is still bound to that language.
   */
  public function testSavedLayoutKeepsPreparedLanguage() {
    $node = $this->createNodeWithTranslatedParagraph();
    $this->setUpEditor();

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create($path));
    $data = $this->getBuilderFormFields();

    $response = $this->saveThroughDialog($path, $data);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertStringContainsString('has been updated', $response->getContent());

    $layout = $this->container->get('layout_paragraphs.tempstore_repository')
      ->getWithStorageKey($data['layout_paragraphs_storage_key']);
    $this->assertEquals('de', $layout->getThirdPartySetting('layout_paragraphs', 'langcode'));
  }

  /**
   * Tests that a layout without a prepared language is not saved.
   *
   * A layout that does not record the language it was prepared for cannot be
   * checked against the route, which is the state a builder opened before
   * that language was recorded leaves behind. Its submission is rejected on
   * its own route as well.
   */
  public function testLayoutWithoutPreparedLanguageIsNotSaved() {
    $node = $this->createNodeWithTranslatedParagraph();
    $this->setUpEditor();
    $this->setChangedTimeOnAllTranslations($node, 1000);

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create($path));
    $data = $this->getBuilderFormFields();

    $tempstore = $this->container->get('layout_paragraphs.tempstore_repository');
    $layout = $tempstore->getWithStorageKey($data['layout_paragraphs_storage_key']);
    $layout->unsetThirdPartySetting('layout_paragraphs', 'langcode');
    $tempstore->set($layout);

    $response = $this->doRequest(Request::create($path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertText(self::OPENED_BEFORE_UPDATE_ERROR);

    $this->assertEquals(['en' => 1000, 'de' => 1000], $this->loadChangedTimes($node));
  }

  /**
   * Tests that the widget records the language on the layout.
   *
   * The node form of the German translation puts a layout into the tempstore
   * too, so every layout this module writes carries the language its
   * components were prepared for.
   */
  public function testWidgetRecordsPreparedLanguageOnLayout() {
    $node = $this->createNodeWithTranslatedParagraph();
    $this->setUpEditor();

    $response = $this->doRequest(Request::create('/de/node/' . $node->id() . '/edit'));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

    // The widget nests its hidden storage key under the field's parents.
    $inputs = $this->cssSelect('input[name="field_content[layout_paragraphs_storage_key]"]');
    $this->assertCount(1, $inputs);
    $storage_key = (string) $inputs[0]->attributes()->value[0];

    $layout = $this->container->get('layout_paragraphs.tempstore_repository')
      ->getWithStorageKey($storage_key);
    $this->assertEquals('de', $layout->getThirdPartySetting('layout_paragraphs', 'langcode'));
  }

  /**
   * Tests that a cached form is not saved under another translation.
   *
   * Closing the builder rebuilds and caches its form, and the response hands
   * the new form build ID to the client. That cached form carries the German
   * translation it was built for. Posting it to the English route, where the
   * access check ran on the English translation, saves nothing and shows an
   * error.
   */
  public function testCachedFormPostedUnderOtherTranslationIsNotSaved() {
    $node = $this->createNodeWithTranslatedParagraph();
    $this->setUpEditor();
    $this->setChangedTimeOnAllTranslations($node, 1000);

    $path = '/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create('/de' . $path));
    $data = $this->getBuilderFormFields();
    $data['form_build_id'] = $this->closeBuilder('/de' . $path, $data);

    $save = [
      'op' => 'Save',
      '_triggering_element_name' => 'op',
      '_triggering_element_value' => 'Save',
    ];
    $response = $this->doRequest($this->createAjaxRequest('/en' . $path . self::AJAX_QUERY, $data + $save));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertStringNotContainsString('has been updated', $response->getContent());
    $this->assertStringContainsString(self::ROUTE_MISMATCH_ERROR, $response->getContent());

    $response = $this->doRequest(Request::create('/en' . $path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertText(self::ROUTE_MISMATCH_ERROR);

    $changed_times = $this->loadChangedTimes($node);
    $this->assertEquals(1000, $changed_times['de']);
    $this->assertEquals(1000, $changed_times['en']);
  }

  /**
   * Tests that closing a cached form under another translation is rejected.
   *
   * The cached form of a builder closed under the German route is closed
   * again under the English route. The request shows an error and neither
   * removes the layout's tempstore entry nor replaces the builder.
   */
  public function testCachedFormClosedUnderOtherTranslationKeepsLayout() {
    $node = $this->createNodeWithTranslatedParagraph();
    $this->setUpEditor();

    $path = '/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create('/de' . $path));
    $data = $this->getBuilderFormFields();
    $tempstore = $this->container->get('layout_paragraphs.tempstore_repository');
    $layout = $tempstore->getWithStorageKey($data['layout_paragraphs_storage_key']);
    $this->assertNotNull($layout);

    $data['form_build_id'] = $this->closeBuilder('/de' . $path, $data);
    // Closing removed the layout's tempstore entry. Write it back, so the
    // second Close can show whether it removes the entry.
    $this->assertNull($tempstore->getWithStorageKey($data['layout_paragraphs_storage_key']));
    $tempstore->set($layout);

    $close = [
      '_triggering_element_name' => 'op',
      '_triggering_element_value' => 'Close',
    ];
    $response = $this->doRequest($this->createAjaxRequest('/en' . $path . self::AJAX_QUERY, $data + $close));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertStringContainsString(self::ROUTE_MISMATCH_ERROR, $response->getContent());
    $command_names = array_column(json_decode($response->getContent(), TRUE), 'command');
    $this->assertNotContains('insert', $command_names);
    $this->assertNotContains('LayoutParagraphsEventCommand', $command_names);
    $this->assertNotNull($tempstore->getWithStorageKey($data['layout_paragraphs_storage_key']));
  }

  /**
   * Tests that the layout of another node is not saved through the builder.
   *
   * The builder form of one node is posted with the storage key of a layout
   * opened for another node. The access check ran on the first node, so
   * nothing is saved and an error is shown.
   */
  public function testLayoutOfOtherNodeIsNotSaved() {
    $node = $this->createNodeWithTranslatedParagraph();
    $other_node = $this->createNodeWithTranslatedParagraph();
    $this->setUpEditor();
    $this->setChangedTimeOnAllTranslations($node, 1000);
    $this->setChangedTimeOnAllTranslations($other_node, 1000);

    $this->doRequest(Request::create('/en/layout-paragraphs-builder/formatter/node/' . $other_node->id() . '/field_content/full'));
    $other_storage_key = $this->getBuilderFormFields()['layout_paragraphs_storage_key'];

    $uri = '/en/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create($uri));
    $data = ['layout_paragraphs_storage_key' => $other_storage_key] + $this->getBuilderFormFields();

    $response = $this->doRequest(Request::create($uri, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertText(self::ROUTE_MISMATCH_ERROR);

    $this->assertEquals(['en' => 1000, 'de' => 1000], $this->loadChangedTimes($node));
    $this->assertEquals(['en' => 1000, 'de' => 1000], $this->loadChangedTimes($other_node));
  }

  /**
   * Tests that the layout of a deleted revision is not saved.
   *
   * The builder is opened on the German translation, a new revision of the
   * node is created, and the revision the layout was opened on is deleted.
   * The form is then posted to the German route it was opened on. The stored
   * revision the layout belongs to is gone, so nothing is saved, no other
   * revision is saved in its place, and an error is shown.
   */
  public function testLayoutOfDeletedRevisionIsNotSaved() {
    $node = $this->createNodeWithTranslatedParagraph();
    $this->setUpEditor();

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create($path));
    $data = $this->getBuilderFormFields();

    $revision_id = (int) $node->getRevisionId();
    $node->setNewRevision(TRUE);
    $node->save();
    $this->entityTypeManager->getStorage('node')->deleteRevision($revision_id);
    $this->setChangedTimeOnAllTranslations($node, 1000);

    $response = $this->doRequest(Request::create($path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertText(self::TRANSLATION_UNAVAILABLE_ERROR);

    $this->assertEquals(['en' => 1000, 'de' => 1000], $this->loadChangedTimes($node));
  }

  /**
   * Tests that a component edit after a save goes to the edited translation.
   *
   * The builder is opened on the German translation and saved, a component is
   * edited, and the builder is saved again. The edit is saved to the German
   * paragraph translation, the English one stays unchanged, and the builder
   * stays in translation mode.
   */
  public function testComponentEditAfterSaveIsSavedToEditedTranslation() {
    $node = $this->createNodeWithTranslatedParagraph();
    $this->setUpEditor();

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create($path));
    $data = $this->getBuilderFormFields();
    $response = $this->saveThroughDialog($path, $data);
    $this->assertStringContainsString('has been updated', $response->getContent());

    $this->editFirstComponentText($data['layout_paragraphs_storage_key'], 'DE text edited');
    $response = $this->saveThroughDialog($path, $data);
    $this->assertStringContainsString('has been updated', $response->getContent());
    $this->assertStringContainsString('You are in translation mode.', $response->getContent());

    $texts = $this->loadParagraphTexts((int) $node->get('field_content')->target_id);
    $this->assertEquals(['en' => 'EN text', 'de' => 'DE text edited'], $texts);
  }

  /**
   * Tests that a cached form is accepted on the route it was built for.
   *
   * The form cached by closing the builder on the German translation is saved
   * on the same German route. The save is accepted and writes the German
   * translation; the English one stays unchanged.
   */
  public function testCachedFormIsAcceptedOnItsOwnRoute() {
    $node = $this->createNodeWithTranslatedParagraph();
    $this->setUpEditor();
    $this->setChangedTimeOnAllTranslations($node, 1000);

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create($path));
    $data = $this->getBuilderFormFields();
    $data['form_build_id'] = $this->closeBuilder($path, $data);

    $response = $this->saveThroughDialog($path, $data);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertStringContainsString('has been updated', $response->getContent());
    $this->assertStringNotContainsString(self::ROUTE_MISMATCH_ERROR, $response->getContent());

    $changed_times = $this->loadChangedTimes($node);
    $this->assertNotEquals(1000, $changed_times['de']);
    $this->assertEquals(1000, $changed_times['en']);
  }

  /**
   * Tests that a layout of another translation is not saved.
   *
   * With a translatable reference field, the layout of the German translation
   * is posted with a builder form of the English translation. The access
   * check ran on the English translation, so nothing is saved and an error is
   * shown.
   */
  public function testLayoutOfOtherTranslationIsNotSavedForTranslatableField() {
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->load('node.field_content');
    $field_storage->set('translatable', TRUE);
    $field_storage->save();
    $field = $this->entityTypeManager->getStorage('field_config')->load('node.page.field_content');
    $field->set('translatable', TRUE);
    $field->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $paragraph = $paragraph_storage->create([
      'type' => 'text',
      'field_text' => 'EN text',
    ]);
    $paragraph->save();
    $german_paragraph = $paragraph_storage->create([
      'type' => 'text',
      'field_text' => 'DE text',
      'langcode' => 'de',
    ]);
    $german_paragraph->save();
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Node title',
      'field_content' => [$paragraph],
    ]);
    $node->save();
    $node->addTranslation('de', [
      'title' => 'Node title DE',
      'field_content' => [$german_paragraph],
    ]);
    $node->save();
    $this->setUpEditor();
    $this->setChangedTimeOnAllTranslations($node, 1000);

    $path = '/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create('/de' . $path));
    $german_storage_key = $this->getBuilderFormFields()['layout_paragraphs_storage_key'];

    $this->doRequest(Request::create('/en' . $path));
    $data = ['layout_paragraphs_storage_key' => $german_storage_key] + $this->getBuilderFormFields();

    $response = $this->doRequest(Request::create('/en' . $path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertText(self::ROUTE_MISMATCH_ERROR);

    $this->assertEquals(['en' => 1000, 'de' => 1000], $this->loadChangedTimes($node));
  }

  /**
   * Tests that a cached form of another field is not saved.
   *
   * The form cached by closing the builder of a second layout field is saved
   * on the route of the first field. The access check ran on the first
   * field, so nothing is saved and an error is shown.
   */
  public function testCachedFormOfOtherFieldIsNotSaved() {
    $this->addParagraphsField('page', 'field_content_second', 'node', 'layout_paragraphs');
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $paragraph = $paragraph_storage->create([
      'type' => 'text',
      'field_text' => 'EN text',
    ]);
    $paragraph->save();
    $second_paragraph = $paragraph_storage->create([
      'type' => 'text',
      'field_text' => 'EN second text',
    ]);
    $second_paragraph->save();
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Node title',
      'field_content' => [$paragraph],
      'field_content_second' => [$second_paragraph],
    ]);
    $node->save();
    $this->setUpEditor();
    $this->setChangedTimeOnAllTranslations($node, 1000);

    $path = '/en/layout-paragraphs-builder/formatter/node/' . $node->id();
    $this->doRequest(Request::create($path . '/field_content_second/full'));
    $data = $this->getBuilderFormFields();
    $this->assertText('EN second text');
    $data['form_build_id'] = $this->closeBuilder($path . '/field_content_second/full', $data);

    $save = [
      'op' => 'Save',
      '_triggering_element_name' => 'op',
      '_triggering_element_value' => 'Save',
    ];
    $response = $this->doRequest($this->createAjaxRequest($path . '/field_content/full' . self::AJAX_QUERY, $data + $save));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertStringNotContainsString('has been updated', $response->getContent());
    $this->assertStringContainsString(self::ROUTE_MISMATCH_ERROR, $response->getContent());

    $this->assertEquals(['en' => 1000], $this->loadChangedTimes($node));
  }

  /**
   * Tests that a form built without the route's arguments is rejected.
   *
   * The builder route passes the host entity translation and the field name
   * to the form. A caller that passes fewer arguments, such as a subclass or
   * a module embedding the form, leaves the submission bound to no entity and
   * no field, which the validator rejects instead of raising a PHP error.
   */
  public function testFormBuiltWithoutRouteArgumentsIsRejected() {
    $node = $this->createNodeWithTranslatedParagraph();
    $this->setUpEditor();
    $uri = '/en/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';

    $this->assertSame([], $this->validateBuilderForm($uri, $node, [$node, 'field_content']));

    foreach ([[], [$node]] as $args) {
      $errors = $this->validateBuilderForm($uri, $node, $args);
      $this->assertCount(1, $errors);
      $this->assertStringContainsString(self::ROUTE_MISMATCH_ERROR, (string) reset($errors));
    }
  }

  /**
   * Tests that an asymmetric save writes only the edited translation.
   *
   * With a translatable reference field every host translation has its own
   * paragraphs. Saving the German translation through the builder moves its
   * changed time, leaves the English one untouched, and leaves both
   * translations referencing the paragraphs they referenced before.
   */
  public function testAsymmetricBuilderSavesOnlyEditedTranslation() {
    $this->setUpAsymmetricTranslations();
    $node = $this->createNodeWithAsymmetricParagraphs();
    $english_paragraph_id = (int) $node->get('field_content')->target_id;
    $german_paragraph_id = (int) $node->getTranslation('de')->get('field_content')->target_id;
    $english_revision_id = (int) $node->get('field_content')->target_revision_id;
    $german_revision_id = (int) $node->getTranslation('de')->get('field_content')->target_revision_id;
    $this->setUpEditor();
    $this->setChangedTimeOnAllTranslations($node, 1000);

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create($path));
    $this->assertText('DE text');
    $this->assertNoText('EN text');

    $data = $this->getBuilderFormFields();
    $response = $this->doRequest(Request::create($path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(303, $response->getStatusCode());

    $changed_times = $this->loadChangedTimes($node);
    $this->assertNotEquals(1000, $changed_times['de']);
    $this->assertEquals(1000, $changed_times['en']);

    $expected_ids = ['en' => [$english_paragraph_id], 'de' => [$german_paragraph_id]];
    $this->assertEquals($expected_ids, $this->loadReferencedParagraphIds($node));

    // The revision IDs too: a paragraph saved again would get a new one, so
    // this fails if the save touches the English paragraph.
    $expected_revision_ids = [
      'en' => [$english_revision_id],
      'de' => [$german_revision_id],
    ];
    $this->assertEquals($expected_revision_ids, $this->loadReferencedParagraphRevisionIds($node));
  }

  /**
   * Tests that an asymmetric save leaves the paragraphs untranslated.
   *
   * With untranslatable paragraphs, saving the German host translation
   * through the builder leaves every paragraph with a single translation, in
   * the language the paragraph was created in.
   */
  public function testAsymmetricBuilderLeavesParagraphsUntranslated() {
    $this->setUpAsymmetricTranslations();
    $node = $this->createNodeWithAsymmetricParagraphs();
    $english_paragraph_id = (int) $node->get('field_content')->target_id;
    $german_paragraph_id = (int) $node->getTranslation('de')->get('field_content')->target_id;
    $this->setUpEditor();

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doPostForm($path, 'Save', []);

    $this->assertEquals(['en' => 'EN text'], $this->loadParagraphTexts($english_paragraph_id));
    $this->assertEquals(['de' => 'DE text'], $this->loadParagraphTexts($german_paragraph_id));
  }

  /**
   * Tests that an edit while creating a translation translates the paragraph.
   *
   * The German host translation exists and its paragraph has no German
   * translation yet. Editing the first component and saving the builder on
   * the German route writes the edit to a new German paragraph translation,
   * leaves the English text as it was, and keeps the paragraph's own language
   * English.
   */
  public function testComponentEditWhileCreatingTranslationCreatesParagraphTranslation() {
    $node = $this->createNodeWithUntranslatedParagraph();
    $paragraph_id = (int) $node->get('field_content')->target_id;
    $this->setUpEditor();

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create($path));
    $data = $this->getBuilderFormFields();

    $this->editFirstComponentText($data['layout_paragraphs_storage_key'], 'DE text');
    // The POST is built here rather than through self::doPostForm(), which
    // submits the build ID, the form ID and the token only. Without the
    // layout's storage key the form takes its first-build branch and puts a
    // fresh layout into the tempstore, and the submit would save that one
    // instead of the edited layout.
    $response = $this->doRequest(Request::create($path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(303, $response->getStatusCode());

    $this->assertEquals(['en' => 'EN text', 'de' => 'DE text'], $this->loadParagraphTexts($paragraph_id));
    $this->assertEquals('en', $this->loadParagraphLangcode($paragraph_id));
  }

  /**
   * Tests the change detector on a translation with untranslated paragraphs.
   *
   * Opening the builder on the German route prepares a German translation of
   * the paragraph. That alone is not a change: closing the builder must not
   * ask about unsaved changes. Editing the component is.
   */
  public function testPreparedParagraphTranslationIsNoChange() {
    $node = $this->createNodeWithUntranslatedParagraph();
    $this->setUpEditor();

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create($path));
    $storage_key = $this->getBuilderFormFields()['layout_paragraphs_storage_key'];
    $tempstore = $this->container->get('layout_paragraphs.tempstore_repository');
    $change_detector = $this->container->get('layout_paragraphs.change_detector');

    $layout = $tempstore->getWithStorageKey($storage_key);
    $this->assertTrue($layout->getParagraphsReferenceField()->get(0)->entity->isNewTranslation());
    $this->assertFalse($change_detector->hasChanges($layout));

    $this->editFirstComponentText($storage_key, 'DE text');
    $this->assertTrue($change_detector->hasChanges($tempstore->getWithStorageKey($storage_key)));
  }

  /**
   * Tests that saving one translation leaves the other ones unchanged.
   *
   * The builder is opened on the German translation and saved once, with no
   * Close rebuild and no cross-route POST before the save. The German changed
   * time moves, the English one stays where it was, and the paragraph keeps
   * its text in both languages.
   */
  public function testSavingOneTranslationLeavesOtherTranslationsUnchanged() {
    $node = $this->createNodeWithTranslatedParagraph();
    $paragraph_id = (int) $node->get('field_content')->target_id;
    $this->setUpEditor();
    $this->setChangedTimeOnAllTranslations($node, 1000);

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doPostForm($path, 'Save', []);

    $changed_times = $this->loadChangedTimes($node);
    $this->assertNotEquals(1000, $changed_times['de']);
    $this->assertEquals(1000, $changed_times['en']);
    $this->assertEquals(['en' => 'EN text', 'de' => 'DE text'], $this->loadParagraphTexts($paragraph_id));
  }

  /**
   * Tests the language of a component inserted while translating.
   *
   * With a translatable reference field the builder allows inserting
   * components while a host translation is edited. A component inserted on
   * the German route is stored in German.
   */
  public function testComponentInsertedWhileTranslatingAsymmetricallyTakesEditedLangcode() {
    $this->setUpAsymmetricTranslations();
    // The component forms take the form display mode of the new paragraph
    // from the layout settings, which the builder reads from the formatter of
    // the reference field. Without the layout paragraphs builder formatter on
    // the view display the insert route gets no form display mode at all,
    // which is why a test of the inserted langcode configures a display.
    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page')
      ->setComponent('field_content', ['type' => 'layout_paragraphs_builder'])
      ->save();
    $node = $this->createNodeWithAsymmetricParagraphs();
    $german_paragraph_id = (int) $node->getTranslation('de')->get('field_content')->target_id;
    $this->setUpEditor();

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $this->doRequest(Request::create($path));
    $data = $this->getBuilderFormFields();

    $insert_path = '/de/layout-paragraphs-builder/' . $data['layout_paragraphs_storage_key'] . '/insert/text';
    $this->doPostForm($insert_path, 'Save', [
      'op' => 'Save',
      'field_text' => [['value' => 'DE inserted text']],
    ]);

    // As above, the POST carries the layout's storage key, which
    // self::doPostForm() does not submit: without it the form would build a
    // fresh layout and the submit would save that one instead of the layout
    // the component was inserted into.
    $response = $this->doRequest(Request::create($path, 'POST', $data + ['op' => 'Save']));
    $this->assertEquals(303, $response->getStatusCode());

    $paragraph_ids = $this->loadReferencedParagraphIds($node);
    $inserted_ids = array_values(array_diff($paragraph_ids['de'], [$german_paragraph_id]));
    $this->assertCount(1, $inserted_ids);
    $this->assertEquals('de', $this->loadParagraphLangcode($inserted_ids[0]));
  }

  /**
   * Tests the builder of a translation when untranslatable fields are hidden.
   *
   * The reference field is untranslatable, so every language shares its
   * structure. With the host bundle hiding untranslatable fields on its
   * translation forms, a translation cannot change that structure: the builder
   * says so, offers no move controls, does not make the components sortable,
   * and the reorder route is denied. The same layout stays editable in the
   * original language.
   */
  public function testBuilderLocksLayoutWhileTranslatingWithHiddenUntranslatableFields() {
    $node = $this->createNodeWithTwoTranslatedParagraphs();
    $this->hideUntranslatableFields();
    $this->setUpEditor();

    $path = '/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $response = $this->doRequest(Request::create('/de' . $path));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertText('You are in translation mode. You cannot add, remove or reorder items while translating.');
    $this->assertFalse($this->getBuilderSetting('layout_change_access'));
    $controls = $this->getComponentControlsMarkup();
    $this->assertStringContainsString('lpb-edit', $controls);
    $this->assertStringNotContainsString('lpb-drag', $controls);
    $this->assertStringNotContainsString('lpb-up', $controls);
    $this->assertStringNotContainsString('lpb-down', $controls);

    $storage_key = $this->getBuilderFormFields()['layout_paragraphs_storage_key'];
    $response = $this->postReversedOrder('/de', $storage_key);
    $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    $this->assertEquals(['DE first', 'DE second'], $this->loadLayoutTexts($storage_key, 'de'));

    // The original language changes the structure for every language.
    $response = $this->doRequest(Request::create('/en' . $path));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertNoText('You are in translation mode.');
    $this->assertTrue($this->getBuilderSetting('layout_change_access'));
    $this->assertStringContainsString('lpb-drag', $this->getComponentControlsMarkup());

    $storage_key = $this->getBuilderFormFields()['layout_paragraphs_storage_key'];
    $response = $this->postReversedOrder('/en', $storage_key);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertEquals(['EN second', 'EN first'], $this->loadLayoutTexts($storage_key, 'en'));
  }

  /**
   * Tests the builder of a translation when untranslatable fields are shown.
   *
   * Without the host bundle hiding untranslatable fields, a translation may
   * still reorder the shared structure, and the builder warns that this
   * affects every language.
   */
  public function testBuilderAllowsReorderWhileTranslatingWithVisibleUntranslatableFields() {
    $node = $this->createNodeWithTwoTranslatedParagraphs();
    $this->setUpEditor();

    $path = '/de/layout-paragraphs-builder/formatter/node/' . $node->id() . '/field_content/full';
    $response = $this->doRequest(Request::create($path));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertText('You are in translation mode. You cannot add or remove items while translating. Reordering items will affect all languages.');
    $this->assertTrue($this->getBuilderSetting('layout_change_access'));
    $controls = $this->getComponentControlsMarkup();
    $this->assertStringContainsString('lpb-drag', $controls);
    $this->assertStringContainsString('lpb-up', $controls);
    $this->assertStringContainsString('lpb-down', $controls);

    $storage_key = $this->getBuilderFormFields()['layout_paragraphs_storage_key'];
    $response = $this->postReversedOrder('/de', $storage_key);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertEquals(['DE second', 'DE first'], $this->loadLayoutTexts($storage_key, 'de'));
  }

  /**
   * Tests that the node form of a locked translation keeps the structure.
   *
   * With the host bundle hiding untranslatable fields, a translation must not
   * change the untranslatable reference field, whose order every language
   * shares. A layout whose order changed in the tempstore anyway, for example
   * through a reorder request sent before the lock applied, is saved with the
   * original order and the edited component values, instead of reordering the
   * components of every language.
   */
  public function testWidgetKeepsStructureWhileTranslatingWithHiddenUntranslatableFields() {
    $node = $this->createNodeWithTwoTranslatedParagraphs();
    $paragraph_ids = $this->loadReferencedParagraphIds($node);
    $this->hideUntranslatableFields();
    // The translation form falls back to the anonymous user as the translation
    // author, which a kernel test does not create.
    $this->entityTypeManager->getStorage('user')->create([
      'uid' => 0,
      'name' => '',
      'status' => 0,
    ])->save();
    $this->setUpEditor();

    $uri = '/de/node/' . $node->id() . '/edit';
    $response = $this->doRequest(Request::create($uri));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $inputs = $this->cssSelect('input[name="field_content[layout_paragraphs_storage_key]"]');
    $this->assertCount(1, $inputs);
    $storage_key = (string) $inputs[0]->attributes()->value[0];
    // Submit the form as rendered, with the values of all its text and hidden
    // inputs, such as the translation author.
    $data = ['op' => 'Save (this translation)'];
    foreach ($this->cssSelect('form.node-form input[type="text"], form.node-form input[type="hidden"]') as $input) {
      $data[(string) $input['name']] = (string) $input['value'];
    }
    $this->assertEquals($storage_key, $data['field_content[layout_paragraphs_storage_key]']);

    $this->editFirstComponentText($storage_key, 'DE first edited');
    $tempstore = $this->container->get('layout_paragraphs.tempstore_repository');
    $layout = $tempstore->getWithStorageKey($storage_key);
    $uuids = array_map(fn ($component) => $component->getEntity()->uuid(), $layout->getComponents());
    $layout->reorderComponents(array_map(fn ($uuid) => [
      'uuid' => $uuid,
      'parentUuid' => NULL,
      'region' => NULL,
    ], array_reverse($uuids)));
    $tempstore->set($layout);

    // Nest the bracketed input names, as PHP does for a browser's form POST.
    parse_str(http_build_query($data), $data);
    $response = $this->doRequest(Request::create($uri, 'POST', $data));
    $this->assertEquals(303, $response->getStatusCode(), $this->getTextContent());

    $this->assertEquals($paragraph_ids, $this->loadReferencedParagraphIds($node));
    $this->assertEquals(['en' => 'EN first', 'de' => 'DE first edited'], $this->loadParagraphTexts($paragraph_ids['de'][0]));
  }

  /**
   * Creates a node with a German translation and two translated paragraphs.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function createNodeWithTwoTranslatedParagraphs() {
    $paragraphs = [];
    foreach (['first', 'second'] as $position) {
      $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
        'type' => 'text',
        'field_text' => 'EN ' . $position,
      ]);
      $paragraph->addTranslation('de', [
        'field_text' => 'DE ' . $position,
      ]);
      $paragraph->save();
      $paragraphs[] = $paragraph;
    }

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Node title',
      'field_content' => $paragraphs,
    ]);
    $node->save();
    $node->addTranslation('de', [
      'title' => 'Node title DE',
    ]);
    $node->save();

    return $node;
  }

  /**
   * Hides the untranslatable fields of pages on their translation forms.
   *
   * Content translation then lets only the original language change the
   * untranslatable fields, the paragraphs reference field among them.
   */
  protected function hideUntranslatableFields() {
    $this->container->get('content_translation.manager')
      ->setBundleTranslationSettings('node', 'page', ['untranslatable_fields_hide' => TRUE]);
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * Returns a setting of the builder in the current content.
   *
   * @param string $name
   *   The setting name.
   *
   * @return mixed
   *   The value of the setting, or NULL if the builder has no such setting.
   */
  protected function getBuilderSetting(string $name) {
    $scripts = $this->cssSelect('script[data-drupal-selector="drupal-settings-json"]');
    $this->assertCount(1, $scripts);
    $settings = json_decode((string) $scripts[0], TRUE);
    $builders = array_diff_key($settings['lpBuilder'], ['uiElements' => TRUE]);
    $this->assertCount(1, $builders);
    return reset($builders)[$name] ?? NULL;
  }

  /**
   * Returns the markup of every component's controls in the current content.
   *
   * The builder hands the controls to its JavaScript in drupalSettings.
   *
   * @return string
   *   The concatenated markup.
   */
  protected function getComponentControlsMarkup(): string {
    $scripts = $this->cssSelect('script[data-drupal-selector="drupal-settings-json"]');
    $this->assertCount(1, $scripts);
    $settings = json_decode((string) $scripts[0], TRUE);
    $this->assertNotEmpty($settings['lpBuilder']['uiElements']);
    $markup = '';
    foreach ($settings['lpBuilder']['uiElements'] as $elements) {
      $markup .= $elements['controls']['element'] ?? '';
    }
    $this->assertNotEmpty($markup);
    return $markup;
  }

  /**
   * Posts the components of a layout in reverse order to the reorder route.
   *
   * @param string $prefix
   *   The language prefix of the route.
   * @param string $storage_key
   *   The layout's tempstore storage key.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  protected function postReversedOrder(string $prefix, string $storage_key): Response {
    $layout = $this->container->get('layout_paragraphs.tempstore_repository')
      ->getWithStorageKey($storage_key);
    $components = [];
    foreach (array_reverse($layout->getComponents()) as $component) {
      $components[] = [
        'uuid' => $component->getEntity()->uuid(),
        'parentUuid' => NULL,
        'region' => NULL,
      ];
    }
    $uri = $prefix . '/layout-paragraphs-builder/' . $storage_key . '/reorder' . self::AJAX_QUERY;
    return $this->doRequest($this->createAjaxRequest($uri, ['components' => json_encode($components)]));
  }

  /**
   * Loads the component texts of a layout in the tempstore, in order.
   *
   * @param string $storage_key
   *   The layout's tempstore storage key.
   * @param string $langcode
   *   The language of the texts.
   *
   * @return string[]
   *   The texts.
   */
  protected function loadLayoutTexts(string $storage_key, string $langcode): array {
    $layout = $this->container->get('layout_paragraphs.tempstore_repository')
      ->getWithStorageKey($storage_key);
    $texts = [];
    foreach ($layout->getComponents() as $component) {
      $paragraph = $component->getEntity();
      $texts[] = $paragraph->getTranslation($langcode)->get('field_text')->value;
    }
    return $texts;
  }

  /**
   * Creates a node with a German translation and a translated paragraph.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function createNodeWithTranslatedParagraph() {
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => 'text',
      'field_text' => 'EN text',
    ]);
    $paragraph->addTranslation('de', [
      'field_text' => 'DE text',
    ]);
    $paragraph->save();

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Node title',
      'field_content' => [$paragraph],
    ]);
    $node->save();
    $node->addTranslation('de', [
      'title' => 'Node title DE',
    ]);
    $node->save();

    return $node;
  }

  /**
   * Creates a node with a German translation and one paragraph per language.
   *
   * The fixture of the asymmetric configuration: the English paragraph is
   * referenced by the English node, the German one by the German
   * translation's own value of the reference field.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function createNodeWithAsymmetricParagraphs() {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $paragraph = $paragraph_storage->create([
      'type' => 'text',
      'field_text' => 'EN text',
    ]);
    $paragraph->save();
    $german_paragraph = $paragraph_storage->create([
      'type' => 'text',
      'field_text' => 'DE text',
      'langcode' => 'de',
    ]);
    $german_paragraph->save();

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Node title',
      'field_content' => [$paragraph],
    ]);
    $node->save();
    $node->addTranslation('de', [
      'title' => 'Node title DE',
      'field_content' => [$german_paragraph],
    ]);
    $node->save();

    return $node;
  }

  /**
   * Creates a node with a German translation and an untranslated paragraph.
   *
   * The German host translation carries the content translation source that a
   * translation created through the API records, and the paragraph has no
   * German translation yet.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function createNodeWithUntranslatedParagraph() {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $paragraph = $paragraph_storage->create([
      'type' => 'text',
      'field_text' => 'EN text',
    ]);
    $paragraph->save();

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'Node title',
      'field_content' => [$paragraph],
    ]);
    $node->save();
    $node->addTranslation('de', [
      'title' => 'Node title DE',
    ]);
    $this->container->get('content_translation.manager')
      ->getTranslationMetadata($node->getTranslation('de'))
      ->setSource('en');
    $node->save();

    $paragraph_storage->resetCache([$paragraph->id()]);
    $this->container->get('entity.memory_cache')->deleteAll();
    $this->assertFalse($paragraph_storage->load($paragraph->id())->hasTranslation('de'));

    return $node;
  }

  /**
   * Configures asymmetric translations for pages and their paragraphs.
   *
   * Turns the configuration of self::setUp() into the asymmetric one: the
   * reference field becomes translatable, so that every host translation has
   * its own paragraphs, and the paragraphs themselves become untranslatable.
   * Call it before any entity is created.
   */
  protected function setUpAsymmetricTranslations() {
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->load('node.field_content');
    $field_storage->set('translatable', TRUE);
    $field_storage->save();
    $field = $this->entityTypeManager->getStorage('field_config')->load('node.page.field_content');
    $field->set('translatable', TRUE);
    $field->save();

    $content_translation_manager = $this->container->get('content_translation.manager');
    $content_translation_manager->setEnabled('paragraph', 'text', FALSE);
    $content_translation_manager->setEnabled('paragraph', 'section', FALSE);
    $entity_field_manager = $this->container->get('entity_field.manager');
    $entity_field_manager->getFieldDefinitions('paragraph', 'text')['field_text']
      ->setTranslatable(FALSE)
      ->save();

    // The translatability of the fields and the translatability of the
    // paragraph bundles are both cached.
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $entity_field_manager->clearCachedFieldDefinitions();

    // Asserted here, so that a configuration change without effect fails at
    // its source instead of turning a later assertion green.
    $reference_field = $entity_field_manager->getFieldDefinitions('node', 'page')['field_content'];
    $this->assertTrue($reference_field->isTranslatable());
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => 'text',
      'langcode' => 'en',
    ]);
    $this->assertFalse($paragraph->isTranslatable());
  }

  /**
   * Sets a user who may view and update pages as the current user.
   *
   * The first user created is user 1, who bypasses access checks, so a
   * placeholder takes that ID.
   */
  protected function setUpEditor() {
    $this->createUser();
    \Drupal::currentUser()->setAccount($this->createUser([
      'access content',
      'edit any page content',
    ]));
  }

  /**
   * Sets the changed time of every translation of a node and saves it.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param int $timestamp
   *   The changed time.
   */
  protected function setChangedTimeOnAllTranslations(NodeInterface $node, int $timestamp) {
    $expected = [];
    foreach ($node->getTranslationLanguages() as $langcode => $language) {
      $node->getTranslation($langcode)->setChangedTime($timestamp);
      $expected[$langcode] = $timestamp;
    }
    $node->save();
    $this->assertEquals($expected, $this->loadChangedTimes($node));
  }

  /**
   * Loads the stored changed time of every translation of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return int[]
   *   The changed times, keyed by langcode.
   */
  protected function loadChangedTimes(NodeInterface $node): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$node->id()]);
    $this->container->get('entity.memory_cache')->deleteAll();
    $stored_node = $storage->load($node->id());
    $changed_times = [];
    foreach ($stored_node->getTranslationLanguages() as $langcode => $language) {
      $changed_times[$langcode] = (int) $stored_node->getTranslation($langcode)->getChangedTime();
    }
    return $changed_times;
  }

  /**
   * Edits the text of the first component of a layout in the tempstore.
   *
   * Changes the layout the way the component edit form does.
   *
   * @param string $storage_key
   *   The layout's tempstore storage key.
   * @param string $text
   *   The new text of the component's text field.
   */
  protected function editFirstComponentText(string $storage_key, string $text) {
    $tempstore = $this->container->get('layout_paragraphs.tempstore_repository');
    $layout = $tempstore->getWithStorageKey($storage_key);
    $paragraph = $layout->getParagraphsReferenceField()->get(0)->entity;
    $paragraph->set('field_text', $text);
    $paragraph->setNeedsSave(TRUE);
    $layout->setComponent($paragraph);
    $tempstore->set($layout);
  }

  /**
   * Loads the stored text of every translation of a paragraph.
   *
   * @param int $paragraph_id
   *   The paragraph ID.
   *
   * @return string[]
   *   The texts, keyed by langcode.
   */
  protected function loadParagraphTexts(int $paragraph_id): array {
    $storage = $this->entityTypeManager->getStorage('paragraph');
    $storage->resetCache([$paragraph_id]);
    $this->container->get('entity.memory_cache')->deleteAll();
    $paragraph = $storage->load($paragraph_id);
    $texts = [];
    foreach ($paragraph->getTranslationLanguages() as $langcode => $language) {
      $texts[$langcode] = $paragraph->getTranslation($langcode)->get('field_text')->value;
    }
    return $texts;
  }

  /**
   * Loads the stored language of a paragraph.
   *
   * @param int $paragraph_id
   *   The paragraph ID.
   *
   * @return string
   *   The langcode of the paragraph's own language.
   */
  protected function loadParagraphLangcode(int $paragraph_id): string {
    $storage = $this->entityTypeManager->getStorage('paragraph');
    $storage->resetCache([$paragraph_id]);
    $this->container->get('entity.memory_cache')->deleteAll();
    return $storage->load($paragraph_id)->language()->getId();
  }

  /**
   * Loads the paragraph IDs every translation of a node references.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return int[][]
   *   The referenced paragraph IDs, keyed by langcode.
   */
  protected function loadReferencedParagraphIds(NodeInterface $node): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$node->id()]);
    $this->container->get('entity.memory_cache')->deleteAll();
    $stored_node = $storage->load($node->id());
    $paragraph_ids = [];
    foreach ($stored_node->getTranslationLanguages() as $langcode => $language) {
      $values = $stored_node->getTranslation($langcode)->get('field_content')->getValue();
      $paragraph_ids[$langcode] = array_map('intval', array_column($values, 'target_id'));
    }
    return $paragraph_ids;
  }

  /**
   * Loads the paragraph revision IDs every translation of a node references.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return int[][]
   *   The referenced paragraph revision IDs, keyed by langcode.
   */
  protected function loadReferencedParagraphRevisionIds(NodeInterface $node): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$node->id()]);
    $this->container->get('entity.memory_cache')->deleteAll();
    $stored_node = $storage->load($node->id());
    $revision_ids = [];
    foreach ($stored_node->getTranslationLanguages() as $langcode => $language) {
      $values = $stored_node->getTranslation($langcode)->get('field_content')->getValue();
      $revision_ids[$langcode] = array_map('intval', array_column($values, 'target_revision_id'));
    }
    return $revision_ids;
  }

  /**
   * Saves the builder through its AJAX Save button and the save dialog.
   *
   * The first Save opens the dialog. The answer is recorded on the layout as
   * the dialog's own submit records it, and the second Save saves.
   *
   * @param string $uri
   *   The builder URI, without the AJAX query parameters.
   * @param array $data
   *   The builder form fields.
   * @param bool $new_revision
   *   Whether to create a new revision.
   * @param string $log
   *   The revision log message.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response to the second Save.
   */
  protected function saveThroughDialog(string $uri, array $data, bool $new_revision = FALSE, string $log = ''): Response {
    $save = [
      'op' => 'Save',
      '_triggering_element_name' => 'op',
      '_triggering_element_value' => 'Save',
    ];
    $response = $this->doRequest($this->createAjaxRequest($uri . self::AJAX_QUERY, $data + $save));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertContains('openDialog', array_column(json_decode($response->getContent(), TRUE), 'command'));

    $tempstore = $this->container->get('layout_paragraphs.tempstore_repository');
    $layout = $tempstore->getWithStorageKey($data['layout_paragraphs_storage_key']);
    $layout->setThirdPartySetting('layout_paragraphs', 'revision', [
      'create' => $new_revision,
      'log' => $log,
    ]);
    $tempstore->set($layout);

    return $this->doRequest($this->createAjaxRequest($uri . self::AJAX_QUERY, $data + $save));
  }

  /**
   * Closes the builder through its AJAX Close button.
   *
   * @param string $uri
   *   The builder URI, without the AJAX query parameters.
   * @param array $data
   *   The builder form fields.
   *
   * @return string
   *   The build ID of the rebuilt form, which the form cache holds.
   */
  protected function closeBuilder(string $uri, array $data): string {
    $close = [
      '_triggering_element_name' => 'op',
      '_triggering_element_value' => 'Close',
    ];
    $response = $this->doRequest($this->createAjaxRequest($uri . self::AJAX_QUERY, $data + $close));
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $build_id = NULL;
    foreach (json_decode($response->getContent(), TRUE) as $command) {
      if ($command['command'] === 'update_build_id') {
        $build_id = $command['new'];
      }
    }
    $this->assertNotNull($build_id);
    return $build_id;
  }

  /**
   * Returns the hidden fields of the builder form in the current content.
   *
   * @return array
   *   The form build ID, form ID, form token and layout storage key, keyed by
   *   input name.
   */
  protected function getBuilderFormFields(): array {
    $fields = [];
    foreach (['form_build_id', 'form_id', 'form_token', 'layout_paragraphs_storage_key'] as $name) {
      $inputs = $this->cssSelect('input[name="' . $name . '"]');
      $this->assertCount(1, $inputs);
      $fields[$name] = (string) $inputs[0]->attributes()->value[0];
    }
    return $fields;
  }

  /**
   * Validates the route binding of a form built with the given arguments.
   *
   * Builds the form outside the builder route's controller, the only way to
   * give it build arguments the route does not pass, and runs the validator
   * of the route binding on the built form.
   *
   * @param string $uri
   *   The builder URI the form is validated under.
   * @param \Drupal\node\NodeInterface $node
   *   The host entity the form is built for.
   * @param array $args
   *   The build arguments recorded in the form state.
   *
   * @return array
   *   The errors the validator recorded on the form state.
   */
  protected function validateBuilderForm(string $uri, NodeInterface $node, array $args): array {
    $request = Request::create($uri);
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);
    $this->container->get('language_manager')->reset();
    try {
      $request->attributes->add($this->container->get('router.no_access_checks')->matchRequest($request));
      $form_object = $this->container->get('class_resolver')->getInstanceFromDefinition(LayoutParagraphsBuilderForm::class);
      $form_state = new FormState();
      $form_state->setBuildInfo(['args' => $args]);
      // The form builder passes the submitted input on to the form.
      $form_state->setUserInput([]);
      $form = $form_object->buildForm([], $form_state, $node, 'field_content', 'full');
      $element = $form['layout_paragraphs_storage_key'];
      $form_object->validateRouteBinding($element, $form_state, $form);
      return $form_state->getErrors();
    }
    finally {
      $request_stack->pop();
      $this->container->get('language_manager')->reset();
    }
  }

  /**
   * Creates an AJAX form POST request as the browser sends it.
   *
   * @param string $uri
   *   The URI, including the AJAX query parameters.
   * @param array $data
   *   The form data.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function createAjaxRequest(string $uri, array $data): Request {
    $request = Request::create($uri, 'POST', $data);
    $request->headers->set('Accept', 'application/json, text/javascript, */*; q=0.01');
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    return $request;
  }

  /**
   * Make a POST request to a form.
   *
   * @param string $uri
   *   The URI to post to.
   * @param string $op
   *   The operation to perform (not used).
   * @param array $data
   *   The form data.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response from reloading the page after the form submission.
   */
  protected function doPostForm(string $uri, string $op, array $data = []): Response {
    $request = Request::create($uri);
    $response = $this->doRequest($request);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

    $data['form_build_id'] = (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0];
    $data['form_id'] = (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0];
    if (\Drupal::currentUser()->isAuthenticated()) {
      $data['form_token'] = (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0];
    }

    $request = Request::create($uri, 'POST', $data);
    $response = $this->doRequest($request);

    self::assertEquals(303, $response->getStatusCode(), $this->content);

    // Cheat for now and don't check the redirect URL, as our form doesn't
    // redirect to a new location.
    $request = Request::create($uri);
    $response = $this->doRequest($request);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

    return $response;
  }

  /**
   * Sends a request to the HTTP kernel and builds the response.
   *
   * The content of the response is passed to self::setRawContent().
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Exception
   */
  protected function doRequest(Request $request): Response {
    // Start each request with empty entity caches, as a separate request does.
    $this->container->get('entity.memory_cache')->deleteAll();
    $response = $this->container->get('http_kernel')->handle($request);
    $content = $response->getContent();
    self::assertNotFalse($content);
    $this->setRawContent($content);

    return $response;
  }

}
