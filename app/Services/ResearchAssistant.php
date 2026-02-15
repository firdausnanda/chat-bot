<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Document;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Generator;

class ResearchAssistant
{
  protected PineconeService $pinecone;
  protected EmbeddingService $embedding;
  protected string $apiKey;
  protected string $chatModel;

  public function __construct(PineconeService $pinecone, EmbeddingService $embedding)
  {
    $this->pinecone = $pinecone;
    $this->embedding = $embedding;
    $this->apiKey = config('services.gemini.api_key');
    $this->chatModel = config('services.gemini.model', 'models/gemini-1.5-flash');
  }

  /**
   * Full RAG pipeline: embed question → query Pinecone → fetch metadata → stream LLM response.
   *
   * @param string $question
   * @param string $searchMode 'all', 'pdf', 'database'
   * @return Generator Yields streamed text chunks and source data
   */
  public function ask(string $question, string $searchMode = 'all'): Generator
  {
    // Step 1: Embed the question
    $queryVector = $this->embedding->embed($question);

    if (empty($queryVector)) {
      yield json_encode([
        'type' => 'error',
        'content' => 'Failed to generate embedding for your question.',
      ]);
      return;
    }

    // Step 2: Query Pinecone for relevant chunks with optional filter
    $filter = $this->buildFilter($searchMode);
    $results = $this->pinecone->query($queryVector, 5, $filter);
    $matches = $results['matches'] ?? [];

    // Step 3: Build context and extract sources
    $context = $this->buildContext($matches);
    $sources = $this->extractSources($matches);

    // Send sources first
    yield json_encode([
      'type' => 'sources',
      'content' => $sources,
    ]);

    // Step 4: Stream Gemini completion with context
    $systemPrompt = $this->buildSystemPrompt($context);

    yield from $this->streamCompletion($systemPrompt, $question);
  }

  /**
   * Non-streaming version of ask.
   *
   * @param string $question
   * @param string $searchMode
   * @return array
   */
  public function askSync(string $question, string $searchMode = 'all'): array
  {
    $queryVector = $this->embedding->embed($question);

    if (empty($queryVector)) {
      return [
        'answer' => 'Failed to generate embedding for your question.',
        'sources' => [],
      ];
    }

    $filter = $this->buildFilter($searchMode);
    $results = $this->pinecone->query($queryVector, 5, $filter);
    $matches = $results['matches'] ?? [];

    $context = $this->buildContext($matches);
    $sources = $this->extractSources($matches);
    $systemPrompt = $this->buildSystemPrompt($context);

    try {
      $url = "https://generativelanguage.googleapis.com/v1beta/{$this->chatModel}:generateContent?key={$this->apiKey}";

      $response = Http::withHeaders([
        'Content-Type' => 'application/json',
      ])->timeout(60)->post($url, [
            'contents' => [
              ['role' => 'user', 'parts' => [['text' => $systemPrompt . "\n\nUser Question: " . $question]]]
            ],
            'generationConfig' => [
              'temperature' => 0.7,
              'maxOutputTokens' => 1024,
            ]
          ]);

      $answer = $response->json('candidates.0.content.parts.0.text', 'Sorry, I could not generate a response.');

      return [
        'answer' => $answer,
        'sources' => $sources,
      ];
    } catch (\Exception $e) {
      Log::error('Gemini completion exception', ['message' => $e->getMessage()]);
      return [
        'answer' => 'An error occurred while generating the response.',
        'sources' => $sources,
      ];
    }
  }

  /**
   * Build Pinecone metadata filter based on search mode.
   */
  protected function buildFilter(string $searchMode): array
  {
    return match ($searchMode) {
      'pdf' => ['source_type' => ['$eq' => 'pdf']],
      'database' => ['source_type' => ['$ne' => 'pdf']],
      default => [],
    };
  }

  /**
   * Build context string from Pinecone matches and local DB metadata.
   */
  public function buildContext(array $matches): string
  {
    if (empty($matches)) {
      // Fall back to all books if no Pinecone matches
      $books = Book::all();
      $context = "Available books in the library:\n\n";
      foreach ($books as $book) {
        $context .= "- Title: {$book->title}\n";
        $context .= "  Author: {$book->author}\n";
        $context .= "  Category: {$book->category}\n";
        $context .= "  Rack Location: {$book->rack_location}\n";
        $context .= "  Description: {$book->description}\n";
        $context .= "  Published Year: {$book->published_year}\n\n";
      }
      return $context;
    }

    $context = "";
    $bookIds = [];
    $documentIds = [];

    foreach ($matches as $match) {
      $metadata = $match['metadata'] ?? [];
      $sourceType = $metadata['source_type'] ?? 'book';
      $chunk = $metadata['content_chunk'] ?? '';
      $score = round($match['score'] ?? 0, 4);

      if ($sourceType === 'pdf') {
        // PDF source
        $filename = $metadata['filename'] ?? 'Unknown';
        $page = $metadata['page'] ?? '?';
        $docId = $metadata['document_id'] ?? null;

        if ($docId && !in_array($docId, $documentIds)) {
          $documentIds[] = $docId;
        }

        $context .= "Relevant content from PDF \"{$filename}\" (page {$page}, score: {$score}):\n";
        $context .= $chunk . "\n\n";
      } else {
        // Book/database source
        $itemId = $metadata['item_id'] ?? null;

        if ($itemId && !in_array($itemId, $bookIds)) {
          $bookIds[] = $itemId;
        }

        $context .= "Relevant content chunk (score: {$score}):\n";
        $context .= $chunk . "\n\n";
      }
    }

    // Fetch book metadata from local DB
    if (!empty($bookIds)) {
      $books = Book::whereIn('id', $bookIds)->get();
      $context .= "\n\nReferenced Books:\n";
      foreach ($books as $book) {
        $context .= "- [{$book->id}] \"{$book->title}\" by {$book->author}";
        $context .= " (Category: {$book->category}, Rack: {$book->rack_location})\n";
      }
    }

    // Fetch document metadata from local DB
    if (!empty($documentIds)) {
      $documents = Document::whereIn('id', $documentIds)->get();
      $context .= "\n\nReferenced PDF Documents:\n";
      foreach ($documents as $doc) {
        $context .= "- [{$doc->id}] \"{$doc->filename}\" ({$doc->pages_count} pages)\n";
      }
    }

    return $context;
  }

