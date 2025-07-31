<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Language\LanguageInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\sitewide_alert\SitewideAlertManager;

// cspell:ignore d'essai

/**
 * Defines a class for testing the sitewide alert entity.
 *
 * @group sitewide_alert
 * @coversDefaultClass \Drupal\sitewide_alert\Entity\SitewideAlert
 */
final class SitewideAlertEntityTest extends SitewideAlertKernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = ['content_translation', 'language'];

  /**
   * The sitewide alert manager.
   *
   * @var \Drupal\sitewide_alert\SitewideAlertManager
   */
  private SitewideAlertManager $sitewideAlertManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->container->get('content_translation.manager')->setEnabled('sitewide_alert', 'sitewide_alert', TRUE);

    // Rebuild container to make sure the sitewide alert manager is available.
    $this->container->get('kernel')->rebuildContainer();

    $this->sitewideAlertManager = $this->container->get('sitewide_alert.sitewide_alert_manager');

  }

  /**
   * Covers ::isPublished.
   *
   * @covers ::isPublished
   */
  public function testIsPublished(): void {
    $alert = $this->createSiteWideAlert();
    $this->assertTrue($alert->isPublished());

    $alert = $this->createSiteWideAlert([
      'status' => FALSE,
    ]);
    $this->assertFalse($alert->isPublished());
  }

  /**
   * Test basic crud.
   *
   * Tests basic entity crud.
   */
  public function testEntityCrud(): void {
    $name = $this->randomMachineName();
    $alert = $this->createSiteWideAlert([
      'name' => $name,
    ]);
    \Drupal::entityTypeManager()->getStorage('sitewide_alert')->loadUnchanged($alert->id());
    $this->assertEquals($name, $alert->label());
  }

  /**
   * Tests creating and retrieving alerts in multiple languages.
   */
  public function testAlertDefaultLanguage(): void {
    $alert = $this->createSiteWideAlert([
      'langcode' => 'fr',
    ]);
    $this->assertEquals('fr', $alert->language()->getId());
    $this->assertCount(0, $this->sitewideAlertManager->activeSitewideAlerts());

    \Drupal::service('language.default')->set(\Drupal::languageManager()->getLanguage('fr'));
    \Drupal::languageManager()->reset();

    $this->assertCount(1, $this->sitewideAlertManager->activeSitewideAlerts());

    $alert = $this->createSiteWideAlert([
      'langcode' => 'it',
    ]);
    $this->assertCount(1, $this->sitewideAlertManager->activeSitewideAlerts());

    \Drupal::service('language.default')->set(\Drupal::languageManager()->getLanguage(LanguageInterface::LANGCODE_NOT_SPECIFIED));
    \Drupal::languageManager()->reset();

    $this->assertCount(0, $this->sitewideAlertManager->activeSitewideAlerts());
  }

  /**
   * Tests alert translations and default language.
   */
  public function testAlertTranslations(): void {
    $this->createSiteWideAlert(['message' => 'Message test']);

    $alert = $this->createSiteWideAlert(['message' => 'Message test']);
    $this->assertEquals('en', $alert->language()->getId());
    $this->assertCount(2, $this->sitewideAlertManager->activeSitewideAlerts());

    $translation = $alert->addTranslation('fr', ['message' => "message d'essai"] + $alert->toArray());
    $translation->save();
    // There should still only be 2 alerts after adding a translation.
    $this->assertCount(2, $this->sitewideAlertManager->activeSitewideAlerts());

    // Let's create another alert in another default language.
    $alert = $this->createSiteWideAlert([
      'langcode' => 'fr',
      'message' => "message d'essai",
    ]);

    \Drupal::service('language.default')->set(\Drupal::languageManager()->getLanguage('fr'));
    \Drupal::languageManager()->reset();

    $alerts = $this->sitewideAlertManager->activeSitewideAlerts();

    $this->assertCount(2, $alerts);
    foreach ($alerts as $alert) {
      // Should not be the first alert as it only exists in English.
      $this->assertNotEquals(1, $alert->id());
      $this->assertEquals("message d'essai", $alert->get('message')->value);
    }
  }

  /**
   * Tests getScheduledEndDateTime().
   *
   * @covers ::getScheduledEndDateTime
   *
   * @throws \Exception
   */
  public function testGetScheduledEndTime(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $alert = $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+7 days')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);
    $end_date = $alert->getScheduledEndDateTime();
    $this->assertInstanceOf(DrupalDateTime::class, $end_date);
    $this->assertEquals($now->modify('+7 days')->getTimestamp(), $end_date->getTimestamp());

    // Test for NULL date.
    $alert = $this->createSiteWideAlert();
    $this->assertNull($alert->getScheduledEndDateTime());
  }

  /**
   * Tests getScheduledStartDateTime().
   *
   * @covers ::getScheduledStartDateTime
   *
   * @throws \Exception
   */
  public function testGetScheduledStartTime(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $alert = $this->createSiteWideAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+7 days')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);
    $start_date = $alert->getScheduledStartDateTime();
    $this->assertInstanceOf(DrupalDateTime::class, $start_date);
    $this->assertEquals($now->getTimestamp(), $start_date->getTimestamp());

    // Test for NULL date.
    $alert = $this->createSiteWideAlert();
    $this->assertNull($alert->getScheduledStartDateTime());
  }

}
