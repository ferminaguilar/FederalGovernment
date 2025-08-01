<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Batch;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for the batch builder class.
 *
 * @coversDefaultClass \Drupal\Core\Batch\BatchBuilder
 *
 * @group system
 */
class BatchBuilderTest extends UnitTestCase {

  /**
   * Tests the default values.
   *
   * @covers ::toArray
   */
  public function testDefaultValues(): void {
    $batch = (new BatchBuilder())->toArray();

    $this->assertIsArray($batch);
    $this->assertArrayHasKey('operations', $batch);
    $this->assertIsArray($batch['operations']);
    $this->assertEmpty($batch['operations'], 'Operations array is empty.');
    $this->assertEquals(new TranslatableMarkup('Processing'), $batch['title']);
    $this->assertEquals(new TranslatableMarkup('Initializing.'), $batch['init_message']);
    $this->assertEquals(new TranslatableMarkup('Completed @current of @total.'), $batch['progress_message']);
    $this->assertEquals(new TranslatableMarkup('An error has occurred.'), $batch['error_message']);
    $this->assertNull($batch['finished']);
    $this->assertNull($batch['file']);
    $this->assertArrayHasKey('library', $batch);
    $this->assertIsArray($batch['library']);
    $this->assertEmpty($batch['library']);
    $this->assertArrayHasKey('url_options', $batch);
    $this->assertIsArray($batch['url_options']);
    $this->assertEmpty($batch['url_options']);
    $this->assertArrayHasKey('progressive', $batch);
    $this->assertTrue($batch['progressive']);
    $this->assertArrayNotHasKey('queue', $batch);
  }

  /**
   * Tests setTitle().
   *
   * @covers ::setTitle
   */
  public function testSetTitle(): void {
    $batch = (new BatchBuilder())
      ->setTitle(new TranslatableMarkup('New Title'))
      ->toArray();

    $this->assertEquals(new TranslatableMarkup('New Title'), $batch['title']);
  }

  /**
   * Tests setFinishCallback().
   *
   * @covers ::setFinishCallback
   */
  public function testSetFinishCallback(): void {
    $batch = (new BatchBuilder())
      ->setFinishCallback('\Drupal\Tests\Core\Batch\BatchBuilderTest::finishedCallback')
      ->toArray();

    $this->assertEquals('\Drupal\Tests\Core\Batch\BatchBuilderTest::finishedCallback', $batch['finished']);
  }

  /**
   * Tests setInitMessage().
   *
   * @covers ::setInitMessage
   */
  public function testSetInitMessage(): void {
    $batch = (new BatchBuilder())
      ->setInitMessage(new TranslatableMarkup('New initialization message.'))
      ->toArray();

    $this->assertEquals(new TranslatableMarkup('New initialization message.'), $batch['init_message']);
  }

  /**
   * Tests setProgressMessage().
   *
   * @covers ::setProgressMessage
   */
  public function testSetProgressMessage(): void {
    $batch = (new BatchBuilder())
      ->setProgressMessage(new TranslatableMarkup('Batch in progress...'))
      ->toArray();

    $this->assertEquals(new TranslatableMarkup('Batch in progress...'), $batch['progress_message']);
  }

  /**
   * Tests setErrorMessage().
   */
  public function testSetErrorMessage(): void {
    $batch = (new BatchBuilder())
      ->setErrorMessage(new TranslatableMarkup('Oops. An error has occurred :('))
      ->toArray();

    $this->assertEquals(new TranslatableMarkup('Oops. An error has occurred :('), $batch['error_message']);
  }

  /**
   * Tests setFile().
   *
   * @covers ::setFile
   */
  public function testSetFile(): void {
    $filename = dirname(__DIR__, 6) . '/core/modules/system/tests/modules/batch_test/batch_test.callbacks.inc';
    $this->assertIsNotCallable('_batch_test_callback_1');
    $this->assertIsNotCallable('_batch_test_finished_1');

    $batch = (new BatchBuilder())
      ->setFile($filename)
      ->setFinishCallback('_batch_test_finished_1')
      ->addOperation('_batch_test_callback_1', [])
      ->toArray();
    $this->assertEquals($filename, $batch['file']);
    $this->assertEquals([['_batch_test_callback_1', []]], $batch['operations']);
    $this->assertEquals('_batch_test_finished_1', $batch['finished']);
    $this->assertIsCallable('_batch_test_callback_1');
    $this->assertIsCallable('_batch_test_finished_1');
  }

