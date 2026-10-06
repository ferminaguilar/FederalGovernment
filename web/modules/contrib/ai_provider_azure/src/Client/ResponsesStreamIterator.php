<?php

namespace Drupal\ai_provider_azure\Client;

use Psr\Http\Message\StreamInterface;

/**
 * Reads a Responses API server-sent-events body into decoded event arrays.
 *
 * Each Responses API streaming event carries its own event name in the
 * JSON payload's "type" key, so only the "data:" lines need to be read;
 * the separate SSE "event:" line is redundant and is ignored, same as the
 * openai-php SDK does for its own typed Responses streaming.
 */
class ResponsesStreamIterator implements \IteratorAggregate {

  /**
   * The response body stream.
   *
   * @var \Psr\Http\Message\StreamInterface
   */
  protected StreamInterface $body;

  /**
   * Constructor.
   *
   * @param \Psr\Http\Message\StreamInterface $body
   *   The response body stream.
   */
  public function __construct(StreamInterface $body) {
    $this->body = $body;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Generator {
    $buffer = '';
    while (!$this->body->eof() || $buffer !== '') {
      if (!$this->body->eof()) {
        $buffer .= $this->body->read(8192);
      }
      // SSE events are separated by a blank line.
      while (($pos = strpos($buffer, "\n\n")) !== FALSE) {
        $event = substr($buffer, 0, $pos);
        $buffer = substr($buffer, $pos + 2);
        foreach (explode("\n", $event) as $line) {
          if (!str_starts_with($line, 'data:')) {
            continue;
          }
          $data = trim(substr($line, strlen('data:')));
          if ($data === '' || $data === '[DONE]') {
            continue;
          }
          $decoded = json_decode($data, TRUE);
          if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            yield $decoded;
          }
        }
      }
      if ($this->body->eof()) {
        // No more data will arrive; whatever is left in the buffer without
        // a trailing blank line separator cannot become a full event.
        break;
      }
    }
  }

}
