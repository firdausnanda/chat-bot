<?php

namespace App\Http\Controllers;

use App\Services\ResearchAssistant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
  protected ResearchAssistant $assistant;

  public function __construct(ResearchAssistant $assistant)
  {
    $this->assistant = $assistant;
  }

  /**
   * Stream chat response using SSE (Server-Sent Events).
   *
   * POST /api/chat
   * Body: { "message": "...", "search_mode": "all|pdf|database" }
   */
  public function stream(Request $request): StreamedResponse
  {
    $request->validate([
      'message' => 'required|string|max:2000',
      'search_mode' => 'nullable|string|in:all,pdf,database',
    ]);

    $message = $request->input('message');
    $searchMode = $request->input('search_mode', 'all');

    return response()->stream(function () use ($message, $searchMode) {
      // Set headers for SSE
      header('X-Accel-Buffering: no');

      try {
        foreach ($this->assistant->ask($message, $searchMode) as $chunk) {
          echo "data: {$chunk}\n\n";
          if (ob_get_level() > 0) {
            ob_flush();
          }
          flush();
        }

        echo "data: " . json_encode(['type' => 'done', 'content' => '']) . "\n\n";
        if (ob_get_level() > 0) {
          ob_flush();
        }
        flush();
      } catch (\Throwable $e) {
        $error = json_encode(['type' => 'error', 'content' => $e->getMessage()]);
        echo "data: {$error}\n\n";
        if (ob_get_level() > 0) {
          ob_flush();
        }
        flush();
      }
    }, 200, [
      'Content-Type' => 'text/event-stream',
      'Cache-Control' => 'no-cache',
      'Connection' => 'keep-alive',
      'X-Accel-Buffering' => 'no',
    ]);
  }

  /**
   * Non-streaming chat response.
   *
   * POST /api/chat/ask
   * Body: { "message": "...", "search_mode": "all|pdf|database" }
   */
  public function ask(Request $request)
  {
    $request->validate([
      'message' => 'required|string|max:2000',
      'search_mode' => 'nullable|string|in:all,pdf,database',
    ]);

    $message = $request->input('message');
    $searchMode = $request->input('search_mode', 'all');
    $result = $this->assistant->askSync($message, $searchMode);

    return response()->json($result);
  }
}
