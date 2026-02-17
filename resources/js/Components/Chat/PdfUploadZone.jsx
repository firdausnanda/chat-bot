import React, { useState, useCallback, useEffect } from 'react';
import { useDropzone } from 'react-dropzone';

export default function PdfUploadZone() {
  const [documents, setDocuments] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [isOpen, setIsOpen] = useState(false);

  // Fetch existing documents on mount
  useEffect(() => {
    fetchDocuments();
  }, []);

  const fetchDocuments = async () => {
    try {
      const res = await fetch('/api/documents');
      if (res.ok) {
        const data = await res.json();
        setDocuments(data);
      }
    } catch (err) {
      console.error('Error fetching documents:', err);
    }
  };

  const onDrop = useCallback(async (acceptedFiles) => {
    for (const file of acceptedFiles) {
      if (file.type !== 'application/pdf') continue;

      setUploading(true);
      setUploadProgress(0);

      const formData = new FormData();
      formData.append('file', file);

      try {
        // Simulate progress
        const progressInterval = setInterval(() => {
          setUploadProgress(prev => Math.min(prev + 10, 90));
        }, 200);

        const res = await fetch('/api/documents/upload', {
          method: 'POST',
          body: formData,
        });

        clearInterval(progressInterval);
        setUploadProgress(100);

        if (res.ok) {
          const data = await res.json();
          setDocuments(prev => [data.document, ...prev]);

          // Auto-ingest after upload
          setTimeout(() => ingestDocument(data.document.id), 500);
        } else {
          const error = await res.json();
          console.error('Upload failed:', error);
        }
      } catch (err) {
        console.error('Upload error:', err);
      } finally {
        setTimeout(() => {
          setUploading(false);
          setUploadProgress(0);
        }, 1000);
      }
    }
  }, []);

  const ingestDocument = async (documentId) => {
    // Update local state to processing
    setDocuments(prev =>
      prev.map(doc =>
        doc.id === documentId ? { ...doc, status: 'processing' } : doc
      )
    );

    try {
      console.log(`Processing document ${documentId}...`);
      const res = await fetch(`/api/documents/${documentId}/process`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      });

      if (res.ok) {
        const data = await res.json();
        setDocuments(prev =>
          prev.map(doc =>
            doc.id === documentId
              ? { ...doc, status: 'completed', chunks_count: data.chunks_count, pages_count: data.pages_count }
              : doc
          )
        );
      } else {
        setDocuments(prev =>
          prev.map(doc =>
            doc.id === documentId ? { ...doc, status: 'failed' } : doc
          )
        );
      }
    } catch (err) {
      console.error('Ingest error:', err);
      setDocuments(prev =>
        prev.map(doc =>
          doc.id === documentId ? { ...doc, status: 'failed' } : doc
        )
      );
    }
  };

  const deleteDocument = async (documentId) => {
    try {
      const res = await fetch(`/api/documents/${documentId}`, {
        method: 'DELETE',
      });

      if (res.ok) {
        setDocuments(prev => prev.filter(doc => doc.id !== documentId));
      }
    } catch (err) {
      console.error('Delete error:', err);
    }
  };

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: { 'application/pdf': ['.pdf'] },
    maxSize: 20 * 1024 * 1024, // 20MB
    multiple: true,
  });

  const getStatusBadge = (status) => {
    const styles = {
      pending: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
      processing: 'bg-blue-500/20 text-blue-400 border-blue-500/30 animate-pulse',
      completed: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
      failed: 'bg-red-500/20 text-red-400 border-red-500/30',
    };
    const labels = {
      pending: 'Pending',
      processing: '⏳ Processing...',
      completed: '✅ Ready',
      failed: '❌ Failed',
    };
    return (
      <span className={`text-xs px-2 py-0.5 rounded-full border ${styles[status] || styles.pending}`}>
        {labels[status] || status}
      </span>
    );
  };

  const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  };

  return (
    <div className="relative">
      {/* Toggle Button */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-3 py-1.5 text-sm bg-gray-700/60 hover:bg-gray-700 text-gray-300 hover:text-white rounded-lg transition-all duration-200 border border-gray-600/50"
      >
        <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
        <span>PDF Documents</span>
        {documents.length > 0 && (
          <span className="bg-indigo-500/30 text-indigo-300 text-xs px-1.5 py-0.5 rounded-full">
            {documents.length}
          </span>
        )}
        <svg xmlns="http://www.w3.org/2000/svg" className={`w-3 h-3 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {/* Dropdown Panel */}
      {isOpen && (
        <div className="absolute top-full right-0 mt-2 w-96 bg-gray-800/95 backdrop-blur-xl border border-gray-700/60 rounded-2xl shadow-2xl shadow-black/40 z-50 overflow-hidden">
          {/* Dropzone */}
          <div className="p-4">
            <div
              {...getRootProps()}
              className={`border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all duration-300 ${isDragActive
                ? 'border-indigo-400 bg-indigo-500/10 scale-[1.02]'
                : 'border-gray-600 hover:border-indigo-500/50 hover:bg-gray-700/30'
                }`}
            >
              <input {...getInputProps()} />
              <div className="flex flex-col items-center gap-2">
                <div className={`w-12 h-12 rounded-full flex items-center justify-center transition-all duration-300 ${isDragActive ? 'bg-indigo-500/20 scale-110' : 'bg-gray-700'
                  }`}>
                  <svg xmlns="http://www.w3.org/2000/svg" className={`w-6 h-6 transition-colors ${isDragActive ? 'text-indigo-400' : 'text-gray-400'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                  </svg>
                </div>
                <p className="text-sm text-gray-300">
                  {isDragActive ? (
                    <span className="text-indigo-400 font-medium">Drop PDF here...</span>
                  ) : (
                    <>
                      <span className="text-indigo-400 font-medium">Click to upload</span> or drag & drop
                    </>
                  )}
                </p>
                <p className="text-xs text-gray-500">PDF files up to 20MB</p>
              </div>
            </div>

            {/* Upload Progress */}
            {uploading && (
              <div className="mt-3">
                <div className="h-1.5 bg-gray-700 rounded-full overflow-hidden">
                  <div
                    className="h-full rounded-full bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500 bg-[length:200%_100%] animate-[shimmer_1.5s_ease-in-out_infinite] transition-all duration-300"
                    style={{ width: `${uploadProgress}%` }}
                  />
                </div>
                <p className="text-xs text-gray-400 mt-1 text-center">{uploadProgress}% uploading...</p>
              </div>
            )}
          </div>

          {/* Document List */}
          {documents.length > 0 && (
            <div className="border-t border-gray-700/60 max-h-64 overflow-y-auto">
              <div className="p-2">
                {documents.map(doc => (
                  <div
                    key={doc.id}
                    className="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-700/30 transition-colors group"
                  >
                    {/* PDF Icon */}
                    <div className="w-8 h-8 rounded-lg bg-red-500/15 flex items-center justify-center flex-shrink-0">
                      <span className="text-sm">📄</span>
                    </div>

                    {/* File Info */}
                    <div className="flex-1 min-w-0">
                      <p className="text-sm text-gray-200 truncate font-medium">{doc.filename}</p>
                      <div className="flex items-center gap-2 mt-0.5">
                        <span className="text-xs text-gray-500">{formatFileSize(doc.file_size)}</span>
                        {doc.pages_count && <span className="text-xs text-gray-500">{doc.pages_count} pages</span>}
                        {doc.chunks_count && <span className="text-xs text-gray-500">{doc.chunks_count} chunks</span>}
                      </div>
                    </div>

                    {/* Status + Actions */}
                    <div className="flex items-center gap-2 flex-shrink-0">
                      {getStatusBadge(doc.status)}
                      {doc.status === 'failed' && (
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            ingestDocument(doc.id);
                          }}
                          className="p-1 rounded hover:bg-amber-500/20 text-amber-400 hover:text-amber-300 transition-all"
                          title="Retry processing"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                          </svg>
                        </button>
                      )}
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          deleteDocument(doc.id);
                        }}
                        className="opacity-0 group-hover:opacity-100 p-1 rounded hover:bg-red-500/20 text-gray-500 hover:text-red-400 transition-all"
                        title="Delete document"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Empty State */}
          {documents.length === 0 && !uploading && (
            <div className="border-t border-gray-700/60 px-4 py-6 text-center">
              <p className="text-sm text-gray-500">No PDF documents uploaded yet</p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
