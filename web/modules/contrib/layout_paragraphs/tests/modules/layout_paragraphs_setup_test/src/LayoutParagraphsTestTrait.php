<?php

declare(strict_types=1);

namespace Drupal\layout_paragraphs_setup_test;

use Behat\Mink\Exception\ExpectationException;

/**
 * Trait for Layout Paragraphs test functionality.
 *
 * This trait provides all the helper methods that were previously in
 * BuilderTestBase, making them reusable across different test classes.
 */
trait LayoutParagraphsTestTrait {

  /**
   * List of admin permissions.
   *
   * @var array
   */
  protected $adminPermissions = [
    'administer site configuration',
    'administer node fields',
    'administer node display',
    'administer paragraphs types',
  ];

  /**
   * List of content creation related permissions.
   *
   * @var array
   */
  protected $contentPermissions = [
    'create page content',
    'edit own page content',
    'create lp_test_ct content',
    'edit own lp_test_ct content',
  ];

  /**
   * Adds a content type with a layout paragraphs field.
   *
   * @param string $type_name
   *   The name of the content type.
   * @param string $paragraph_field
   *   The name of the paragraphs reference field.
   */
  protected function addLayoutParagraphedContentType($type_name, $paragraph_field) {
    $this->addParagraphedContentType($type_name, $paragraph_field, 'layout_paragraphs');
    // Add "section" and "text" paragraph types to the content type.
    $this->drupalGet('admin/structure/types/manage/' . $type_name . '/fields/node.' . $type_name . '.' . $paragraph_field);
    // Enables all paragraph types.
    $this->submitForm([
      'settings[handler_settings][negate]' => '1',
    ], 'Save settings');
    // Use "Layout Paragraphs" formatter for the content field.
    $this->drupalGet('admin/structure/types/manage/' . $type_name . '/display/default');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('fields[' . $paragraph_field . '][type]', 'layout_paragraphs');
    $this->assertSession()->assertWaitOnAjaxRequest(10000, 'Unable to choose layout paragraphs (fields[' . $paragraph_field . '][type]) field formatter.');
    $this->submitForm([], 'Save');
  }

  /**
   * Uses Javascript to make a DOM element visible.
   *
   * @param string $selector
   *   A css selector.
   */
  protected function forceVisible($selector) {
    $this->getSession()->executeScript("jQuery('{$selector}').css({display:'inline-block', opacity:1, visibility: 'visible'});");
  }

  /**
   * Uses Javascript to hide a DOM element.
   *
   * @param string $selector
   *   A css selector.
   */
  protected function forceHidden($selector) {
    $this->getSession()->executeScript("jQuery('{$selector}').css({display:'none', opacity:0, visibility: 'hidden'});");
  }

