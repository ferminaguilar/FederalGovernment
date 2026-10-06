<?php

namespace Drupal\ai_provider_azure\Client;

/**
 * Lightweight provider client for mimicking OpenAI\Client.
 */
class EmbeddingsClient extends AbstractLightweightClient {

  /**
   * The input.
   *
   * @var string
   */
  protected string $input;

  /**
   * Set the input.
   *
   * @param string $input
   *   The message key.
   */
  public function setInput(string $input) {
    $this->input = $input;
  }

  /**
   * Make a normal create request.
   *
   * @param array $parameters
   *   The parameters to use.
   *
   * @return mixed
   *   The response or a string on failure to json_decode.
   */
  public function create(array $parameters) {
    $response = $this->sendRequest($this->requestUri('embeddings'), ['json' => $parameters]);
    return $this->decodeJsonResponse($response, EmbeddingsResult::class);
  }

}
