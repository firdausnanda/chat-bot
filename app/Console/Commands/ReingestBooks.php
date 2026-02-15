<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\EmbeddingService;
use App\Services\PineconeService;
use Illuminate\Console\Command;

class ReingestBooks extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'app:reingest-books';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Re-ingest all books into Pinecone with new embeddings';

  /**
   * Execute the console command.
   */
  public function handle(EmbeddingService $embeddingService, PineconeService $pineconeService)
  {
    $this->info('Starting re-ingestion process...');
    $this->info('Pinecone Base URL: ' . $pineconeService->getBaseUrl());

    // Step 1: Wipe the index
    $this->info('Wiping existing Pinecone index...');
    $wipeResult = $pineconeService->deleteAll();
    if (isset($wipeResult['error'])) {
      $this->error('Failed to wipe index: ' . $wipeResult['error']);
      if (!$this->confirm('Do you want to continue anyway?')) {
        return;
      }
    } else {
      $this->info('Index wiped successfully.');
    }

    // Step 2: Iterate and re-ingest
    $books = Book::all();
    $bar = $this->output->createProgressBar(count($books));
    $bar->start();

    foreach ($books as $book) {
      $chunks = $this->createChunks($book);

      $vectors = [];
      foreach ($chunks as $index => $chunk) {
        // Add a small delay to avoid rate limits
        usleep(100000); // 100ms

        $embedding = $embeddingService->embed($chunk['text']);

        if (empty($embedding)) {
          $this->error("Failed to embed chunk for book: {$book->title}");
          continue;
        }

        $vectors[] = [
          'id' => "book-{$book->id}-chunk-{$index}",
          'values' => $embedding,
          'metadata' => [
            'item_id' => $book->id,
            'content_chunk' => $chunk['text'],
            'category' => $book->category,
          ],
        ];
      }

      if (!empty($vectors)) {
        $result = $pineconeService->upsert($vectors);
        if (isset($result['error'])) {
          $this->error("Failed to upsert book: {$book->title}. Error: " . $result['error']);
        }
      }

      $bar->advance();
    }

    $bar->finish();
    $this->newLine();
    $this->info('Re-ingestion completed!');
  }

  /**
   * Copied from BookController to ensure consistency
   */
  protected function createChunks(Book $book): array
  {
    $chunks = [];

    // Main metadata chunk
    $chunks[] = [
      'text' => "Book: {$book->title}. Author: {$book->author}. Category: {$book->category}. "
        . "Published: {$book->published_year}. Location: Rack {$book->rack_location}. "
        . "Description: {$book->description}",
    ];

    // Title-focused chunk
    $chunks[] = [
      'text' => "The book titled \"{$book->title}\" was written by {$book->author} "
        . "and belongs to the {$book->category} category.",
    ];

    // Description chunk
    if ($book->description && strlen($book->description) > 50) {
      $chunks[] = [
        'text' => "About \"{$book->title}\": {$book->description}",
      ];
    }

    return $chunks;
  }
}