  /**
   * Inserts a text component by clicking the "+" button.
   *
   * @param string $text
   *   The text for the component's field_text value.
   * @param string $css_selector
   *   A css selector targeting the "+" button.
   */
  protected function addTextComponent($text, $css_selector) {
    $page = $this->getSession()->getPage();
    // Add a text item to first column.
    // Because there are only two component types and sections cannot
    // be nested, this will load the text component form directly.
    $button = $page->find('css', $css_selector);
    $button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $title = $page->find('css', '.ui-dialog-title');
    if ($title->getText() == 'Choose a component') {
      $page->clickLink('lp_test_text');
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    $dialog = $page->find('css', '.lpb-dialog');
    $style = $dialog->getAttribute('style');
    if (strpos($style, 'width: 90%;') === FALSE || strpos($style, 'height: auto;') === FALSE) {
      throw new ExpectationException('Incorrect dialog width or height settings', $this->getSession()->getDriver());
    }

    $this->assertSession()->pageTextContains('field_lp_test_text');

    $page->fillField('field_lp_test_text[0][value]', $text);
    // Force show the hidden submit button so we can click it.
    $this->getSession()->executeScript("jQuery('.lpb-btn--save').attr('style', '');");
    $button = $this->assertSession()->waitForElementVisible('css', ".lpb-btn--save");
    $button->press();

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains($text);
  }

  /**
   * Inserts a text component by clicking the "+" button and choosing "section".
   *
   * @param int $columns_choice
   *   Which column option to choose.
   * @param string $css_selector
   *   A css selector targeting the "+" button.
   */
  protected function addSectionComponent(int $columns_choice, $css_selector) {

    $page = $this->getSession()->getPage();
    // Click the Add Component button.
    $page->find('css', $css_selector)->click();
    $this->assertNotEmpty($this->assertSession()->waitForText('Choose a component'));

    // Add a section.
    $page->clickLink('lp_test_section');
    $this->assertNotEmpty($this->assertSession()->waitForText('Create new section'));

    // Choose a layout.
    $elements = $page->findAll('css', '.layout-select__item label.option');
    $elements[$columns_choice]->click();
    $this->assertNotEmpty($this->assertSession()->waitForText('Choose a layout:'));
    $this->getSession()->wait(1000);

    // Save the layout.
    $button = $page->find('css', 'button.lpb-btn--save');
    $button->click();
    // $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->wait(1000);
  }

  /**
   * Creates a new user with provided permissions and logs them in.
   *
   * @param array $permissions
   *   An array of permissions.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The user.
   */
  protected function loginWithPermissions(array $permissions) {
    $user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($user);
    return $user;
  }

  /**
   * Asserts that provided strings appear on page in same order as in array.
   *
   * @param array $strings
   *   A list of strings in the order they are expected to appear.
   * @param string $assert_message
   *   Message if assertion fails.
   */
  protected function assertOrderOfStrings(array $strings, $assert_message = 'Strings are not in correct order.') {
    $page = $this->getSession()->getPage();
    $page_text = $page->getHtml();
    $high_mark = -1;
    foreach ($strings as $string) {
      $this->assertSession()->pageTextContains($string);
      $pos = strpos($page_text, $string);
      if ($pos <= $high_mark) {
        throw new ExpectationException($assert_message, $this->getSession()->getDriver());
      }
      $high_mark = $pos;
    }
  }

  /**
   * Simulates pressing a key with javascript.
   *
   * @param string $key_code
   *   The string key code (i.e. ArrowUp, Enter).
   */
  protected function keyPress($key_code) {
    $script = 'var e = new KeyboardEvent("keydown", {bubbles : true, cancelable : true, code: "' . $key_code . '"});
    document.body.dispatchEvent(e);';
    $this->getSession()->executeScript($script);
  }

  /**
   * Enables the frontend builder formatter for a content type and field.
   */
  protected function useFrontEndBuilderFormatter($type, $field) {
    $this->loginWithPermissions($this->adminPermissions);
    $this->drupalGet('admin/structure/types/manage/' . $type . '/display/default');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('fields[' . $field . '][type]', 'layout_paragraphs_builder');
    $this->assertSession()->assertWaitOnAjaxRequest(10000, 'Unable to choose layout paragraphs builder (fields[' . $field . '][type]) field formatter.');
    $this->submitForm([], 'Save');
    $this->drupalLogout();
  }

  /**
   * Enables the frontend editor.
   */
  protected function enableFrontendBuilder() {
    $this->forceVisible('.lpb-enable-button');
    $this->getSession()->getPage()->find('css', '.lpb-enable-button')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementExists('css', '.layout-paragraphs-builder-form');
  }

  /**
   * Save the frontend builder.
   */
  protected function saveFrontendBuilder() {
    $page = $this->getSession()->getPage();
    $page->find('css', '.lpb-form__actions .button--primary')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Close the frontend builder.
   */
  protected function closeFrontendBuilder() {
    $page = $this->getSession()->getPage();
    $page->find('css', '.lpb-form__actions .lpb-btn--cancel')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Save and close the frontend builder.
   */
  protected function saveAndCloseFrontendBuilder() {
    $this->saveFrontendBuilder();
    $this->closeFrontendBuilder();
  }

  /**
   * Setup Layout Paragraphs test environment.
   *
   * This method handles the setup that was previously in
   * BuilderTestBase::setUp().
   */
  protected function layoutParagraphsSetUp() {
    // The basic paragraph types and content type setup is now handled by
    // the layout_paragraphs_setup_test module's installation.
    // This method can be used for additional per-test setup if needed.
    // Ensure the setup test module is enabled.
    \Drupal::service('module_installer')->install(['layout_paragraphs_setup_test']);
  }

}
