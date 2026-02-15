<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\EmbeddingService;
use App\Services\PineconeService;
use Illuminate\Http\Request;

class BookController extends Controller
{
  /**
   * List all books.
   *
   * GET /api/books
   */
  public function index(Request $request)
  {
    $query = Book::query();

    // Optional search by title or author
    if ($request->has('search')) {
      $search = $request->input('search');
      $query->where(function ($q) use ($search) {
        $q->where('title', 'like', "%{$search}%")
          ->orWhere('author', 'like', "%{$search}%")
          ->orWhere('category', 'like', "%{$search}%");
      });
    }

    // Optional filter by category
    if ($request->has('category')) {
      $query->where('category', $request->input('category'));
    }

    $books = $query->paginate(15);

    return response()->json($books);
  }

  /**
   * Get single book detail.
   *
   * GET /api/books/{id}
   */
  public function show(int $id)
  {
    $book = Book::findOrFail($id);
    return response()->json($book);
  }

  /**
   * Ingest a book's content into Pinecone.
   * This creates vector embeddings from the book's metadata and description.
   *
   * POST /api/books/{id}/ingest
   */
  public function ingest(int $id, EmbeddingService $embeddingService, PineconeService $pineconeService)
  {
    $book = Book::findOrFail($id);

    // Build content chunks from the book's metadata
    $chunks = $this->createChunks($book);

    $vectors = [];
    foreach ($chunks as $index => $chunk) {
      $embedding = $embeddingService->embed($chunk['text']);

      if (empty($embedding)) {
        return response()->json(['error' => 'Failed to generate embedding'], 500);
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

    $result = $pineconeService->upsert($vectors);

    if (isset($result['error'])) {
      return response()->json(['error' => $result['error']], 500);
    }

    return response()->json([
      'message' => "Book '{$book->title}' ingested successfully",
      'chunks_count' => count($vectors),
    ]);
  }

  /**
   * Create text chunks from a book's metadata for embedding.
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

    // Title-focused chunk for better title search
    $chunks[] = [
      'text' => "The book titled \"{$book->title}\" was written by {$book->author} "
        . "and belongs to the {$book->category} category.",
    ];

    // Description chunk (if description is long enough)
    if ($book->description && strlen($book->description) > 50) {
      $chunks[] = [
        'text' => "About \"{$book->title}\": {$book->description}",
      ];
    }

    return $chunks;
  }
}
