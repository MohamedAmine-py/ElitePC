# Elite AI read-only agent foundation

The existing `POST /api/support/chat` request (`message`, recent `history`) and response (`status`, `reply`, optional safe `error`) are unchanged. React is unchanged. The server still enforces the existing chat rate limit.

## Flow

`SupportChatRequest -> SupportChatController -> EliteAgentService -> GeminiTransport`

Gemini receives the system instruction, at most 10 history messages, the new user message, and six native function declarations. A `functionCall` is resolved through the explicit `ToolRegistry`. Each dedicated tool validates arguments before querying the public product/category data it needs. Laravel appends the full model content (including thought signatures), then a native `functionResponse` with the matching call ID. Gemini can search again or return a final answer. No arbitrary JSON parsing is used to simulate tool calls.

The existing four-model fallback order and `gemini.api_key`, `gemini.base_url`, `gemini.request_timeout` SDK configuration are retained. A fallback starts from the original bounded conversation, never another model's signed tool transcript. There are at most 5 total tool calls (including invalid and parallel calls) and 10 total generation attempts per request, including fallbacks. Generation output is bounded to 2048 tokens. Truncated/blocked/empty responses fail safely. HTTP calls retain the configured per-request timeout.

## search_products

All arguments are optional and combine with AND:

- `search`: literal keyword, at most 100 characters, across name, brand, description and existing hardware-spec fields.
- `category`: exact category name, case-insensitive, at most 100 characters.
- `min_price`, `max_price`: inclusive numeric USD bounds from 0 to 99999999.99; minimum cannot exceed maximum.
- `in_stock`: JSON boolean; true means stock > 0, false means stock = 0.
- `limit`: JSON integer 1–10, default 10. Larger values are rejected, not silently trusted.

Unknown keys, nulls, incorrect JSON types and invalid ranges are rejected. Queries use fixed server-owned columns and bound parameters; keyword wildcards are escaped. Results are ordered by price then ID and return at most 10 products plus `has_more`. Prices are exact two-decimal USD strings, with no currency conversion. Product output includes ID, name, price, stock, category, bounded plain-text description and existing specifications. No customer/order fields, timestamps, internal relationships, shell commands, raw SQL or mutation tools are exposed.

Specs are nullable descriptive strings, not a normalized compatibility database. Missing dimensions/socket/power information remains unknown. Detail/comparison output now includes the existing image field; search output is unchanged. Broad searches are deliberately partial when more than 10 products match; `has_more` must not be presented as a full catalog.

## Rollback and diagnostics

`ELITE_AI_AGENT_ENABLED=false` selects `LegacyRagChatService`, which uses the unchanged `GeminiRagService` full-catalog prompt without tools. There is no silent RAG fallback after a failed agent run. For native local Laravel, clear config cache after changing the setting. For Docker, set it in the root `.env`, then recreate the backend (`docker compose up -d backend`). Deploy code changes with `docker compose up -d --build --no-deps backend`; do not reset database volumes or seed.

Logs include trace ID, model, tool call count, numeric price/stock filters, result status, returned product IDs and result counts for acceptance verification. They do not contain prompts, raw provider errors, keys, SQL, stack traces or product descriptions. Invalid tool arguments are returned to Gemini as a generic correction request. Unknown tools, exhausted limits, provider outages or query failures return the existing friendly error shape, even with APP_DEBUG enabled.

## Verification

Run `php artisan test` with the repository's configured in-memory SQLite test database. Normal tests fake the Gemini transport and must never make paid API calls. Test fixtures do not enter the development database.

For an explicit live acceptance check, send `Show me products under $1000`, `Do you have any products in stock?`, and `Delete a product` through the existing chat UI. Correlate search logs with the actual catalog. The last prompt must explain that destructive actions are unavailable. No product/order/account/cart/favorite records should change.

## Product intelligence tools

The complete registered allowlist is `search_products`, `get_product_details`, `get_categories`, `check_stock`, `compare_products`, and `check_compatibility`. The tool registry is an explicit server-owned list; no mutation or admin tools are exposed. The controller, Gemini transport, model fallbacks, history limits, call budgets and RAG rollback are unchanged.

### Exact new argument schemas

All unspecified keys are rejected server-side. JSON number strings, floats for integer IDs, booleans, nulls, non-list ID arrays and duplicate IDs are rejected. IDs must be positive PHP integers. Names are resolved with `search_products`, not guessed inside detail/stock tools.

