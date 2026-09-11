# Authenticated storefront cart (Phase A)

## Ownership and persistence

`cart_items` is the authenticated source of truth. Rows contain only user/product foreign keys, quantity, and timestamps; `(user_id, produit_id)` is unique. Foreign-key deletion cascades remove abandoned cart references, not order history. Run the normal `php artisan migrate` against the intended database; never reset or reseed it. Docker startup already runs normal migrations.

All cart routes require `auth:sanctum`. `CartController` derives the user from the request and rejects unexpected input, including `user_id` and prices. `CartService` is the shared implementation; it receives a server-authenticated User, not a client-selected owner.

- `GET /api/cart`: current user's cart, no parameters.
- `POST /api/cart/items`: `{produit_id, quantity}`; adds quantity to an existing row.
- `PATCH /api/cart/items/{productId}`: `{quantity}`; replaces quantity on an existing own row.
- `DELETE /api/cart/items/{productId}`: removes own row; absent items are a harmless no-op.

Quantity must be a JSON integer from 1 to 100 and cannot exceed current stock. Carts allow 50 distinct products, matching checkout limits. The UI's decrement-to-zero behavior calls DELETE rather than persisting zero. Unknown products/updates to missing rows return 404; invalid input returns 422; unauthenticated requests return 401.

Responses contain `items`, `currency: USD`, and `total`. Items use the existing UI's product keys plus `quantite`, `subtotal`, `cart_item_id`, and `cart_updated_at`. Prices, stock, specifications, names, and images come from current product records. Adding/updating never reserves or deducts stock; stock can subsequently fall below cart quantity, so checkout must revalidate.

Writes lock the user row first (including empty carts), then the target product; the unique index additionally prevents duplicate rows. Concurrent requests from different tabs/devices therefore serialize safely. Client writes are queued, not automatically retried, and old-account responses are ignored.

## Guest and frontend behavior

Guests use only `elite-pc:cart:guest`. The former ownerless `cart` key is left untouched for recovery, but is not read, copied, or imported. Login loads the account's backend cart without merging guest data; logout restores the guest cart. Authenticated carts are never written to localStorage. Favorites and Elite AI are unchanged.

The existing cart page, drawer, and checkout retain their design. Added loading/error/retry and pending states reflect the new network data source. The cart refreshes on login/reload, writes, checkout success, and window focus. Guest storage changes synchronize between tabs. Server changes are not pushed live to all open devices.

## Checkout and snapshot cleanup

Checkout still sends product IDs/quantities. Each line additionally carries the cart row ID and its six-digit-microsecond `cart_updated_at` value from the last server response. These fields are cleanup preconditions, never price or authorization inputs.

The existing order transaction now acquires the same user lock before its existing product locks. Its validation, server-side pricing, stock checks, order creation, and stock deduction remain intact. Only after success does CartService delete a purchased row that still matches ALL of: authenticated owner, row ID, product ID, purchased quantity, and update timestamp. Cleanup is in the same transaction, so failures roll everything back.

New products, changed quantities (including changes back to the old quantity), and removed/readded rows survive an older checkout snapshot. A changed row is conservatively left in full rather than guessing which units the user now intends to keep. Cart timestamps advance on every update, even at a frozen clock. The frontend refetches the cart after success instead of clearing it blindly.

Older API clients can still submit IDs/quantities without the optional snapshot fields; their order remains valid, but no cart cleanup is attempted for those lines. Guest checkout is still unsupported: checkout already required login and still does.

## Verification

- `php artisan test --filter='CartTest|OrderInventoryTest'`: 20 passed, 114 assertions. Isolated in-memory test database, no real Gemini calls.
- Formatting checked for changed PHP files; frontend lint/build passed.
- Normal additive migration applied to Docker MySQL; frontend/backend rebuilt solely to apply and verify this phase.
- Real storefront: guest add/refresh, legacy-key isolation, A login/add/refresh/logout, B isolation/add/logout, and A restoration passed. Increase/decrease/remove controls passed.
- One local cash-on-delivery acceptance order (#2) was created for the dedicated A test account. Nova Strike stock decreased from 15 to 14 only at checkout; purchased cart became empty. Two dedicated test customer accounts and that order were retained, not deleted. B's test cart retains one product.
- Four concurrent MySQL adds increased the dedicated B cart from 1 to 5 with no stock change; it was restored to quantity 1.
- A delayed real cart response was held across A logout/B login in the same SPA and did not overwrite B's cart.

## Before future AI mutation tools

This phase does not add mutation idempotency keys or change chat authentication. Before AI cart writes, resolve Sanctum identity server-side and prevent duplicate incremental adds when a model/provider/request retries. Never let the model choose `user_id`. Keep favorites, order actions, and checkout outside that future cart-tool scope. Browser checkout itself also retains its existing non-idempotent order endpoint; a lost success response must not be blindly resubmitted.
