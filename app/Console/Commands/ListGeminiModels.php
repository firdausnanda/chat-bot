<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ListGeminiModels extends Command
{
  protected $signature = 'app:list-gemini-models';
  protected $description = 'List available Gemini models';

  public function handle()
  {
    $apiKey = config('services.gemini.api_key');
    $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    $this->info("Using API Key: " . substr($apiKey, 0, 5) . '...');

    $response = Http::get("{$baseUrl}/models?key={$apiKey}");

    if ($response->failed()) {
      $this->error('Failed to list models: ' . $response->body());
      return;
    }

    $models = $response->json('models', []);

    $this->table(
      ['Name', 'Supported Generation Methods'],
      array_map(fn($m) => [
        $m['name'],
        implode(', ', $m['supportedGenerationMethods'] ?? [])
      ], $models)
    );

    $this->info("Total models: " . count($models));
  }
}
