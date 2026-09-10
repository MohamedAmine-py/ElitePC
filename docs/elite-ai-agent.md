# Elite AI read-only agent foundation

The existing `POST /api/support/chat` request (`message`, recent `history`) and response (`status`, `reply`, optional safe `error`) are unchanged. React is unchanged. The server still enforces the existing chat rate limit.

## Flow

`SupportChatRequest -> SupportChatController -> EliteAgentService -> GeminiTransport`

Gemini receives the system instruction, at most 10 history messages, the new user message, and one native function declaration. A `functionCall` is resolved through the explicit `ToolRegistry`. `SearchProductsTool` validates all arguments before querying `Produit` with its category. Laravel appends the full model content (including thought signatures), then a native `functionResponse` with the matching call ID. Gemini can search again or return a final answer. No arbitrary JSON parsing is used to simulate tool calls.

The existing four-model fallback order and `gemini.api_key`, `gemini.base_url`, `gemini.request_timeout` SDK configuration are retained. A fallback starts from the original bounded conversation, never another model's signed tool transcript. There are at most 5 total tool calls (including invalid and parallel calls) and 10 total generation attempts per request, including fallbacks. Generation output is bounded to 2048 tokens. Truncated/blocked/empty responses fail safely. HTTP calls retain the configured per-request timeout.

## search_products

All arguments are optional and combine with AND:

- `search`: literal keyword, at most 100 characters, across name, brand, description and existing hardware-spec fields.
- `category`: exact category name, case-insensitive, at most 100 characters.
- `min_price`, `max_price`: inclusive numeric USD bounds from 0 to 99999999.99; minimum cannot exceed maximum.
- `in_stock`: JSON boolean; true means stock > 0, false means stock = 0.
- `limit`: JSON integer 1–10, default 10. Larger values are rejected, not silently trusted.

Unknown keys, nulls, incorrect JSON types and invalid ranges are rejected. Queries use fixed server-owned columns and bound parameters; keyword wildcards are escaped. Results are ordered by price then ID and return at most 10 products plus `has_more`. Prices are exact two-decimal USD strings, with no currency conversion. Product output includes ID, name, price, stock, category, bounded plain-text description and existing specifications. No customer/order fields, timestamps, internal relationships, shell commands, raw SQL or mutation tools are exposed.

Specs are nullable descriptive strings, not a normalized compatibility database. Missing dimensions/socket/power information remains unknown. Images are omitted in this text-only phase. Broad searches are deliberately partial when more than 10 products match; `has_more` must not be presented as a full catalog.

## Rollback and diagnostics

`ELITE_AI_AGENT_ENABLED=false` selects `LegacyRagChatService`, which uses the unchanged `GeminiRagService` full-catalog prompt without tools. There is no silent RAG fallback after a failed agent run. For native local Laravel, clear config cache after changing the setting. For Docker, set it in the root `.env`, then recreate the backend (`docker compose up -d backend`). Deploy code changes with `docker compose up -d --build --no-deps backend`; do not reset database volumes or seed.

Logs include trace ID, model, tool call count, numeric price/stock filters and returned product IDs for acceptance verification. They do not contain prompts, raw provider errors, keys, SQL, stack traces or product descriptions. Invalid tool arguments are returned to Gemini as a generic correction request. Unknown tools, exhausted limits, provider outages or query failures return the existing friendly error shape, even with APP_DEBUG enabled.

## Verification

Run `php artisan test` with the repository's configured in-memory SQLite test database. Normal tests fake the Gemini transport and must never make paid API calls. Test fixtures do not enter the development database.

For an explicit live acceptance check, send `Show me products under $1000`, `Do you have any products in stock?`, and `Delete a product` through the existing chat UI. Correlate search logs with the actual catalog. The last prompt must explain that destructive actions are unavailable. No product/order/account/cart/favorite records should change.
