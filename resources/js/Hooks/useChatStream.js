import { useState, useCallback } from 'react';

export const useChatStream = () => {
  const [messages, setMessages] = useState([
    {
      id: 'welcome',
      role: 'assistant',
      content: 'Halo! Saya Pustakawan AI Anda. Saya bisa membantu Anda menjelajahi perpustakaan dan menjawab pertanyaan dari dokumen PDF yang diunggah. Bagaimana saya dapat membantu?',
      sources: []
    }
  ]);
  const [isLoading, setIsLoading] = useState(false);

  const sendMessage = useCallback(async (content, searchMode = 'all') => {
    // Add user message
    const userMessage = {
      id: Date.now().toString(),
      role: 'user',
      content
    };

    setMessages(prev => [...prev, userMessage]);
    setIsLoading(true);

    const assistantMessageId = (Date.now() + 1).toString();
    // REMOVED: Initial empty assistant message addition to prevent double bubble

    try {
      const response = await fetch('/api/chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream',
        },
        body: JSON.stringify({ message: content, search_mode: searchMode }),
      });

      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let assistantMessageContent = "";
      let buffer = "";
      let messageCreated = false; // Track if we've created the message bubble yet
      let bufferedSources = []; // Buffer sources until we create the message

      while (true) {
        const { value, done } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');

        // Keep the last line in buffer as it might be incomplete
        buffer = lines.pop() || "";

        for (const line of lines) {
          if (line.trim().startsWith('data: ')) {
            const data = line.trim().slice(6);
            if (data === '[DONE]') continue;

            try {
              const parsed = JSON.parse(data);

              if (parsed.type === 'sources') {
                // Buffer sources, don't create message yet
                bufferedSources = parsed.content;

                // If message already exists (rare case where sources come after text?), update it
                if (messageCreated) {
                  setMessages(prev => prev.map(msg =>
                    msg.id === assistantMessageId
                      ? { ...msg, sources: parsed.content }
                      : msg
                  ));
                }
              } else if (parsed.type === 'text') {
                assistantMessageContent += parsed.content;

                if (!messageCreated) {
                  setIsLoading(false); // Stop thinking bubble
                  setMessages(prev => [...prev, {
                    id: assistantMessageId,
                    role: 'assistant',
                    content: assistantMessageContent,
                    sources: bufferedSources // Use buffered sources
                  }]);
                  messageCreated = true;
                } else {
                  setMessages(prev => prev.map(msg =>
                    msg.id === assistantMessageId
                      ? { ...msg, content: assistantMessageContent }
                      : msg
                  ));
                }
              } else if (parsed.type === 'done') {
                setIsLoading(false);
                if (!messageCreated) {
                  setMessages(prev => [...prev, {
                    id: assistantMessageId,
                    role: 'assistant',
                    content: assistantMessageContent || "No response generated.",
                    sources: bufferedSources
                  }]);
                  messageCreated = true;
                }
              } else if (parsed.type === 'error') {
                console.error("Stream error:", parsed.content);
                const errorText = "\n[Error: " + parsed.content + "]";

                if (!messageCreated) {
                  setIsLoading(false);
                  setMessages(prev => [...prev, {
                    id: assistantMessageId,
                    role: 'assistant',
                    content: errorText,
                    sources: bufferedSources
                  }]);
                  messageCreated = true;
                } else {
                  setMessages(prev => prev.map(msg =>
                    msg.id === assistantMessageId
                      ? { ...msg, content: assistantMessageContent + errorText }
                      : msg
                  ));
                }
              }
            } catch (e) {
              console.error('Error parsing JSON from stream', e);
            }
          }
        }
      }
    } catch (error) {
      console.error('Error sending message:', error);
      setIsLoading(false);

      // If we failed and haven't created a message yet, create an error message
      setMessages(prev => {
        const messageExists = prev.some(msg => msg.id === assistantMessageId);

        if (!messageExists) {
          return [...prev, {
            id: assistantMessageId,
            role: 'assistant',
            content: "Sorry, I encountered an error while processing your request.",
            sources: []
          }];

        } else {
          return prev.map(msg =>
            msg.id === assistantMessageId
              ? { ...msg, content: msg.content + "\n[Error: Request failed]" }
              : msg
          );
        }
      });
    } finally {
      setIsLoading(false);
    }
  }, []);

  return {
    messages,
    sendMessage,
    isLoading
  };
};
