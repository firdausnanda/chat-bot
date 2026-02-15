# 🚀 AI Agent Execution Plan: RAG-based PDF Chatbot

## 1. Project Overview

* **Goal:** Enhance an existing Gemini-powered library chatbot to support PDF documents as a knowledge source.
* **Architecture:** RAG (Retrieval-Augmented Generation).
* **Tech Stack:** * **Backend:** Laravel (PHP 8.2+)
* **Frontend:** React (Inertia.js or Vite)
* **AI:** Google Gemini Pro (LLM) & Gemini Embedding-001
* **Vector DB:** Pinecone
* **Parser:** Smalot PDF Parser



## 2. Environment Setup (Action Required)

Add these variables to `.env` and ensure the AI Agent has access to them:

```env
GEMINI_API_KEY=your_key
PINECONE_API_KEY=your_key
PINECONE_INDEX_URL=https://your-index-url.svc.pinecone.io
PINECONE_DIMENSION=768 # Standard for Gemini Embedding-001

```

## 3. Backend Implementation (Laravel)

### Step 3.1: Dependencies

Install necessary PHP packages:

```bash
composer require smalot/pdfparser guzzlehttp/guzzle

```

### Step 3.2: Storage Link

Ensure public storage is linked for temporary PDF handling:

```bash
php artisan storage:link

```

### Step 3.3: Task List for AI Agent

1. **Create `DocumentProcessorService`:** * Handle PDF text extraction using `Smalot\PdfParser\Parser`.
* Implement **Chunking Logic**: Split text into segments of 1000 characters with 100-character overlap.


2. **Create `VectorService`:**
* `generateEmbedding(string $text)`: Call Gemini API `models/embedding-001`.
* `upsertToPinecone(array $vector, array $metadata)`: Push to Pinecone Index.
* `searchSimilar(array $vector)`: Query Pinecone for top 3-5 matches.


3. **Update `ChatController`:**
* Modify chat logic to first perform a vector search.
* Inject retrieved context into the Gemini Prompt.



## 4. Frontend Implementation (React)

### Step 4.1: Component Task List

1. **`PdfUploadZone.jsx`:**
* Create a drag-and-drop area using `react-dropzone`.
* Handle `multipart/form-data` POST request to Laravel.
* Show a real-time progress bar using Tailwind CSS.


2. **`ChatInterface.jsx` (Enhancement):**
* Add a toggle to "Search in PDF" or "Search in Database".
* Display "Source" chips (e.g., "Found in: Manual_User.pdf, page 5").



## 5. Implementation Logic (The "Prompt" for the Agent)

### 🧩 Logic A: PDF Ingestion

```php
// Pseudo-code for Agent
public function processPdf(File $file) {
    $text = Parser->parse($file);
    $chunks = split($text);
    foreach($chunks as $chunk) {
        $vector = Gemini->embed($chunk);
        Pinecone->upsert($vector, ['text' => $chunk, 'filename' => $file->name()]);
    }
}

```

### 🧩 Logic B: Retrieval Augmented Chat

```php
// Pseudo-code for Agent
public function getResponse(String $query) {
    $queryVector = Gemini->embed($query);
    $context = Pinecone->query($queryVector);
    
    $prompt = "Context: " . $context . "\n\nUser Question: " . $query;
    return Gemini->generate($prompt);
}

```

---

## 6. Testing Checklist for AI Agent

* [ ] Verify PDF text extraction is clean (no weird symbols).
* [ ] Verify Pinecone received vectors with `768` dimensions.
* [ ] Verify Gemini responds based *only* on the provided PDF context when asked.
* [ ] Ensure Large PDFs don't time out (use Laravel Queues if necessary).

---

**Next Step:**
"Agent, mulailah dengan membuat `DocumentProcessorService.php` di Laravel dan implementasikan fungsi ekstraksi teks PDF serta chunking teks tersebut."