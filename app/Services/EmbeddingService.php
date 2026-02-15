<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
  protected string $apiKey;
  protected string $model;
  protected string $baseUrl;

  public function __construct()
  {
    $this->apiKey = config('services.gemini.api_key');
    $this->model = 'models/gemini-embedding-001';
    $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
  }

  /**
   * Generate embedding for a single text using Gemini.
   *
   * @param string $text
   * @return array The embedding vector (768 dimensions)
   */
  public function embed(string $text): array
  {
    try {
      $url = "{$this->baseUrl}/{$this->model}:embedContent?key={$this->apiKey}";

      $response = Http::withHeaders([
        'Content-Type' => 'application/json',
      ])->post($url, [
            'content' => [
              'parts' => [
                ['text' => $text]
              ]
            ],
            'taskType' => 'RETRIEVAL_DOCUMENT',
            'title' => 'Embedding', // Title is optional but good practice for retrieval_document
          ]);

      if ($response->failed()) {
        Log::error('Gemini embedding failed', [
          'url' => $url,
          'status' => $response->status(),
          'body' => $response->body(),
        ]);
        return [];
      }

      return $response->json('embedding.values', []);
    } catch (\Exception $e) {
      Log::error('Gemini embedding exception', ['message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Generate embeddings for multiple texts in batch.
   * Gemini doesn't have a direct batch endpoint like OpenAI, so we iterate.
   *
   * @param array $texts
   * @return array Array of embedding vectors
   */
  public function embedBatch(array $texts): array
  {
    $embeddings = [];
    foreach ($texts as $text) {
      $embeddings[] = $this->embed($text);
    }
    return $embeddings;
  }
}
