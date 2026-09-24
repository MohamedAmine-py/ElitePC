# ElitePC

ElitePC is a full-stack ecommerce application for browsing and ordering high-performance PCs, components, and peripherals. It combines a responsive React storefront, a Laravel REST API, inventory-aware order processing, an Admin workspace, PDF invoices, and a Gemini-powered hybrid RAG shopping assistant.

The repository is organized as a single project with independently runnable frontend and backend applications. Docker Compose is the recommended way to start the complete stack.

## Features

### Storefront

- API-backed product catalog, categories, search, and product detail pages
- Responsive product cards with stock, pricing, specifications, and image fallbacks
- Guest cart with quantity controls and persistent browser storage; authenticated cart stored through the Laravel/MySQL cart API
- Guest favorites in browser storage; authenticated favorites stored through the Laravel/MySQL favorites API
- Guest and authenticated carts remain separate, as do guest and authenticated favorites; neither is automatically merged on sign-in
- Checkout with delivery details, payment-method selection, and validation feedback
- Customer registration, login, logout, and persisted Sanctum bearer-token sessions
- Authenticated order history and customer-authorized order details
- Downloadable, print-friendly PDF invoices
- Dark interface with the shared ElitePC accent colors and visual system
- Responsive layouts for desktop, tablet, and mobile

### Order and inventory safety

- Prices are recalculated from current database values on the server
- Product rows are locked during checkout to prevent concurrent overselling
- Stock availability is validated before an order is created
- Cancelling an order restores its stock once
- Reactivating a cancelled order revalidates and deducts stock
- Deleting an active order safely restores stock
- Customers can access only their own orders and invoices

### Administration

- Role-protected Admin interface
- Real dashboard KPIs for clients, products, orders, in-progress orders, and confirmed revenue
- Product and category management
- Order review and status management
- User creation, editing, and safe deletion rules
- Recent-order overview and inventory visibility

### Elite AI

Elite AI is a **read-only hybrid RAG shopping assistant powered by Gemini**. It combines curated hardware and store knowledge with live product intelligence.

Curated knowledge lives in `backend/knowledge/`: `hardware.md`, `buying-guide.md`, `compatibility.md`, and `store.md`. `KnowledgeLoader` splits these documents into structured sections/chunks. `KnowledgeRetriever` performs deterministic weighted lexical retrieval and supplies only relevant knowledge sections for each question. Gemini combines that context with its reasoning and general hardware knowledge under grounding rules.

Current product facts come from six read-only backend tools backed by the live database:

- **Search products** — filtered discovery, with exhaustive retrieval for explicit all-products requests
- **Product details** — current descriptions and stored specifications
- **Categories** — current catalog categories
- **Stock** — current product availability and quantities
- **Comparisons** — verified facts for selected products
- **Compatibility** — catalog evidence for specific product pairs, with an insufficient-data conclusion when evidence is missing

Live tools are authoritative for products, exact USD prices, stock, specifications, comparisons, and product-specific compatibility evidence. General knowledge does not establish current inventory or fill gaps in compatibility evidence. Missing store policies are acknowledged rather than invented.

The chat supports bounded conversation history, model fallback, and safe Markdown rendering, including comparison tables that scroll within messages on narrow screens. Gemini credentials stay on the backend.

Elite AI cannot access private customer information or modify carts, favorites, orders, checkout, payments, customer data, or Admin data. Shopping actions remain in the normal storefront and authenticated cart, favorites, and order APIs.

## Tech stack

### Frontend

- React 19
- React Router 7
- Vite 8
- Plain CSS with shared ElitePC color variables
- React Markdown and remark-gfm for safe assistant-message formatting and tables
- ESLint 9

### Backend

- PHP 8.2+; the Docker image uses PHP 8.3 with Apache
- Laravel 12
- Laravel Sanctum bearer-token authentication
- Eloquent ORM and database transactions
- Dompdf for PDF invoices
- Google Gemini PHP Laravel integration
- PHPUnit 11
- Laravel Pint

### Data and infrastructure

- MySQL 8.4 in Docker
- MySQL or MariaDB for manual local development
- Docker Compose
- Nginx for the production frontend bundle and SPA routing
- Named Docker volumes for MySQL data and the generated local Laravel application key

## Project structure

