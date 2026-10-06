<?php

namespace Drupal\ai_provider_azure;

use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;
use Drupal\ai_provider_azure\Client\ResponsesToolCallChunk;

/**
 * Streamed message iterator for the Responses API.
 *
 * Consumes decoded Responses API server-sent-events (see
 * \Drupal\ai_provider_azure\Client\ResponsesStreamIterator) and turns them
 * into StreamedChatMessage chunks. Only plain text output and function-tool
 * calls are handled; other Responses tool types (web search, code
 * interpreter, computer use, MCP, image generation) are not supported by
 * the "ai" module's tools abstraction and are ignored.
 */
class AzureResponsesMessageIterator extends StreamedChatMessageIterator {

  /**
   * {@inheritdoc}
   */
  public function doIterate(): \Generator {
    foreach ($this->iterator->getIterator() as $event) {
      $type = $event['type'] ?? '';

      switch ($type) {
        case 'response.output_text.delta':
          yield $this->createStreamedChatMessage('assistant', $event['delta'] ?? '', [], NULL, $event);
          break;

        case 'response.output_item.added':
          $item = $event['item'] ?? [];
          if (($item['type'] ?? '') === 'function_call') {
            yield $this->createStreamedChatMessage('assistant', '', [], [
              new ResponsesToolCallChunk($item['call_id'] ?? '', $item['name'] ?? ''),
            ], $event);
          }
          break;

        case 'response.function_call_arguments.delta':
          yield $this->createStreamedChatMessage('assistant', '', [], [
            new ResponsesToolCallChunk('', '', $event['delta'] ?? ''),
          ], $event);
          break;

        case 'response.completed':
        case 'response.incomplete':
        case 'response.failed':
          $response = $event['response'] ?? [];
          $this->setFinishReason($response['status'] ?? $type);
          $message = $this->createStreamedChatMessage('assistant', '', [], NULL, $event);
          $usage = $response['usage'] ?? NULL;
          if ($usage) {
            $message->setInputTokenUsage($usage['input_tokens'] ?? 0);
            $message->setOutputTokenUsage($usage['output_tokens'] ?? 0);
            $message->setTotalTokenUsage($usage['total_tokens'] ?? 0);
            $message->setReasoningTokenUsage($usage['output_tokens_details']['reasoning_tokens'] ?? 0);
            $message->setCachedTokenUsage($usage['input_tokens_details']['cached_tokens'] ?? 0);
          }
          yield $message;
          break;

        case 'error':
          throw new AiResponseErrorException($event['message'] ?? 'Unknown error from the Azure Responses API stream.');
      }
    }
  }

}
