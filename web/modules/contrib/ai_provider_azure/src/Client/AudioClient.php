<?php

namespace Drupal\ai_provider_azure\Client;

/**
 * Lightweight audio client for endpoints that don't fit the SDK's shape.
 *
 * Always used in literal mode: unlike ChatClient and EmbeddingsClient
 * there is no legacy tail-stripped mode to preserve here, since no prior
 * version of this module supported a lightweight audio client.
 */
class AudioClient extends AbstractLightweightClient {

  /**
   * Transcribes audio, sending the file as multipart/form-data.
   *
   * @param array $parameters
   *   The parameters to use. The 'file' key, if present, must be a file
   *   resource; every other value is sent as a plain form field.
   *
   * @return \Drupal\ai_provider_azure\Client\ChatResult|string
   *   The response, or a string on failure to json_decode.
   */
  public function transcribe(array $parameters) {
    $multipart = [];
    foreach ($parameters as $key => $value) {
      $part = ['name' => $key, 'contents' => $value];
      if ($key === 'file') {
        $part['filename'] = 'audio.mp3';
      }
      $multipart[] = $part;
    }
    $response = $this->sendRequest($this->requestUri(), ['multipart' => $multipart]);
    return $this->decodeJsonResponse($response);
  }

  /**
   * Generates speech audio, returning the raw binary response body.
   *
   * @param array $parameters
   *   The parameters to use.
   *
   * @return string
   *   The raw audio binary.
   */
  public function speech(array $parameters) {
    $response = $this->sendRequest($this->requestUri(), ['json' => $parameters]);
    return $response->getBody()->getContents();
  }

}
