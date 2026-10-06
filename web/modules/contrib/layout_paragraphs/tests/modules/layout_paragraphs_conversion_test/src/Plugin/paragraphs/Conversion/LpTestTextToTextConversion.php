<?php

declare(strict_types=1);

namespace Drupal\layout_paragraphs_conversion_test\Plugin\paragraphs\Conversion;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs\Attribute\ParagraphsConversion;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsConversionBase;

/**
 * Test conversion plugin for lp_test_text → lp_test_text.
 *
 * Used by Cypress tests that exercise the conversion UI with the
 * lp_test_text paragraph type provided by layout_paragraphs_setup_test.
 *
 * @internal
 *   For testing purposes only.
 */
#[ParagraphsConversion(
  id: 'layout_paragraphs_lp_test_text_to_lp_test_text',
  label: new TranslatableMarkup('Convert lp_test_text to lp_test_text (test)'),
  source_type: 'lp_test_text',
  target_types: ['lp_test_text'],
  weight: 0
)]
class LpTestTextToTextConversion extends ParagraphsConversionBase {

  /**
   * {@inheritdoc}
   */
  public function convert(array $settings, ParagraphInterface $original_paragraph, ?array $converted_paragraphs = NULL) {
    $text_value = '';
    if ($original_paragraph->hasField('field_lp_test_text') && !$original_paragraph->get('field_lp_test_text')->isEmpty()) {
      $text_value = $original_paragraph->get('field_lp_test_text')->value;
    }
    return [
      ['type' => 'lp_test_text', 'field_lp_test_text' => ['value' => $text_value]],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConversionForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    return $form;
  }

}
