# Authenticated favorites (Phase B)

Authenticated favorites are stored in MySQL. Guests continue to use only `elite-pc:favorites:guest`. No browser favorites are imported into accounts.

## Persistence and service

The additive `2026_09_12_000001_create_favorites_table` migration creates only `id`, `user_id`, `produit_id`, and timestamps. The user/product pair is unique. Both foreign keys cascade on deletion. Favorite relates to User and Produit, and both expose `favorites()`.

FavoriteService lists current product/category data, adds products idempotently, and removes only the current user's favorites. Writes lock the user row; adds also lock and validate the product. Duplicate adds produce one row. Removing an absent or already deleted product is a successful no-op. Adds to nonexistent products return 404.

The existing product deletion controller remains unchanged: products referenced by orders still cannot be deleted. A permitted product deletion removes its favorite references through the foreign key.

## API

All endpoints require `auth:sanctum`:

- `GET /api/favorites`
- `POST /api/favorites` with `{"produit_id": 123}`
- `DELETE /api/favorites/{productId}`

All successful responses return `{"items": [...current product data...]}`. The controller derives the owner from the authenticated request and rejects unexpected parameters, including `user_id` and product snapshots. Invalid input returns 422; unauthenticated requests return 401.

## Frontend

`useFavorites` supplies the existing cards, detail hearts, navbar count, and favorites page through AppContext. Authenticated changes wait for API success; there is no optimistic mutation. Loading/pending states disable hearts. Failed operations retain the last confirmed state and expose Retry on the favorites page.

Login loads backend favorites. Logout hides authenticated state and restores guest favorites without copying account data. Old responses from a previous authentication scope are ignored. Rapid clicks are guarded synchronously and requests are serialized. Refresh, login, window focus, and successful writes refresh authenticated data. Guest changes synchronize through storage events.

The old `favorites` key and `elite-pc:favorites:user:{id}` keys are left untouched and are neither read nor imported. Authenticated favorites are never persisted to localStorage. Guest favorites retain the existing browser snapshot behavior.

## Files

Added:

- `backend/database/migrations/2026_09_12_000001_create_favorites_table.php`
- `backend/app/Models/Favorite.php`
- `backend/app/Services/FavoriteService.php`
- `backend/app/Http/Controllers/API/FavoriteController.php`
- `backend/tests/Feature/FavoriteTest.php`
- `frontend/src/context/useFavorites.js`
- `backend/docs/favorites.md`

Modified:

- `backend/app/Models/User.php`
- `backend/app/Models/Produit.php`
- `backend/routes/api.php`
- `frontend/src/api/client.js`
- `frontend/src/context/AppContext.jsx`
- `frontend/src/components/ProductCard.jsx`
- `frontend/src/pages/ProductDetails.jsx`
- `frontend/src/pages/Favorites.jsx`

## Verification — 2026-09-12

- `php vendor/bin/phpunit --filter FavoriteTest --do-not-cache-result`: 9 tests, 93 assertions passed. Tests cover authentication, own-list/add/duplicate/remove, invalid and nonexistent products, owner spoofing, account isolation, current product/category values, model relationships, product cascades, order-reference deletion protection, and user cascades.
- Tests use a new SQLite in-memory connection per test, explicitly assert the database configuration, and run normal `migrate` only. No fresh migration or destructive seeding was used.
- Pint passed for all eight changed/new PHP files.
- Frontend `npm run lint` and `npm run build` passed.
- Application Docker images were rebuilt because code is baked into them. Existing startup applied only the new migration, recorded in MySQL as batch 3. Live schema inspection confirmed the unique key and both cascade foreign keys. Live route inspection confirmed Sanctum on all three endpoints.
- The requested manual acceptance scenarios were exercised through automated headless Chrome against the actual local Docker storefront, using UI login/logout and heart buttons: guest add/refresh; A login without guest/legacy import; A add/refresh/remove/refresh; detail-page re-add; logout restoring guest; B isolation/add/logout; A restoration without B's favorite; final logout restoring guest.
- Additional browser checks passed for unchanged legacy/account localStorage, API load failure and Retry, failed removal retaining the server favorite, and a delayed A response released after B login. No browser JavaScript errors were observed.
- Dedicated test customers 5 and 6 were retained with product 2 and product 3 favorited respectively. No orders were placed or stock changed by acceptance checks. Browser output is in the ignored `backend/output/favorites-acceptance-results.json`.

## Before Phase C

No AI mutation tools were added. A future integration must resolve Sanctum identity server-side and pass that User to FavoriteService; it must never trust a model-selected owner. Favorites additions/removals already have idempotent semantics. Updates are refreshed on the events above, not pushed live across devices.

Cart, checkout, order logic, Elite AI, and UI styling were not changed.
