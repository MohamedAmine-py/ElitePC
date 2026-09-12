import assert from "node:assert/strict";
import { after, before, test } from "node:test";
import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { createServer } from "vite";

let server;
let AssistantMarkdown;

before(async () => {
  server = await createServer({ server: { middlewareMode: true }, appType: "custom", optimizeDeps: { noDiscovery: true, include: [] } });
  ({ default: AssistantMarkdown } = await server.ssrLoadModule("/src/components/AssistantMarkdown.jsx"));
});
after(async () => { await server?.close(); });

const render = (content) => renderToStaticMarkup(createElement(AssistantMarkdown, { content }));

test("renders headings, paragraphs, emphasis and nested/ordered lists", () => {
  const html = render("### 1440p Gaming\n\n**32GB of RAM** and *CPU balance*.\n\n- GPU performance\n- RAM capacity\n  - Headroom\n\n1. Choose a budget\n2. Check specifications");
  assert.match(html, /<h3>1440p Gaming<\/h3>/);
  assert.match(html, /<strong>32GB of RAM<\/strong>/);
  assert.match(html, /<em>CPU balance<\/em>/);
  assert.match(html, /<ul>/);
  assert.match(html, /<ol>/);
  assert.equal((html.match(/<li>/g) || []).length, 5);
  assert.doesNotMatch(html, /###|\*\*32GB/);
});

test("renders comparison columns inside a keyboard-accessible scroll region", () => {
  const html = render("| Product | Price | GPU |\n| --- | ---: | --- |\n| Nova Strike | $1,499.99 | RTX 4070 12GB |\n| Slayer-X | $4,299.99 | RTX 4090 24GB |");
  assert.match(html, /class="support-chat-table-scroll" role="region"/);
  assert.match(html, /tabindex="0"/);
  assert.match(html, /<table><thead>/);
  assert.equal((html.match(/<th[ >]/g) || []).length, 3);
  assert.equal((html.match(/<td[ >]/g) || []).length, 6);
  assert.match(html, /text-align:right/);
});

test("keeps inline and fenced code as readable escaped text", () => {
  const html = render("Check `DDR5`.\n\n```html\n<script>alert(1)</script>\n```");
  assert.match(html, /<code>DDR5<\/code>/);
  assert.match(html, /<pre><code class="language-html">/);
  assert.match(html, /&lt;script&gt;/);
  assert.doesNotMatch(html, /<script>/);
});

test("protects external links and keeps relative storefront links local", () => {
  const html = render("[Guide](https://example.com/guide) and [Products](/products)");
  assert.match(html, /href="https:\/\/example.com\/guide" target="_blank" rel="noopener noreferrer"/);
  assert.match(html, /href="\/products">Products<\/a>/);
});

test("blocks unsafe URLs, raw HTML and remote image loading", () => {
  const html = render('[bad](javascript:alert%281%29)\n\n[data](data:text/html,bad)\n\n<img src="x" onerror="alert(1)">\n\n<script>alert(1)</script>\n\n<iframe src="https://example.com"></iframe>\n\n![tracking](https://example.com/pixel.png)');
  assert.doesNotMatch(html, /href="(?:javascript|data):|<script|<iframe|<img|onerror=/);
  assert.match(html, /<span>bad<\/span>/);
  assert.match(html, /<span>data<\/span>/);
});