  /**
   * Extract source information from Pinecone matches.
   */
  protected function extractSources(array $matches): array
  {
    $sources = [];
    $seenBookIds = [];
    $seenDocIds = [];

    foreach ($matches as $match) {
      $metadata = $match['metadata'] ?? [];
      $sourceType = $metadata['source_type'] ?? 'book';

      if ($sourceType === 'pdf') {
        $docId = $metadata['document_id'] ?? null;

        if ($docId && !in_array($docId, $seenDocIds)) {
          $seenDocIds[] = $docId;

          $document = Document::find($docId);
          if ($document) {
            $sources[] = [
              'type' => 'pdf',
              'id' => $document->id,
              'filename' => $document->filename,
              'page' => $metadata['page'] ?? null,
              'score' => round($match['score'] ?? 0, 4),
            ];
          }
        }
      } else {
        $itemId = $metadata['item_id'] ?? null;

        if ($itemId && !in_array($itemId, $seenBookIds)) {
          $seenBookIds[] = $itemId;

          $book = Book::find($itemId);
          if ($book) {
            $sources[] = [
              'type' => 'book',
              'id' => $book->id,
              'title' => $book->title,
              'author' => $book->author,
              'category' => $book->category,
              'rack_location' => $book->rack_location,
              'score' => round($match['score'] ?? 0, 4),
            ];
          }
        }
      }
    }

    // If no Pinecone matches, provide all books as potential sources
    if (empty($sources)) {
      $books = Book::all();
      foreach ($books as $book) {
        $sources[] = [
          'type' => 'book',
          'id' => $book->id,
          'title' => $book->title,
          'author' => $book->author,
          'category' => $book->category,
          'rack_location' => $book->rack_location,
          'score' => null,
        ];
      }
    }

    return $sources;
  }

  /**
   * Build the system prompt for the LLM.
   */
  protected function buildSystemPrompt(string $context): string
  {
    return <<<PROMPT
You are a helpful AI Librarian for a university library. Your role is to help students and researchers find relevant books and information from the library's collection and uploaded PDF documents.

INSTRUCTIONS:
- Answer questions based on the library's collection data and PDF documents provided below.
- Always reference specific books by their title and author when relevant.
- When citing PDF sources, mention the filename and page number.
- Include the rack location so users can find the physical book.
- If you cannot find relevant information in the collection, say so honestly.
- Be concise but informative in your responses.
- ALWAYS RESPOND IN INDONESIAN LANGUAGE (BAHASA INDONESIA).

LIBRARY COLLECTION DATA:
{$context}
PROMPT;
  }

  /**
   * Stream Gemini chat completion.
   */
  protected function streamCompletion(string $systemPrompt, string $userMessage): Generator
  {
    try {
      $url = "https://generativelanguage.googleapis.com/v1beta/{$this->chatModel}:streamGenerateContent?key={$this->apiKey}&alt=sse";

      $response = Http::withHeaders([
        'Content-Type' => 'application/json',
      ])->withOptions([
            'stream' => true,
          ])->timeout(60)->post($url, [
            'contents' => [
              ['role' => 'user', 'parts' => [['text' => $systemPrompt . "\n\nUser Question: " . $userMessage]]]
            ],
            'generationConfig' => [
              'temperature' => 0.7,
              'maxOutputTokens' => 1024,
            ]
          ]);

      if ($response->status() >= 400) {
        $body = $response->body();
        Log::error('Gemini stream error status', ['status' => $response->status(), 'body' => $body]);
        yield json_encode(['type' => 'error', 'content' => "Gemini API Error: " . $response->status()]);
        return;
      }

      $body = $response->toPsrResponse()->getBody();

      $buffer = '';
      while (!$body->eof()) {
        $chunk = $body->read(1024);
        $buffer .= $chunk;

        // Parse SSE lines
        while (($newlinePos = strpos($buffer, "\n")) !== false) {
          $line = substr($buffer, 0, $newlinePos);
          $buffer = substr($buffer, $newlinePos + 1);

          $line = trim($line);
          if (empty($line))
            continue;

          if (str_starts_with($line, 'data: ')) {
            $data = substr($line, 6);

            $json = json_decode($data, true);
            if ($json && isset($json['candidates'][0]['content']['parts'][0]['text'])) {
              yield json_encode([
                'type' => 'text',
                'content' => $json['candidates'][0]['content']['parts'][0]['text'],
              ]);
            }
          }
        }
      }

      yield json_encode(['type' => 'done', 'content' => '']);
    } catch (\Exception $e) {
      Log::error('Gemini stream exception', ['message' => $e->getMessage()]);
      yield json_encode([
        'type' => 'error',
        'content' => 'An error occurred while streaming the response: ' . $e->getMessage(),
      ]);
    }
  }
}
