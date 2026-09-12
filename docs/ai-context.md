# Elite AI authenticated context and replay safety (Phase C)

Phase C adds trusted execution context and replay infrastructure only. The production registry still contains exactly search_products, get_product_details, get_categories, check_stock, compare_products, and check_compatibility. There are no cart, favorite, private-read, checkout, or order-action tools.

## Authentication and request identity

POST /api/support/chat remains public for catalog questions. OptionalChatAuthentication treats an absent Authorization header as guest. A supplied header must contain a valid Sanctum bearer token; malformed, expired, revoked, and invalid tokens return 401 rather than silently becoming guest. Session fallback does not authorize bearer chat.

The frontend sends only message/history in the body, plus its bearer token when logged in. SupportChatRequest rejects user_id (even null), user, context, and execution_id in the body. History entries accept only role/content. Arbitrary identity text in conversation history is still untrusted prose.

AgentContext receives the server-resolved User or null and creates a fresh server UUID. That UUID is used for tracing and returned in X-Chat-Execution-ID. Context and User are never included in Gemini prompts, content, or schemas. The request ID does not authorize anything.

## Tool contract and private boundary

The existing AgentTool contract remains the public, read-only path. ToolExecutor calls existing tools with the same arguments and model schemas.

Future private tools must extend AuthenticatedAgentTool and explicitly implement isMutating(). executeWithContext receives arguments and AgentContext separately, calls requireUser(), then invokes the tool's protected run method. Calling a private tool through the old execute(arguments) path throws AuthenticationException. ToolExecutor also requires a User before private execution. Model arguments containing user_id, including nested occurrences, are rejected. ToolRegistry rejects schemas declaring user_id.

Private tools must validate their business arguments and pass context->requireUser() to the existing user-scoped service. They must not look up an owner selected by the model. The abstract contract is infrastructure; no concrete private tools are registered. ContextProbeTool exists only in tests.

## Mutation replay within one logical execution

AgentContext owns a MutationReplayGuard throughout the model loop, outside the transcript that resets during fallback.

The key combines the server execution UUID, tool name, and canonical JSON argument fingerprint. Object keys are sorted; list order and JSON numeric types are preserved. Provider call IDs are additional consistency checks, not the only identity: providers may omit them or change them on fallback.

- Repeated identical mutation intent within one request returns the first result, even with a different or missing provider ID.
- A provider ID reused with different tool/arguments is rejected.
- A pending/failed operation is marked before business logic; an uncertain outcome is never executed again within that context.
- After any mutation attempt, model fallback may replay known results but cannot introduce a new mutation.
- Read-only tools execute normally, including repeated calls.
- A new logical request receives a new server execution UUID, so the same action can intentionally execute again.

This conservatively coalesces identical mutation arguments within a single request. A future tool must define strict argument types/defaults so semantically equivalent actions have a canonical representation. Two deliberately identical actions in the same message are not treated as separate writes by this policy.

Gemini model order, public-tool budgets, thought-signature handling, and the existing read-only prompt are preserved.

## Detectable HTTP transport retries

Each frontend send includes a UUID in X-Chat-Request-ID. It is a correlation key, not a credential. There is no automatic mutation retry.

For authenticated requests, ChatExecutionStore validates and normalizes that UUID, combines it with the server-resolved user and Sanctum token record ID, and atomically reserves a cache record before running the agent. The record holds the server execution ID, input fingerprint, status, and final response; it does not store a conversation transcript or User/token values.

Matching completed retries return the original response and execution ID. Conflicting input or an in-progress/uncertain record returns 409 and does not start another execution. Provider-error responses are also retained. Authentication is checked on every HTTP request before cached results can be read.

Records expire after one hour in the configured Laravel cache. The current Docker deployment uses the file cache; tests use the isolated array cache. Guest requests do not share cached responses because they have no authenticated principal. Requests without a correlation header still get a server UUID and in-request replay protection.

## Frontend identity state

SupportChat mounts its stateful child with the current bearer token as its React key. Guest -> account, account -> another session/account, and logout all discard messages, drafts, loading/error state, and panel state. No chat history is stored in localStorage or persisted across devices.

Requests are aborted on unmount. An independent active-scope check ignores late successes, failures, and finally callbacks even when a transport ignores cancellation. Each current-scope send passes only that scope's recent history. The existing chat styling and rendering are unchanged.

