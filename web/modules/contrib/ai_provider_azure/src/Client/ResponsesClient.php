<?php

namespace Drupal\ai_provider_azure\Client;

/**
 * Lightweight client for the OpenAI/Azure Responses API.
 *
 * Always used in literal mode, posting directly to the endpoint exactly
 * as configured, without appending a REST path. Unlike Chat Completions,
 * Responses endpoints (especially custom proxies) can put the deployment
 * name after the 'responses' segment, which a client that always
 * requests "<base-uri>/responses" cannot reproduce.
 */
class ResponsesClient extends AbstractLightweightClient {

  /**
   * Make a normal create request.
   *
   * @param array $parameters
   *   The parameters to use.
   *
   * @return \Drupal\ai_provider_azure\Client\ChatResult|string
   *   The response, or a string on failure to json_decode.
   */
  public function create(array $parameters) {
    $response = $this->sendRequest($this->requestUri(), ['json' => $parameters]);
    return $this->decodeJsonResponse($response);
  }

  /**
   * Make a streamed create request.
   *
   * @param array $parameters
   *   The parameters to use.
   *
   * @return \Drupal\ai_provider_azure\Client\ResponsesStreamIterator
   *   The stream of decoded Responses API events.
   */
  public function createStreamed(array $parameters) {
    $parameters['stream'] = TRUE;
    $response = $this->sendRequest($this->requestUri(), ['json' => $parameters, 'stream' => TRUE]);
    return new ResponsesStreamIterator($response->getBody());
  }

}
