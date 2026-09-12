import { useState, useEffect, useRef } from "react";
import useApp from "../context/useApp";
import { sendSupportMessage } from "../api/client";
import { buildSupportHistory } from "../utils/chatHistory";
import AssistantMarkdown from "./AssistantMarkdown";
import { BrandMark } from "./BrandLogo";
import "../styles/SupportChat.css";

// SVG Icons
const IconClose = () => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
    <line x1="18" y1="6" x2="6" y2="18"></line>
    <line x1="6" y1="6" x2="18" y2="18"></line>
  </svg>
);

const IconSend = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
    <line x1="22" y1="2" x2="11" y2="13"></line>
    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
  </svg>
);

export default function SupportChat() {
  const { token } = useApp();
  // A new authentication scope remounts all history, drafts, loading and error state.
  return <ScopedSupportChat key={token || "guest"} />;
}

function ScopedSupportChat() {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState([
    {
      role: "assistant",
      content: "Welcome to **Elite AI**. I can help you choose from the current Elite PC catalog, compare available hardware, and answer product or compatibility questions.\n\nWhat are you shopping for today?",
      isIntro: true,
    }
  ]);
  const [input, setInput] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [hasError, setHasError] = useState(false);

  const historyEndRef = useRef(null);
  const activeRef = useRef(true);
  const requestRef = useRef(null);

  useEffect(() => {
    activeRef.current = true;
    return () => {
      activeRef.current = false;
      requestRef.current?.abort();
    };
  }, []);

  useEffect(() => {
    const openAssistant = () => setIsOpen(true);
    window.addEventListener("elitepc:open-assistant", openAssistant);
    return () => window.removeEventListener("elitepc:open-assistant", openAssistant);
  }, []);

  // Automatically scroll to the bottom when messages or loading states change
  useEffect(() => {
    if (historyEndRef.current) {
      historyEndRef.current.scrollIntoView({ behavior: "smooth" });
    }
  }, [messages, isLoading]);

  const handleSend = async (e) => {
    e.preventDefault();
    if (!input.trim() || isLoading || requestRef.current) return;
    const request = new AbortController();
    requestRef.current = request;

    const userQuery = input.trim();
    setInput("");
    setHasError(false);

    // Append user's message to local state
    const updatedMessages = [...messages, { role: "user", content: userQuery }];
    setMessages(updatedMessages);
    setIsLoading(true);

    try {
      // Keep five recent user/assistant turns. The current query is sent separately.
      const formattedHistory = buildSupportHistory(messages);

      // Call our Laravel API endpoint
      const response = await sendSupportMessage(userQuery, formattedHistory, request.signal);
      if (!activeRef.current) return;

      if (response && response.status === "success") {
        setMessages(prev => [...prev, { role: "assistant", content: response.reply }]);
      } else if (response && response.reply) {
        setHasError(true);
        setMessages(prev => [...prev, { role: "assistant", content: response.reply, state: "error" }]);
      } else {
        setHasError(true);
        setMessages(prev => [...prev, { 
          role: "assistant", 
          content: "I couldn't retrieve a response just now. Please try again in a moment.",
          state: "error"
        }]);
      }
    } catch {
      if (!activeRef.current) return;
      setHasError(true);
      setMessages(prev => [...prev, { 
        role: "assistant", 
        content: "I couldn't connect to Elite AI. Your message was not answered, so please try again.",
        state: "error"
      }]);
    } finally {
      if (activeRef.current) {
        requestRef.current = null;
        setIsLoading(false);
      }
    }
  };

  return (
    <>
      {/* Floating Chat Bubble Launcher */}
      {!isOpen && (
        <button 
          className="support-chat-launcher" 
          onClick={() => setIsOpen(true)}
          title="Open Elite AI"
          aria-label="Open Elite AI shopping assistant"
        >
          <BrandMark title="Elite PC AI" />
          <span className="support-chat-pulse" />
        </button>
      )}

      {/* Floating Support Chat Panel */}
      {isOpen && (
        <div className="support-chat-panel">
          {/* Header */}
          <div className="support-chat-header">
            <div className="support-chat-agent-info">
              <div className="support-chat-avatar" aria-hidden="true">
                AI
                <span className="support-chat-avatar-status" />
              </div>
              <div className="support-chat-agent-meta">
                <span className="support-chat-agent-name">Elite AI</span>
                <span className="support-chat-agent-role">Hardware shopping assistant</span>
              </div>
            </div>
            <button 
              className="support-chat-close-btn" 
              onClick={() => setIsOpen(false)}
              title="Close support chat"
              aria-label="Close support chat"
            >
              <IconClose />
            </button>
          </div>

          {/* Messages Viewport */}
          <div className="support-chat-history" aria-live="polite">
            {messages.map((msg, index) => (
              <div key={index} className={`support-chat-message-row ${msg.role}`}>
                <div className={`support-chat-bubble ${msg.state === "error" ? "is-error" : ""}`} role={msg.state === "error" ? "alert" : undefined}>
                  <span className="support-chat-message-label">{msg.role === "assistant" ? "Elite AI" : "You"}</span>
                  {msg.role === "assistant" ? <AssistantMarkdown content={msg.content} /> : <p className="support-chat-user-text">{msg.content}</p>}
                </div>
              </div>
            ))}

            {/* AI Typing Indicator */}
            {isLoading && (
              <div className="support-chat-message-row assistant">
                <div className="support-chat-bubble support-chat-thinking" role="status">
                  <span className="support-chat-message-label">Elite AI is thinking</span>
                  <div className="support-chat-typing-indicator">
                    <div className="support-chat-typing-dot" />
                    <div className="support-chat-typing-dot" />
                    <div className="support-chat-typing-dot" />
                  </div>
                </div>
              </div>
            )}
            <div ref={historyEndRef} />
          </div>

          {/* Input Form Bar */}
          <div className="support-chat-grounding">Recommendations use the current Elite PC catalog.</div>
          <form className={`support-chat-input-bar ${hasError ? "has-error" : ""}`} onSubmit={handleSend}>
            <textarea
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter" && !e.shiftKey) {
                  e.preventDefault();
                  e.currentTarget.form?.requestSubmit();
                }
              }}
              placeholder="Ask about products, specifications, or compatibility…"
              className="support-chat-input-field"
              disabled={isLoading}
              maxLength={2000}
              rows={1}
            />
            <button 
              type="submit" 
              className="support-chat-send-btn" 
              disabled={!input.trim() || isLoading}
              title="Send message"
              aria-label="Send message"
            >
              <IconSend />
            </button>
          </form>
        </div>
      )}
    </>
  );
}
