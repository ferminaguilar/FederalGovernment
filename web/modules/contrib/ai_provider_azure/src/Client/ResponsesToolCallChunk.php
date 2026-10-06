<?php

namespace Drupal\ai_provider_azure\Client;

/**
 * A single accumulation chunk for a streamed Responses API function call.
 *
 * Mirrors the shape StreamedChatMessageIterator::assembleToolCalls()
 * expects from Chat Completions delta.tool_calls entries: the chunk that
 * starts a new tool call carries an 'id', later chunks omit it and only
 * append to 'function.arguments'.
 */
class ResponsesToolCallChunk {

  /**
   * Constructor.
   *
   * @param string $id
   *   The tool call id (call_id), only set on the first chunk.
   * @param string $name
   *   The function name, only set on the first chunk.
   * @param string $argumentsDelta
   *   The partial (or, on the first chunk, empty) arguments JSON text.
   */
  public function __construct(
    protected string $id = '',
    protected string $name = '',
    protected string $argumentsDelta = '',
  ) {}

  /**
   * Get the array representation.
   *
   * @return array
   *   The array.
   */
  public function toArray(): array {
    $function = ['arguments' => $this->argumentsDelta];
    if ($this->name !== '') {
      $function['name'] = $this->name;
    }
    $array = ['function' => $function];
    if ($this->id !== '') {
      $array['id'] = $this->id;
    }
    return $array;
  }

}
