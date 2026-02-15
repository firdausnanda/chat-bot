<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PineconeService
{
  protected string $apiKey;
  protected ?string $environment;
  protected ?string $indexName;
  protected string $baseUrl;

  public function __construct()
  {
    $this->apiKey = config('services.pinecone.api_key');
    $this->environment = config('services.pinecone.environment');
    $this->indexName = config('services.pinecone.index_name');
    $host = config('services.pinecone.host');

    if ($host) {
      $this->baseUrl = rtrim($host, '/');
      if (!str_starts_with($this->baseUrl, 'https://')) {
        $this->baseUrl = 'https://' . $this->baseUrl;
      }
    } elseif ($this->indexName) {
      if (str_starts_with($this->indexName, 'https://')) {
        $this->baseUrl = rtrim($this->indexName, '/');
      } elseif (str_contains($this->indexName, '.svc.pinecone.io')) {
        $this->baseUrl = 'https://' . rtrim($this->indexName, '/');
      } else {
        // Fallback requires environment
        $env = $this->environment ?? '';
        $this->baseUrl = "https://{$this->indexName}-{$env}.svc.pinecone.io";
      }
    } else {
      // No configuration found
      $this->baseUrl = '';
    }
  }

  /**
   * Upsert vectors into Pinecone index.
   *
   * @param array $vectors Array of ['id' => string, 'values' => array, 'metadata' => array]
   * @return array
   */
  public function upsert(array $vectors): array
  {
    try {
      $response = Http::withHeaders([
        'Api-Key' => $this->apiKey,
        'Content-Type' => 'application/json',
      ])->post("{$this->baseUrl}/vectors/upsert", [
            'vectors' => $vectors,
          ]);

      if ($response->failed()) {
        Log::error('Pinecone upsert failed', [
          'status' => $response->status(),
          'body' => $response->body(),
        ]);
        return ['error' => 'Upsert failed: ' . $response->body()];
      }

      return $response->json();
    } catch (\Exception $e) {
      Log::error('Pinecone upsert exception', ['message' => $e->getMessage()]);
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Query Pinecone for similar vectors.
   *
   * @param array $vector The query embedding vector
   * @param int $topK Number of results to return
   * @param array $filter Optional metadata filter
   * @return array
   */
  public function query(array $vector, int $topK = 5, array $filter = []): array
  {
    try {
      $payload = [
        'vector' => $vector,
        'topK' => $topK,
        'includeMetadata' => true,
      ];

      if (!empty($filter)) {
        $payload['filter'] = $filter;
      }

      $response = Http::withHeaders([
        'Api-Key' => $this->apiKey,
        'Content-Type' => 'application/json',
      ])->post("{$this->baseUrl}/query", $payload);

      if ($response->failed()) {
        Log::error('Pinecone query failed', [
          'status' => $response->status(),
          'body' => $response->body(),
        ]);
        return ['matches' => []];
      }

      return $response->json();
    } catch (\Exception $e) {
      Log::error('Pinecone query exception', ['message' => $e->getMessage()]);
      return ['matches' => []];
    }
  }

  /**
   * Delete vectors by their IDs.
   *
   * @param array $ids
   * @return array
   */
  public function deleteByIds(array $ids): array
  {
    try {
      $response = Http::withHeaders([
        'Api-Key' => $this->apiKey,
        'Content-Type' => 'application/json',
      ])->post("{$this->baseUrl}/vectors/delete", [
            'ids' => $ids,
          ]);

      if ($response->failed()) {
        Log::error('Pinecone delete failed', [
          'status' => $response->status(),
          'body' => $response->body(),
        ]);
        return ['error' => 'Delete failed: ' . $response->body()];
      }

      return $response->json() ?? ['success' => true];
    } catch (\Exception $e) {
      Log::error('Pinecone delete exception', ['message' => $e->getMessage()]);
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Delete all vectors in the index.
   *
   * @return array
   */
  public function deleteAll(): array
  {
    try {
      $response = Http::withHeaders([
        'Api-Key' => $this->apiKey,
        'Content-Type' => 'application/json',
      ])->post("{$this->baseUrl}/vectors/delete", [
            'deleteAll' => true,
          ]);

      return $response->json() ?? ['success' => true];
    } catch (\Exception $e) {
      Log::error('Pinecone deleteAll exception', ['message' => $e->getMessage()]);
      return ['error' => $e->getMessage()];
    }
  }

  public function getBaseUrl(): string
  {
    return $this->baseUrl;
  }
}
