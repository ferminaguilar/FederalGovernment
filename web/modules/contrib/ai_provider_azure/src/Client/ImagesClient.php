<?php

namespace Drupal\ai_provider_azure\Client;

/**
 * Lightweight images client for endpoints that don't fit the SDK's shape.
 *
 * Always used in literal mode: unlike ChatClient and EmbeddingsClient
 * there is no legacy tail-stripped mode to preserve here, since no prior
 * version of this module supported a lightweight images client.
 */
class ImagesClient extends AbstractLightweightClient {

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

}