```text
ElitePC/
├── backend/
│   ├── app/                    # Laravel models, controllers, requests, and services
│   │   └── Services/EliteAI/    # Hybrid RAG assistant and read-only catalog tools
│   ├── config/                 # Laravel and integration configuration
│   ├── database/               # Migrations, factories, and idempotent seeder
│   ├── docker/                 # Apache virtual host and container entrypoint
│   ├── knowledge/              # Curated hardware, buying, compatibility, and store guides
│   ├── resources/views/        # PDF invoice template
│   ├── routes/                 # API and web routes
│   ├── tests/                  # Backend feature and knowledge retrieval unit tests
│   └── Dockerfile
├── frontend/
│   ├── public/                 # Local brand, category, and product assets
│   ├── src/                    # React pages, components, context, API client, and styles
│   ├── tests/                  # Assistant Markdown rendering and safety tests
│   ├── nginx.conf              # Static serving and React Router fallback
│   └── Dockerfile
├── .env.docker.example         # Root Docker Compose environment template
├── docker-compose.yml
└── README.md
```

## Prerequisites

### Docker setup

- Git
- Docker Desktop, or Docker Engine with Docker Compose v2

### Manual setup

- PHP 8.2 or newer with the extensions required by Laravel and PDO MySQL
- Composer 2
- Node.js compatible with Vite 8; Node.js 22 LTS is recommended and used by Docker
- npm
- MySQL or MariaDB

## Quick start with Docker

Clone the repository into an `ElitePC` directory:

```bash
git clone https://github.com/MohamedAmine-py/ElitePC.git
cd ElitePC
```

Copy the Docker environment template to the root `.env` file.

PowerShell:

```powershell
Copy-Item .env.docker.example .env
```

macOS/Linux:

```bash
cp .env.docker.example .env
```

The included values are local-development defaults. Add a valid `GEMINI_API_KEY` if Elite AI is needed, and replace the database credentials before using the configuration in any shared environment.

Build and start the stack:

```bash
docker compose up --build -d
```

Open:

