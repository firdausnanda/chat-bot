import React, { useState, useRef, useEffect } from 'react';
import { useChatStream } from '../../Hooks/useChatStream';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import PdfUploadZone from './PdfUploadZone';

export default function ChatInterface() {
  const { messages, sendMessage, isLoading } = useChatStream();
  const [input, setInput] = useState('');
  const [searchMode, setSearchMode] = useState('all');
  const messagesEndRef = useRef(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!input.trim() || isLoading) return;
    sendMessage(input, searchMode);
    setInput('');
  };

  const searchModes = [
    { value: 'all', label: 'All Sources', icon: '🔍' },
    { value: 'pdf', label: 'PDF Only', icon: '📄' },
    { value: 'database', label: 'Database', icon: '📚' },
  ];

  const renderSourceChips = (sources) => {
    if (!sources || sources.length === 0) return null;

    return (
      <div className="flex flex-wrap gap-1.5 mt-3 pt-3 border-t border-gray-700/50">
        <span className="text-xs text-gray-500 mr-1 self-center">Sources:</span>
        {sources.map((source, idx) => (
          <span
            key={idx}
            className={`inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full border transition-colors ${source.type === 'pdf'
              ? 'bg-orange-500/10 text-orange-300 border-orange-500/25 hover:bg-orange-500/20'
              : 'bg-indigo-500/10 text-indigo-300 border-indigo-500/25 hover:bg-indigo-500/20'
              }`}
          >
            {source.type === 'pdf' ? (
              <>
                <span>📄</span>
                <span className="truncate max-w-[150px]">{source.filename}</span>
                {source.page && <span className="text-gray-400">p.{source.page}</span>}
              </>
            ) : (
              <>
                <span>📚</span>
                <span className="truncate max-w-[150px]">{source.title}</span>
              </>
            )}
            {source.score && (
              <span className="text-gray-500 text-[10px]">
                {(source.score * 100).toFixed(0)}%
              </span>
            )}
          </span>
        ))}
      </div>
    );
  };

  return (
    <div className="flex flex-col h-screen bg-gray-900 text-gray-100 font-sans">
      {/* Header */}
      <header className="relative z-20 bg-gray-800/80 backdrop-blur-sm border-b border-gray-700/60 p-4 shadow-lg">
        <div className="max-w-4xl mx-auto flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg shadow-indigo-500/20">
              <span className="text-xl">📚</span>
            </div>
            <div>
              <h1 className="text-xl font-bold text-white">Library AI Assistant</h1>
              <p className="text-xs text-gray-400">Powered by RAG & Pinecone</p>
            </div>
          </div>

          {/* Right side: PDF Upload + Search Mode */}
          <div className="flex items-center gap-3">
            <PdfUploadZone />

            {/* Search Mode Toggle */}
            <div className="flex items-center bg-gray-700/40 rounded-lg p-0.5 border border-gray-600/30">
              {searchModes.map(mode => (
                <button
                  key={mode.value}
                  onClick={() => setSearchMode(mode.value)}
                  className={`flex items-center gap-1 px-2.5 py-1.5 text-xs rounded-md transition-all duration-200 ${searchMode === mode.value
                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20'
                    : 'text-gray-400 hover:text-gray-200 hover:bg-gray-700/50'
                    }`}
                >
                  <span>{mode.icon}</span>
                  <span className="hidden sm:inline">{mode.label}</span>
                </button>
              ))}
            </div>
          </div>
        </div>
      </header>

      {/* Chat Area */}
      <main className="flex-1 overflow-y-auto p-4 space-y-6">
        <div className="max-w-3xl mx-auto space-y-6">
          {messages.map((msg) => (
            <div key={msg.id} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
              <div className={`flex max-w-[80%] ${msg.role === 'user' ? 'flex-row-reverse' : 'flex-row'} items-start gap-3`}>
                {/* Avatar */}
                <div className={`w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${msg.role === 'user'
                  ? 'bg-gradient-to-br from-indigo-500 to-purple-500'
                  : 'bg-gradient-to-br from-emerald-500 to-teal-600'
                  }`}>
                  {msg.role === 'user' ? '👤' : '🤖'}
                </div>

                {/* Message Bubble */}
                <div className={`p-4 rounded-2xl shadow-lg ${msg.role === 'user'
                  ? 'bg-gradient-to-br from-indigo-600 to-indigo-700 text-white rounded-tr-none'
                  : 'bg-gray-800/80 backdrop-blur-sm text-gray-100 rounded-tl-none border border-gray-700/60'
                  }`}>
                  <div className={`prose prose-invert max-w-none text-sm leading-relaxed ${msg.role === 'user' ? 'text-white prose-p:text-white' : 'text-gray-100 prose-p:text-gray-100 prose-a:text-blue-400 prose-strong:text-white'
                    }`}>
                    <ReactMarkdown
                      remarkPlugins={[remarkGfm]}
                      components={{
                        a: ({ node, ...props }) => <a {...props} target="_blank" rel="noopener noreferrer" className="text-blue-400 hover:underline" />,
                        p: ({ node, ...props }) => <p {...props} className="mb-2 last:mb-0" />,
                        ul: ({ node, ...props }) => <ul {...props} className="list-disc list-outside ml-4 mb-2" />,
                        ol: ({ node, ...props }) => <ol {...props} className="list-decimal list-outside ml-4 mb-2" />,
                        li: ({ node, ...props }) => <li {...props} className="mb-1" />,
                        strong: ({ node, ...props }) => <strong {...props} className="font-bold text-white" />
                      }}
                    >
                      {msg.content}
                    </ReactMarkdown>
                  </div>

                  {/* Source Chips */}
                  {msg.role === 'assistant' && msg.sources && msg.sources.length > 0 && renderSourceChips(msg.sources)}
                </div>
              </div>
            </div>
          ))}
          {isLoading && (
            <div className="flex justify-start">
              <div className="flex max-w-[80%] flex-row items-start gap-3">
                <div className="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center flex-shrink-0">🤖</div>
                <div className="bg-gray-800/80 backdrop-blur-sm p-4 rounded-2xl rounded-tl-none border border-gray-700/60">
                  <div className="flex items-center gap-2">
                    <div className="flex gap-1">
                      <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0ms' }}></span>
                      <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '150ms' }}></span>
                      <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '300ms' }}></span>
                    </div>
                    <span className="text-sm text-gray-400">Mencari...</span>
                  </div>
                </div>
              </div>
            </div>
          )}
          <div ref={messagesEndRef} />
        </div>
      </main>

      {/* Input Area */}
      <footer className="bg-gray-800/80 backdrop-blur-sm border-t border-gray-700/60 p-4">
        <div className="max-w-3xl mx-auto">
          <form onSubmit={handleSubmit} className="relative">
            <input
              type="text"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder={`Ask about ${searchMode === 'pdf' ? 'PDF documents' : searchMode === 'database' ? 'library books' : 'books and PDF documents'}...`}
              className="w-full bg-gray-900/80 text-white border border-gray-600/50 rounded-xl pl-4 pr-12 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50 placeholder-gray-500 shadow-inner transition-all"
              disabled={isLoading}
            />
            <button
              type="submit"
              disabled={isLoading || !input.trim()}
              className="absolute right-2 top-2 p-1.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white rounded-lg transition-all shadow-lg shadow-indigo-500/20 disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
                <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z" />
              </svg>
            </button>
          </form>
          <p className="text-center text-xs text-gray-500 mt-2">
            AI answers are generated based on {searchMode === 'pdf' ? 'uploaded PDF documents' : searchMode === 'database' ? 'library books' : 'library content & PDF documents'}. Check sources for accuracy.
          </p>
        </div>
      </footer>
    </div>
  );
}