  /**
   * Tests setting and adding libraries.
   *
   * @covers ::setLibraries
   */
  public function testAddingLibraries(): void {
    $batch = (new BatchBuilder())
      ->setLibraries(['only/library'])
      ->toArray();

    $this->assertEquals(['only/library'], $batch['library']);
  }

  /**
   * Tests setProgressive().
   *
   * @covers ::setProgressive
   */
  public function testSetProgressive(): void {
    $batch_builder = new BatchBuilder();
    $batch = $batch_builder
      ->setProgressive(FALSE)
      ->toArray();

    $this->assertFalse($batch['progressive']);

    $batch = $batch_builder
      ->setProgressive(TRUE)
      ->toArray();

    $this->assertTrue($batch['progressive']);
  }

  /**
   * Tests setQueue().
   *
   * @covers ::setQueue
   */
  public function testSetQueue(): void {
    $batch = (new BatchBuilder())
      ->setQueue('BatchName', '\Drupal\Core\Queue\Batch')
      ->toArray();

    $this->assertEquals([
      'name' => 'BatchName',
      'class' => '\Drupal\Core\Queue\Batch',
    ], $batch['queue'], 'Batch queue has been set.');
  }

  /**
   * Tests queue class exists.
   *
   * @covers ::setQueue
   */
  public function testQueueExists(): void {
    $batch_builder = (new BatchBuilder());
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Class \ThisIsNotAClass does not exist.');
    $batch_builder->setQueue('BatchName', '\ThisIsNotAClass');
  }

  /**
   * Tests queue class implements \Drupal\Core\Queue\QueueInterface.
   *
   * @covers ::setQueue
   */
  public function testQueueImplements(): void {
    $batch_builder = (new BatchBuilder());
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Class Exception does not implement \Drupal\Core\Queue\QueueInterface.');
    $batch_builder->setQueue('BatchName', \Exception::class);
  }

  /**
   * Tests setUrlOptions().
   *
   * @covers ::setUrlOptions
   */
  public function testSetUrlOptions(): void {
    $options = [
      'absolute' => TRUE,
      'language' => 'de',
    ];
    $batch = (new BatchBuilder())
      ->setUrlOptions($options)
      ->toArray();

    $this->assertEquals($options, $batch['url_options']);
  }

  /**
   * Tests addOperation().
   *
   * @covers ::addOperation
   */
  public function testAddOperation(): void {
    $batch_builder = new BatchBuilder();
    $batch = $batch_builder
      ->addOperation('\Drupal\Tests\Core\Batch\BatchBuilderTest::operationCallback')
      ->toArray();

    $this->assertEquals([
      ['\Drupal\Tests\Core\Batch\BatchBuilderTest::operationCallback', []],
    ], $batch['operations']);

    $batch = $batch_builder
      ->addOperation('\Drupal\Tests\Core\Batch\BatchBuilderTest::operationCallback', [2])
      ->addOperation('\Drupal\Tests\Core\Batch\BatchBuilderTest::operationCallback', [3])
      ->toArray();

    $this->assertEquals([
      ['\Drupal\Tests\Core\Batch\BatchBuilderTest::operationCallback', []],
      ['\Drupal\Tests\Core\Batch\BatchBuilderTest::operationCallback', [2]],
      ['\Drupal\Tests\Core\Batch\BatchBuilderTest::operationCallback', [3]],
    ], $batch['operations']);
  }

  /**
   * Tests registering IDs of built batches.
   *
   * @covers ::isSetIdRegistered
   * @covers ::registerSetId
   */
  public function testRegisterIds(): void {
    $setId = $this->randomMachineName();
    $this->assertFalse(BatchBuilder::isSetIdRegistered($setId));
    (new BatchBuilder())->registerSetId($setId);
    $this->assertTrue(BatchBuilder::isSetIdRegistered($setId));
  }

  /**
   * Empty callback for the tests.
   *
   * @internal
   */
  public static function finishedCallback() {
  }

  /**
   * Empty callback for the tests.
   *
   * @internal
   */
  public static function operationCallback() {
  }

}