- Storefront: [http://localhost:3000](http://localhost:3000)
- Laravel API: [http://localhost:8000/api](http://localhost:8000/api)
- Backend health check: [http://localhost:8000/up](http://localhost:8000/up)

The MySQL service is available only inside the Compose network. Laravel waits for MySQL and runs incremental `php artisan migrate --force` migrations when the backend starts. It never runs `migrate:fresh`, and seeding does not run automatically.

MySQL data persists in the `mysql_data` volume. When no explicit `APP_KEY` is supplied, the generated local key persists in the `laravel_runtime` volume.

Common lifecycle commands:

```bash
# Stop containers and preserve volumes
docker compose down

# Restart existing containers
docker compose up -d

# Rebuild after source, knowledge, Docker, dependency, or frontend build-setting changes
docker compose up --build -d
```

Avoid `docker compose down --volumes` when the Docker database must be preserved.

## Environment configuration

Environment files can contain credentials and must not be committed. The committed example files contain development placeholders only.

### Root `.env` — Docker Compose

Create this file from `.env.docker.example`.

- `MYSQL_DATABASE`: database created by the MySQL container
- `MYSQL_USER`: application database user
- `MYSQL_PASSWORD`: application database password
- `MYSQL_ROOT_PASSWORD`: MySQL root password
- `APP_KEY`: optional explicit Laravel key; when empty, Docker generates and persists one locally
- `APP_ENV`: Laravel environment, default `local`
- `APP_DEBUG`: Laravel debug output, default `false` in Docker
- `BACKEND_URL`: public Laravel URL, default `http://localhost:8000`
- `BACKEND_PORT`: published backend port, default `8000`
- `FRONTEND_PORT`: published frontend port, default `3000`
- `CORS_ALLOWED_ORIGINS`: comma-separated permitted browser origins
- `VITE_API_URL`: browser-reachable API base URL, default `http://localhost:8000/api`
- `GEMINI_API_KEY`: private server-side Gemini key
- `GEMINI_REQUEST_TIMEOUT`: Gemini request timeout in seconds
- `ELITE_AI_AGENT_ENABLED`: defaults to `true` for the hybrid assistant; `false` selects the retained legacy catalog-only chat path
- `LOG_LEVEL`: Laravel log level

Example placeholder:

```dotenv
GEMINI_API_KEY=your_key_here
```

Never prefix or duplicate this key as a Vite variable. Vite variables are embedded in the browser bundle.

### `backend/.env` — manual Laravel development

Create this file from `backend/.env.example`. Relevant settings include:

- Laravel application settings: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`
- Database connection: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Browser access: `CORS_ALLOWED_ORIGINS`
- Sessions, cache, queue, mail, filesystem, and logging settings provided by Laravel
- `GEMINI_API_KEY`, `GEMINI_REQUEST_TIMEOUT`, and `ELITE_AI_AGENT_ENABLED` for Elite AI
- `SEED_ADMIN_NAME`, `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD`
- `SEED_CLIENT_NAME`, `SEED_CLIENT_EMAIL`, `SEED_CLIENT_PASSWORD`

For MySQL local development, replace the example SQLite setting with your own local connection:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=elitepc
DB_USERNAME=your_local_user
DB_PASSWORD=your_local_password
CORS_ALLOWED_ORIGINS=http://localhost:5173
GEMINI_API_KEY=your_key_here
```

### `frontend/.env` — manual Vite development

Create this file from `frontend/.env.example`:

```dotenv
VITE_API_URL=http://127.0.0.1:8000/api
```

This URL is consumed by the browser and must point to a host reachable from the browser, not to an internal Docker service name.

## Manual local development

### Backend

From the repository root:

```bash
cd backend
composer install
```

Create the local environment file:

```bash
cp .env.example .env
```

On PowerShell, use `Copy-Item .env.example .env` instead. Configure the MySQL or MariaDB variables described above, create the named empty database, then run:

```bash
php artisan key:generate
php artisan migrate
php artisan serve
```

The API is available at `http://127.0.0.1:8000/api` by default.

### Frontend

In a second terminal:

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

On PowerShell, use `Copy-Item .env.example .env`. The Vite development server is normally available at `http://localhost:5173`.

## Database and seed data

The Docker stack uses MySQL 8.4. Manual development can use MySQL or MariaDB through Laravel's `mysql` driver.

Schema changes are managed by Laravel migrations:

```bash
cd backend
php artisan migrate
```

The optional seeder creates an idempotent development catalog and local demo accounts. Repeated runs do not duplicate its natural catalog identities, and existing product stock is not reset.

Run it only when demo data is wanted:

```bash
# Docker
docker compose exec backend php artisan db:seed

# Manual backend
php artisan db:seed
```

Do not use `migrate:fresh` on a database containing data that must be retained.

## Local demo accounts

`DatabaseSeeder` creates these accounts only when Laravel runs in the `local` or `testing` environment:

- Admin: `admin@ecommerce.test` / `admin123`
- Customer: `client@ecommerce.test` / `client123`

They are development-only defaults from `backend/.env.example`. Change the `SEED_*` values when needed, never use these credentials in production, and do not run local demo seeding in a production environment.

## API overview

All endpoints are prefixed with `/api`.

### Public

- `POST /register` — create a customer account
- `POST /login` — authenticate and issue a Sanctum bearer token
- `GET /products` — list products
- `GET /products/{id}` — retrieve a product
- `GET /categories` — list categories
- `POST /support/chat` — submit a bounded-history Elite AI message

### Authenticated

Send `Authorization: Bearer <token>`.

- `GET /user` — current user
- `POST /logout` — revoke the current token
- `GET /cart` — read the current user's cart
- `POST /cart/items` — add a product or increment its cart quantity
- `PATCH /cart/items/{product}` — set an item's quantity
- `DELETE /cart/items/{product}` — remove a product from the cart
- `GET /favorites` — read the current user's favorites
- `POST /favorites` — save a product to favorites
- `DELETE /favorites/{product}` — remove a product from favorites
- `GET /orders` — current customer's orders; Admin receives all orders
- `POST /orders` — create an order
- `GET /orders/{id}` — authorized order details
- `GET /orders/{id}/invoice` — authorized PDF invoice download

### Admin only

These routes require both Sanctum authentication and the Admin role.

- `POST /products` — create a product
- `PUT /products/{id}` — update a product
- `DELETE /products/{id}` — safely delete a product
- `POST /categories` — create a category
- `PUT /categories/{id}` — update a category
- `DELETE /categories/{id}` — safely delete a category
- `PATCH /orders/{id}/status` — update order status
- `DELETE /orders/{id}` — safely delete an order
- `GET /admin/stats` — dashboard statistics and recent orders
- `GET|POST /admin/users` — list or create users
- `PUT|DELETE /admin/users/{id}` — update or safely delete a user

The exact request fields and validation rules remain defined by Laravel request classes and controllers.

## Testing and quality checks

Backend unit and feature tests cover:

- Authentication, authorization, login rate limiting, and safe Admin deletion
- Order creation, authoritative pricing, inventory safety, and invoice rendering
- Authenticated cart ownership, quantity validation, and checkout cleanup
- Authenticated favorites ownership, idempotent additions, and removal
- Elite AI product intelligence tools, bounded searches, exhaustive catalog retrieval, and compatibility evidence
- Knowledge loading, deterministic retrieval, and hybrid RAG integration
- Grounding instructions, unsupported policies, read-only tool restrictions, history limits, and model fallback
- Idempotent development seeding

Run backend tests locally with Composer development dependencies and PDO SQLite enabled. The PHPUnit configuration uses an in-memory SQLite database; AI integration tests mock Gemini rather than requiring live API calls.

```bash
cd backend
php artisan test
```

Format backend PHP with Laravel Pint:

```bash
cd backend
./vendor/bin/pint
```

On Windows PowerShell, `vendor/bin/pint` can be used when the shell resolves the PHP executable.

Frontend tests cover assistant Markdown formatting, comparison tables, code, safe links, and raw HTML rejection.

```bash
cd frontend
npm test
npm run lint
npm run build
```

The repository currently does not include a browser end-to-end test suite.

## Docker troubleshooting

Check container health and published ports:

```bash
docker compose ps
```

Inspect service logs:

```bash
docker compose logs backend
docker compose logs frontend
docker compose logs mysql
```

Check the backend directly:

```bash
curl http://localhost:8000/up
curl http://localhost:8000/api/products
```

The application source and knowledge documents are copied into Docker images rather than mounted from the working tree. After changing them, dependencies, Dockerfiles, Nginx configuration, `VITE_API_URL`, or another frontend build variable, rebuild the affected containers:

```bash
docker compose up --build -d
```

Nginx is configured to serve React Router URLs through `index.html`, including direct refreshes of `/products`, `/orders`, and nested product/order routes. If routing behavior appears stale, confirm the frontend image was rebuilt.

Changes to runtime backend variables normally require container recreation. Changes to `VITE_API_URL` require rebuilding the frontend because Vite embeds the value during compilation.

## Security notes

- Keep root, backend, and frontend `.env` files out of version control.
- Keep `GEMINI_API_KEY` server-side; never expose it through `VITE_*` variables.
- Public registration always creates a customer account and does not accept an Admin role.
- Admin endpoints are protected by Sanctum and role middleware.
- Login and Elite AI endpoints are rate-limited.
- Order totals use server-side database prices rather than client-submitted prices.
- Stock validation and mutation use transactions and row locks.
- Customer cart, favorites, order, and invoice access is ownership-checked by the backend.
- Assistant Markdown does not enable arbitrary raw HTML; external links use safe link attributes.

These controls reduce common risks but do not replace environment-specific production hardening, HTTPS, secure secret management, monitoring, backups, and dependency maintenance.

## Screenshots

Add final portfolio screenshots here when available:

- Home
- Products
- Product details
- Cart and Checkout
- Elite AI
- Admin dashboard

## Project status

ElitePC is a finished full-stack portfolio application with a complete storefront, order workflow, Admin management, Docker Compose environment, and read-only hybrid RAG shopping assistant.

Current operational boundaries:

- Elite AI depends on Gemini API availability, credentials, and quota.
- No external payment gateway is integrated; checkout records the supported payment-method field only.
- The Contact form is currently a non-submitting presentation placeholder.
- The repository does not include production hosting configuration or browser end-to-end tests.

## License

Copyright © 2026 Mohamed Amine Benbouselham.

ElitePC is available for personal, educational, research, and other
non-commercial use. Commercial use requires prior written permission from
the copyright holder.

See [LICENSE](LICENSE) for the full terms.

## Author

Mohamed Amine