## Files added

- backend/app/Http/Middleware/OptionalChatAuthentication.php
- backend/app/Services/EliteAI/AgentContext.php
- backend/app/Services/EliteAI/ChatExecutionStore.php
- backend/app/Services/EliteAI/Contracts/AuthenticatedAgentTool.php
- backend/app/Services/EliteAI/MutationReplayGuard.php
- backend/app/Services/EliteAI/ToolExecutor.php
- backend/tests/Concerns/UsesChatTestDatabase.php
- backend/tests/Fixtures/ContextProbeTool.php
- backend/tests/Feature/ChatAuthenticationTest.php
- backend/tests/Feature/AgentReplayTest.php
- frontend/tests/supportChat.browser.cjs
- backend/docs/ai-context.md

## Files modified

- backend/app/Http/Controllers/API/SupportChatController.php
- backend/app/Http/Requests/SupportChatRequest.php
- backend/app/Services/EliteAI/Contracts/AgentTool.php
- backend/app/Services/EliteAI/EliteAgentService.php
- backend/app/Services/EliteAI/ToolRegistry.php
- backend/routes/api.php
- backend/tests/Feature/EliteAgentTest.php
- backend/tests/Feature/ProductIntelligenceToolsTest.php
- frontend/src/api/client.js
- frontend/src/components/SupportChat.jsx

The two existing AI suites use a fresh in-memory SQLite connection and normal migrations via UsesChatTestDatabase, avoiding the destructive migration command invoked by RefreshDatabase. No application migration was added.

## Verification — 2026-09-12

From backend:

```sh
php vendor/bin/phpunit --filter 'ChatAuthenticationTest|AgentReplayTest|EliteAgentTest|ProductIntelligenceToolsTest|SupportChatHistoryTest' --do-not-cache-result
```

52 tests and 414 assertions passed. Coverage includes valid/guest/invalid/expired/revoked authentication, spoofed context, web-session fallback rejection, transport result reuse/conflicts/account separation, server UUIDs, all six public tools for guest/account contexts, provider payload privacy, private-tool guards, duplicate/missing/changed call IDs, separate executions, fallback, uncertain execution, and concurrent cache reservations. Gemini is mocked in backend tests.

Pint passed for the changed/new PHP files. Frontend npm run lint and npm run build passed.

The checked-in browser regression test runs the built frontend against mocked APIs with an isolated Chrome context:

```sh
node frontend/tests/supportChat.browser.cjs
```

Install/provide Playwright locally or set PLAYWRIGHT_MODULE to its module path; CHROME_CHANNEL defaults to chrome. Five grouped checks passed: guest history, guest-to-A reset/bearer transport, A-to-guest-to-B isolation, delayed success/failure/finally isolation, and UUID/body/privacy assertions. The test deliberately ignores AbortSignal to verify state isolation independently of cancellation.

The requested manual acceptance scenarios were also exercised using automated Chrome against the live Docker storefront and real Gemini: guest, A, and B received catalog answers; guest/A/B transitions started with clean chat; final logout removed B's transcript; invalid bearer returned 401. A concurrent authenticated duplicate returned 409 or the existing result, and a completed duplicate returned the exact same body and server execution ID. Existing dedicated test customers 5 and 6 were reused; no new accounts, orders, cart/favorite writes, product changes, or stock changes were made. Runtime results are in ignored backend/output/phase-c-live-results.json.

Application images were rebuilt to verify the baked-in code. No new migration or destructive seeding was needed.

## Prerequisites before enabling actual mutations

This is not a durable exactly-once guarantee. A new correlation ID, cache expiry/eviction, container cache loss, token change, or a separate request can begin a new execution. A timed-out HTTP request cannot prove whether a future business operation committed.

Before non-idempotent cart tools are enabled, define operation-level idempotency durably and atomically with the business transaction, and define how intentional retries retain identity or reconcile uncertain outcomes. Multiple backend instances need a shared atomic cache if using the transport replay layer. Do not treat clicking Send again as a safe retry of an uncertain mutation: the current UI creates a new request ID for each send.

Future tools also need strict canonical argument validation, user-scoped service calls, suitable result filtering, and their own focused authorization/replay tests. Keep checkout, payment, cancellation/refund, and admin operations outside that scope. Phase C does not change CartService or FavoriteService.
