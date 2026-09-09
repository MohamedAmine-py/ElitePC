import { useState, useEffect, useRef } from "react";
import { sendSupportMessage } from "../api/client";
import { buildSupportHistory } from "../utils/chatHistory";
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
    if (!input.trim() || isLoading) return;

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
      const response = await sendSupportMessage(userQuery, formattedHistory);

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
    } catch (error) {
      console.error("Support Chat API Error:", error);
      setHasError(true);
      setMessages(prev => [...prev, { 
        role: "assistant", 
        content: "I couldn't connect to Elite AI. Your message was not answered, so please try again.",
        state: "error"
      }]);
    } finally {
      setIsLoading(false);
    }
  };

  // Safe and clean custom React markdown rendering helper
  const renderFormattedContent = (text) => {
    if (!text) return "";
    
    // Split text by double newlines for paragraph breaks
    const paragraphs = text.split(/\n\n+/);
    
    return paragraphs.map((para, idx) => {
      const trimmedPara = para.trim();
      
      // Render bullet list items
      if (trimmedPara.startsWith("- ") || trimmedPara.startsWith("* ")) {
        const items = trimmedPara.split(/\n[-*]\s+/);
        items[0] = items[0].replace(/^[-*]\s+/, "");
        return (
          <ul key={idx} className="support-chat-list">
            {items.map((item, itemIdx) => (
              <li key={itemIdx}>{parseInlineStyles(item)}</li>
            ))}
          </ul>
        );
      }
      
      // Render numbered list items
      if (/^\d+\.\s+/.test(trimmedPara)) {
        const items = trimmedPara.split(/\n\d+\.\s+/);
        items[0] = items[0].replace(/^\d+\.\s+/, "");
        return (
          <ol key={idx} className="support-chat-list">
            {items.map((item, itemIdx) => (
              <li key={itemIdx}>{parseInlineStyles(item)}</li>
            ))}
          </ol>
        );
      }
      
      // Render default paragraph
      return (
        <p key={idx} style={{ marginBottom: idx === paragraphs.length - 1 ? 0 : "8px" }}>
          {parseInlineStyles(trimmedPara)}
        </p>
      );
    });
  };

  // Helper to parse **bold** and handle internal line breaks safely in React elements
  const parseInlineStyles = (text) => {
    const parts = text.split(/(\*\*.*?\*\*)/g);
    return parts.map((part, idx) => {
      if (part.startsWith("**") && part.endsWith("**")) {
        return <strong key={idx}>{part.slice(2, -2)}</strong>;
      }
      
      // Split single newlines to output <br /> tags
      const sublines = part.split("\n");
      return sublines.map((line, lineIdx) => (
        <span key={lineIdx}>
          {line}
          {lineIdx < sublines.length - 1 && <br />}
        </span>
      ));
    });
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
                  {renderFormattedContent(msg.content)}
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