- `get_product_details`: object with required `product_id` (integer >= 1). No other parameters.
- `check_stock`: object with required `product_id` (integer >= 1). No other parameters.
- `compare_products`: object with required `product_ids` (JSON array of 2–4 distinct integers >= 1). No other parameters.
- `check_compatibility`: object with required `product_ids` (JSON array of exactly 2 distinct integers >= 1). No other parameters.
- `get_categories`: object with optional `limit` (integer 1–50, default 50) and optional `after_id` (integer >= 1, omitted on first page). Results are ID-ordered and provide `has_more` and `next_after_id`. No other parameters. Categories have no slug in the current schema.

Gemini-compatible declarations are generated by each tool's `schema()`; the above server-side constraints apply even when a model ignores its declaration. `search_products` retains its schema documented above.

### Result contracts

- Details: `status: ok`, `currency: USD`, and `product` containing `id`, `name`, two-decimal `price`, exact `stock`, category name, plain-text `description`, `description_truncated`, stored `image`, and `specifications` (brand, processor, graphics_card, ram_details, storage_details, is_custom_build). Description is capped at 4000 characters plus ellipsis; null specs stay null. No timestamps or unrelated relationships.
- Stock: `status: ok`, `product: {id, name, stock, in_stock}`. `in_stock` is exactly `stock > 0`. No low-stock label/threshold is introduced; the frontend's existing low-stock behavior is untouched.
- Categories: real `id`, `name`, correlated current `product_count`, including empty categories. At most 50 returned; keyset pagination avoids silent omission beyond that page.
- Comparison: `status: ok`, `currency: USD`, and 2–4 product projections in requested ID order. No winner/benchmark logic. Gemini explains differences using these facts.
- Unknown single ID: `status: not_found`, `product_id`. If any comparison/compatibility ID is missing: `status: not_found`, `missing_product_ids`, `products: []`. Partial comparisons are not silently presented as complete.

### Compatibility boundary

No definitive compatible/incompatible rule is supported by the current schema and stored evidence. The fields are descriptive, and there are no verified paired socket requirements, motherboard RAM support, form-factor support, GPU clearance or complete power requirements. The RAM's DDR5 text plus the motherboard's AM5 marketing description is not sufficient evidence. Parsing these words would not supply the missing facts.

For any valid existing pair, `check_compatibility` returns `status: insufficient_data`, a reason, and `known_facts` (IDs, names, categories, bounded descriptions and stored specifications). Even matching tokens or claims embedded in descriptions do not cause a fabricated confirmation. Unknown IDs return `not_found` instead. Future deterministic rules require verified data on both sides; none are invented here.

The system prompt explicitly requires the assistant to say it cannot confirm compatibility when this tool reports insufficient data. General education must be separated from verified catalog claims. It must not substitute model knowledge for the missing catalog evidence.

### Acceptance prompts

1. What categories do you have?
2. Tell me everything you know about Nova Strike eSports Build.
3. How many Nova Strike units are in stock?
4. Compare Nova Strike eSports Build with another gaming PC.
5. Is Corsair Vengeance DDR5 compatible with ASUS ROG Crosshair X670E Hero?
6. Delete Nova Strike.

Expected: category/details/stock/comparison/compatibility tools used as appropriate after discovery; compatibility explicitly unconfirmed; deletion refused without a mutation. Product names resolve through bounded search; ambiguous results require clarification. The cheapest gaming PC may be Nova Strike itself: that is not two distinct products and should not be forced into a comparison.

### Verification record (2026-09-11)

- Backend: 54 tests / 291 assertions passed on isolated in-memory SQLite; 13 new product-intelligence tests cover tool facts, invalid arguments before queries, unknown IDs, bounded results, category pagination, conservative compatibility, native multi-tool chains and parallel call IDs. No real Gemini calls in automated tests.
- Frontend lint and production build passed; no frontend changes. A rebuilt Docker backend is healthy.
- Live browser chat checks exercised real Gemini and database data: categories returned all five actual categories/counts; Nova Strike details and exact stock (15 units) loaded; comparison with Phantom-Z returned current stored specifications and USD prices; RAM/motherboard compatibility explicitly could not be confirmed; deletion was refused. Tool names/result statuses were checked in backend logs, not inferred from answer wording. Public catalog snapshots were unchanged across the live checks.
- Initial live attempts exposed premature search-only answers and overly literal name searches. Instructions now require the task-specific tool, shorter search retries, and category-only discovery for an authorized alternative comparison. No search API behavior or model configuration was changed.
- Live model routing remains probabilistic: these checks are observations, not a guarantee of every future response. The primary model had provider failures; the unchanged fallback chain recovered. The five-call budget is preserved, so difficult name discovery plus provider retries can still exhaust the budget and fail gracefully.
