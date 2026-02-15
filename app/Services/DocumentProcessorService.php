<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class DocumentProcessorService
{
  protected Parser $parser;
  protected int $chunkSize;
  protected int $chunkOverlap;

  public function __construct()
  {
    $this->parser = new Parser();
    $this->chunkSize = 1000;
    $this->chunkOverlap = 100;
  }

  /**
   * Full processing pipeline: extract + chunk page by page to save memory.
   *
   * @param string $filePath
   * @param string $filename
   * @return array ['chunks' => array, 'pages_count' => int]
   */
  public function process(string $filePath, string $filename): array
  {
    // Increase memory limit for this process
    ini_set('memory_limit', '1024M');

    try {
      $pdf = $this->parser->parseFile($filePath);
      $pages = $pdf->getPages();
      $pagesCount = count($pages);
      $chunks = [];
      $chunkIndex = 0;

      foreach ($pages as $index => $page) {
        $text = $page->getText();
        $text = $this->cleanText($text);

        if (empty($text)) {
          continue;
        }

        // Chunk the current page text
        $pageChunks = $this->chunkString($text, $index + 1, $filename, $chunkIndex);

        foreach ($pageChunks as $chunk) {
          $chunks[] = $chunk;
          $chunkIndex++;
        }

        // Explicitly clear variables to help GC
        unset($text);
        unset($pageChunks);
      }

      // Explicitly clear PDF object
      unset($pages);
      unset($pdf);

      return [
        'chunks' => $chunks,
        'pages_count' => $pagesCount,
      ];
    } catch (\Exception $e) {
      Log::error('PDF processing failed', [
        'file' => $filePath,
        'message' => $e->getMessage(),
      ]);

      return [
        'chunks' => [],
        'pages_count' => 0,
      ];
    }
  }

  /**
   * Split a single string (page content) into chunks.
   */
  protected function chunkString(string $text, int $pageNumber, string $filename, int $startIndex): array
  {
    $chunks = [];
    $textLength = mb_strlen($text);
    $localIndex = $startIndex;

    if ($textLength <= $this->chunkSize) {
      return [
        [
          'text' => $text,
          'page' => $pageNumber,
          'chunk_index' => $localIndex,
          'filename' => $filename,
        ]
      ];
    }

    $offset = 0;
    while ($offset < $textLength) {
      $chunkContent = mb_substr($text, $offset, $this->chunkSize);

      // Try to break at sentence boundary
      if ($offset + $this->chunkSize < $textLength) {
        $lastPeriod = mb_strrpos($chunkContent, '.');
        $lastNewline = mb_strrpos($chunkContent, "\n");
        $breakPoint = max($lastPeriod, $lastNewline);

        if ($breakPoint !== false && $breakPoint > $this->chunkSize * 0.5) {
          $chunkContent = mb_substr($chunkContent, 0, $breakPoint + 1);
        }
      }

      $chunks[] = [
        'text' => trim($chunkContent),
        'page' => $pageNumber,
        'chunk_index' => $localIndex++,
        'filename' => $filename,
      ];

      $step = mb_strlen($chunkContent) - $this->chunkOverlap;
      if ($step <= 0) {
        // Prevent infinite loop if overlap >= chunk size (should unlikely happen with logic above but safe to handle)
        $step = 1;
      }
      $offset += $step;
    }

    return $chunks;
  }

  /**
   * Clean up extracted text from common PDF artifacts.
   */
  protected function cleanText(string $text): string
  {
    // Replace multiple whitespace with single space
    $text = preg_replace('/[ \t]+/', ' ', $text);
    // Replace multiple newlines with double newline
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    // Remove null bytes and other control characters (except newline, tab)
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    return trim($text);
  }
}
