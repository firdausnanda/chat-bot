<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentProcessorService;
use App\Services\EmbeddingService;
use App\Services\PineconeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
  /**
   * Upload a PDF document.
   *
   * POST /api/documents/upload
   */
  public function upload(Request $request): JsonResponse
  {
    $request->validate([
      'file' => 'required|file|mimes:pdf|max:20480', // Max 20MB
    ]);

    $file = $request->file('file');
    $filename = $file->getClientOriginalName();
    $path = $file->store('documents', 'local');

    $document = Document::create([
      'filename' => $filename,
      'filepath' => $path,
      'file_size' => $file->getSize(),
      'status' => 'pending',
    ]);

    return response()->json([
      'message' => "File '{$filename}' uploaded successfully.",
      'document' => $document,
    ], 201);
  }

  /**
   * Process and ingest a document into Pinecone.
   *
   * POST /api/documents/{id}/ingest
   */
  public function ingest(
    int $id,
    DocumentProcessorService $processor,
    EmbeddingService $embeddingService,
    PineconeService $pineconeService
  ): JsonResponse {
    set_time_limit(300); // Allow 5 minutes for processing
    $document = Document::findOrFail($id);


    if ($document->status === 'processing') {
      return response()->json(['message' => 'Document is already being processed.'], 409);
    }

    $document->update(['status' => 'processing']);

    try {
      $filePath = storage_path('app/' . $document->filepath);

      // Step 1: Extract and chunk
      $result = $processor->process($filePath, $document->filename);

      if (empty($result['chunks'])) {
        $document->update(['status' => 'failed']);
        return response()->json(['error' => 'No text could be extracted from the PDF.'], 422);
      }

      $document->update(['pages_count' => $result['pages_count']]);

      // Step 2: Generate embeddings and upsert to Pinecone
      $vectors = [];
      $batchSize = 10;
      $chunks = $result['chunks'];

      foreach (array_chunk($chunks, $batchSize) as $batchIndex => $batch) {
        foreach ($batch as $chunk) {
          $embedding = $embeddingService->embed($chunk['text']);

          if (empty($embedding)) {
            Log::warning('Skipping chunk due to empty embedding', [
              'document_id' => $document->id,
              'chunk_index' => $chunk['chunk_index'],
            ]);
            continue;
          }

          $vectors[] = [
            'id' => "doc-{$document->id}-chunk-{$chunk['chunk_index']}",
            'values' => $embedding,
            'metadata' => [
              'source_type' => 'pdf',
              'document_id' => $document->id,
              'filename' => $chunk['filename'],
              'page' => $chunk['page'],
              'chunk_index' => $chunk['chunk_index'],
              'content_chunk' => $chunk['text'],
            ],
          ];
        }

        // Upsert in batches to avoid payload size limits
        if (count($vectors) >= $batchSize) {
          $upsertResult = $pineconeService->upsert($vectors);
          if (isset($upsertResult['error'])) {
            Log::error('Pinecone upsert error during ingestion', [
              'document_id' => $document->id,
              'error' => $upsertResult['error'],
            ]);
          }
          $vectors = [];
        }
      }

      // Upsert remaining vectors
      if (!empty($vectors)) {
        $upsertResult = $pineconeService->upsert($vectors);
        if (isset($upsertResult['error'])) {
          Log::error('Pinecone upsert error during final batch', [
            'document_id' => $document->id,
            'error' => $upsertResult['error'],
          ]);
        }
      }

      $document->update([
        'status' => 'completed',
        'chunks_count' => count($chunks),
      ]);

      return response()->json([
        'message' => "Document '{$document->filename}' processed successfully.",
        'pages_count' => $result['pages_count'],
        'chunks_count' => count($chunks),
      ]);
    } catch (\Exception $e) {
      Log::error('Document ingestion failed', [
        'document_id' => $document->id,
        'message' => $e->getMessage(),
      ]);

      $document->update(['status' => 'failed']);

      return response()->json([
        'error' => 'Document processing failed: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * List all uploaded documents.
   *
   * GET /api/documents
   */
  public function index(): JsonResponse
  {
    $documents = Document::orderBy('created_at', 'desc')->get();
    return response()->json($documents);
  }

  /**
   * Delete a document and its vectors from Pinecone.
   *
   * DELETE /api/documents/{id}
   */
  public function destroy(int $id, PineconeService $pineconeService): JsonResponse
  {
    $document = Document::findOrFail($id);

    // Delete vectors from Pinecone
    if ($document->chunks_count && $document->chunks_count > 0) {
      $vectorIds = [];
      for ($i = 0; $i < $document->chunks_count; $i++) {
        $vectorIds[] = "doc-{$document->id}-chunk-{$i}";
      }
      $pineconeService->deleteByIds($vectorIds);
    }

    // Delete file from storage
    Storage::disk('local')->delete($document->filepath);

    // Delete DB record
    $document->delete();

    return response()->json([
      'message' => "Document '{$document->filename}' deleted successfully.",
    ]);
  }
}
