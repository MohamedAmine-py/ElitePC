import Markdown from "react-markdown";
import remarkGfm from "remark-gfm";

const components = {
  a({ href, title, children }) {
    // react-markdown's default URL transform removes unsafe protocols first.
    if (!href) return <span>{children}</span>;
    const external = /^(https?:)?\/\//i.test(href);
    return (
      <a href={href} title={title} target={external ? "_blank" : undefined} rel={external ? "noopener noreferrer" : undefined}>
        {children}
      </a>
    );
  },
  table({ children }) {
    return (
      <div className="support-chat-table-scroll" role="region" aria-label="Scrollable comparison table" tabIndex={0}>
        <table>{children}</table>
      </div>
    );
  },
};

export default function AssistantMarkdown({ content }) {
  return (
    <div className="support-chat-markdown">
      <Markdown remarkPlugins={[remarkGfm]} skipHtml disallowedElements={["img"]} components={components}>
        {content || ""}
      </Markdown>
    </div>
  );
}
