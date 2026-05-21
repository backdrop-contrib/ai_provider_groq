<?php

/**
 * @file
 * Groq adapter for AI core.
 */

class AIGroqAdapter extends AIAdapterBase {

  use AICompatibleTrait;

  /**
   * Groq compatibility base URL.
   *
   * @var string
   */
  protected $baseUrl = 'https://api.groq.com/openai/v1';

  /**
   * {@inheritdoc}
   */
  protected function getDefaultHeaders(): array {
    return [
      'Authorization' => 'Bearer ' . $this->apiKey,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getModels(): array {
    $models = [];

    try {
      $result = $this->makeRequest($this->baseUrl . '/models', [], [], 'GET', 10);
      if (!empty($result['data']) && is_array($result['data'])) {
        foreach ($result['data'] as $model) {
          $id = $model['id'] ?? ($model['name'] ?? NULL);
          if (empty($id)) {
            continue;
          }
          $models[$id] = $model['name'] ?? $id;
        }
      }
    }
    catch (\Exception $e) {
      watchdog('ai_provider_groq', 'Failed to fetch Groq models: @message', ['@message' => $e->getMessage()], WATCHDOG_DEBUG);
    }

    if (empty($models)) {
      $models = [
        'llama-3.3-70b-versatile' => 'Llama 3.3 70B Versatile',
        'llama-3.1-8b-instant' => 'Llama 3.1 8B Instant',
        'mixtral-8x7b-32768' => 'Mixtral 8x7B',
        'gemma2-9b-it' => 'Gemma 2 9B IT',
      ];
    }

    asort($models);
    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelsByCapability($capability): array {
    $models = $this->getModels();
    $filtered = [];

    foreach ($models as $id => $label) {
      $ok = FALSE;
      switch ($capability) {
        case 'text':
        case 'chat':
          $ok = TRUE;
          break;

        case 'embeddings':
        case 'embedding':
        case 'image':
        case 'vision':
        case 'moderation':
          $ok = FALSE;
          break;
      }

      if ($ok) {
        $filtered[$id] = $label;
      }
    }

    backdrop_alter('ai_model_capabilities', $filtered, $capability, $this);
    return $filtered;
  }

  /**
   * {@inheritdoc}
   */
  public function completions(string $model, string $prompt, $temperature, $max_tokens = 512, bool $stream_response = FALSE) {
    try {
      $payload = [
        'model' => $model,
        'prompt' => trim($prompt),
        'temperature' => (float) $temperature,
      ];
      if ((int) $max_tokens > 0) {
        $payload['max_tokens'] = (int) $max_tokens;
      }

      if ($stream_response) {
        return $this->buildStreamingResponse($this->baseUrl . '/completions', [
          'method' => 'POST',
          'headers' => array_merge([
            'Accept' => 'text/event-stream',
            'Content-Type' => 'application/json',
          ], $this->getDefaultHeaders()),
          'data' => json_encode($payload),
          'timeout' => 300,
        ], function ($data) {
          return $data['choices'][0]['delta']['content'] ?? $data['choices'][0]['text'] ?? '';
        });
      }

      $result = $this->makeRequest($this->baseUrl . '/completions', $payload);
      return trim($result['choices'][0]['text'] ?? $result['choices'][0]['message']['content'] ?? '');
    }
    catch (\Exception $e) {
      watchdog('ai_provider_groq', 'Groq completions error: @message', ['@message' => $e->getMessage()], WATCHDOG_ERROR);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function chat(string $model, array $messages, $temperature, $max_tokens = 1024, bool $stream_response = FALSE, array $context_extra = []) {
    try {
      $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => (float) $temperature,
      ];
      if ((int) $max_tokens > 0) {
        $payload['max_tokens'] = (int) $max_tokens;
      }

      if ($stream_response) {
        return $this->buildStreamingResponse($this->baseUrl . '/chat/completions', [
          'method' => 'POST',
          'headers' => array_merge([
            'Accept' => 'text/event-stream',
            'Content-Type' => 'application/json',
          ], $this->getDefaultHeaders()),
          'data' => json_encode($payload),
          'timeout' => 300,
        ], function ($data) {
          return $data['choices'][0]['delta']['content'] ?? $data['choices'][0]['message']['content'] ?? $data['choices'][0]['text'] ?? '';
        });
      }

      $result = $this->makeRequest($this->baseUrl . '/chat/completions', $payload);
      return trim($result['choices'][0]['message']['content'] ?? $result['choices'][0]['text'] ?? '');
    }
    catch (\Exception $e) {
      watchdog('ai_provider_groq', 'Groq chat error: @message', ['@message' => $e->getMessage()], WATCHDOG_ERROR);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function images(string $model, string $prompt, string $size, string $response_format, string $quality = 'standard', string $style = 'natural', ?string $output_format = NULL) {
    watchdog('ai_provider_groq', 'Image generation is not supported by Groq.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Image generation is not supported by Groq.');
  }

  /**
   * {@inheritdoc}
   */
  public function textToSpeech(string $model, string $input, string $voice, string $response_format) {
    watchdog('ai_provider_groq', 'Text-to-speech is not supported by Groq.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Text-to-speech is not supported by Groq.');
  }

  /**
   * {@inheritdoc}
   */
  public function speechToText(string $model, string $file, string $task = 'transcribe', $temperature = 0.4, string $response_format = 'verbose_json') {
    watchdog('ai_provider_groq', 'Speech-to-text is not supported by Groq.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Speech-to-text is not supported by Groq.');
  }

  /**
   * {@inheritdoc}
   */
  public function moderation(string $input, string $model = 'omni-moderation-latest'): array {
    watchdog('ai_provider_groq', 'Moderation is not supported by Groq.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Moderation is not supported by Groq.');
  }

  /**
   * {@inheritdoc}
   */
  public function embedding(string $input, string $model, bool $log = TRUE): array {
    watchdog('ai_provider_groq', 'Embeddings are not supported by Groq.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Embeddings are not supported by Groq.');
  }

  /**
   * {@inheritdoc}
   */
  public function chatWithTools(string $model, array $messages, array $tools, $temperature, $max_tokens = 1024, string $tool_choice = 'auto', array $context_extra = []): array {
    try {
      $payload = [
        'model' => $model,
        'messages' => $messages,
        'tools' => $tools,
        'tool_choice' => $tool_choice,
        'temperature' => (float) $temperature,
      ];
      if ((int) $max_tokens > 0) {
        $payload['max_tokens'] = (int) $max_tokens;
      }

      $result = $this->makeRequest($this->baseUrl . '/chat/completions', $payload);
      return $this->normalizeToolResponse($result);
    }
    catch (\Exception $e) {
      watchdog('ai_provider_groq', 'Groq chatWithTools error: @message', ['@message' => $e->getMessage()], WATCHDOG_ERROR);
      throw $e;
    }
  }

  protected function normalizeToolResponse(array $result): array {
    $choice = $result['choices'][0] ?? [];
    $message = $choice['message'] ?? [];
    $tool_calls = [];

    foreach ($message['tool_calls'] ?? [] as $tool_call) {
      $arguments = $tool_call['function']['arguments'] ?? '{}';
      if (is_string($arguments)) {
        $arguments = json_decode($arguments, TRUE) ?? [];
      }
      $tool_calls[] = [
        'id' => $tool_call['id'] ?? '',
        'name' => $tool_call['function']['name'] ?? '',
        'arguments' => $arguments,
      ];
    }

    return [
      'finish_reason' => $choice['finish_reason'] ?? 'stop',
      'content' => trim($message['content'] ?? ''),
      'tool_calls' => $tool_calls,
      'raw' => $result,
    ];
  }
}
