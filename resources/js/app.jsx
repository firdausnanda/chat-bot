import './bootstrap';
import '../css/app.css';

import React from 'react';
import ReactDOM from 'react-dom/client';
import ChatInterface from './Components/Chat/ChatInterface';

if (document.getElementById('chat-root')) {
  const root = ReactDOM.createRoot(document.getElementById('chat-root'));
  root.render(
    <React.StrictMode>
      <ChatInterface />
    </React.StrictMode>
  );
}
