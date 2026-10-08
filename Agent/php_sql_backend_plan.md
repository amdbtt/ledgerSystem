---
name: PHP SQL Backend Plan
overview: There is no backend left. After you confirm, write AgentPlanBackend.md at the repo root with a PHP 8 and MySQL plan that restores the API the React screens already expect.
todos:
  - id: write-plan-md
    content: Write AgentPlanBackend.md at the repo root with the PHP 8 and MySQL plan, schema, routes, and build order. Do not implement the API in that step.
    status: completed
isProject: false
---

# PHP and SQL backend plan

There is no `backend/` directory. [AgentremovingBackend.md](AgentremovingBackend.md) records that the old Node, Express, MongoDB, and JWT API (port 8888) was deleted. The React app on port 3000 still opens every screen, but [frontend/src/request/request.js](../frontend/src/request/request.js) and [frontend/src/auth/auth.service.js](../frontend/src/auth/auth.service.js) answer locally from [frontend/src/data/dummyData.js](../frontend/src/data/dummyData.js). Settings URLs in [frontend/src/config/serverApiConfig.js](../frontend/src/config/serverApiConfig.js) are empty, and login is bypassed (`isLoggedIn: true` in the auth reducer).

After you confirm this plan, the only file to add is [AgentPlanBackend.md](AgentPlanBackend.md). It will hold the plan below. PHP and SQL will not be implemented in that step.

## Stack

- PHP 8.2, a single front controller, PDO, no Laravel
- MySQL 8 (or MariaDB)
- JWT (`Authorization: Bearer`)
- Listen on `http://localhost:8888` so the old contract can be restored
- JSON always uses `{ success, result, message, pagination }`
- Every record the UI reads exposes `_id` as a string, even though SQL uses integer primary keys
- Nested objects the tables already read: `client`, `invoice`, `paymentMode`, and `items[]`

## Live screens to support

Routes in [frontend/src/router/routes.jsx](../frontend/src/router/routes.jsx): Dashboard, Customer (`client`), Invoice, Quote, Payment, Taxes, Payment Mode, Settings, Profile, About.

Leave unused forms out of v1: Lead, Employee, Inventory, Order, Currency, and [frontend/src/forms/CustomerForm.jsx](../frontend/src/forms/CustomerForm.jsx). The live customer screen uses [frontend/src/pages/Customer/config.js](../frontend/src/pages/Customer/config.js): `name`, `country`, `address`, `phone`, `email`.

## SQL tables

- `admins`: name, surname, email, password hash, photo, role, enabled
- `password_resets`: admin id, token hash, expiry
- `settings`: category, key, value (the money, company, app, and finance rows already listed in `dummyData.js`)
- `clients`
- `taxes`: tax name, tax value, is default, enabled
- `payment_modes`: name, description, is default, enabled
- `invoices` and `invoice_items` (`itemName`, description, price, quantity, total)
- `quotes` and `quote_items` (same item shape; quote has no payment status)
- `payments`: number, date, amount, currency, client, invoice, payment mode, ref, description
- `uploads`: logo and profile files under `backend/public/uploads/`

Soft-delete with a `removed` flag. Seed the current dummy customers, invoices, quotes, payments, taxes, payment modes, settings (currency PKR), and admin `admin@admin.com` / `admin123`.

## HTTP routes the React client will call again

CRUD, matching the methods already named in `request.js`:

- `POST /api/{entity}/create`
- `GET /api/{entity}/read/{id}`
- `PATCH /api/{entity}/update/{id}`
- `DELETE /api/{entity}/delete/{id}`
- `GET /api/{entity}/list?page=&items=`
- `GET /api/{entity}/listAll`
- `GET /api/{entity}/search?q=&fields=`
- `GET /api/{entity}/filter?filter=&equal=`
- `GET /api/{entity}/summary`

Entities: `client`, `invoice`, `quote`, `payment`, `paymentMode`, `taxes`.

Auth and profile:

- `POST /api/login`, `POST /api/logout`, `POST /api/forgetpassword`, `POST /api/resetpassword`
- `PATCH /api/admin/profile/update` (multipart photo)
- `PATCH /api/admin/profile/password`

Settings:

- `GET /api/setting/listAll` returns `[{ settingCategory, settingKey, settingValue }]`
- `PATCH /api/setting/updateBySettingKey/{key}`
- `PATCH /api/setting/updateManySetting`
- `POST /api/setting/upload/{settingKey}` for the company logo

Finance rules:

- Creating an invoice, quote, or payment advances `last_invoice_number`, `last_quote_number`, or `last_payment_number`
- Recording a payment adds to invoice `credit` and sets `paymentStatus` to `unpaid`, `partially`, or `paid`
- `GET /api/quote/convert/{id}` copies the quote into a new unpaid invoice
- Summary for invoice, quote, and payment returns `total`, `total_undue`, and `performance[]` (`status`, `percentage`) for the selected currency. Client summary returns `total`, `active`, and `new`

PDF and mail, after CRUD works:

- `GET /download/{invoice|quote|payment}/{name}.pdf` with Dompdf
- `POST /api/{entity}/mail` with `{ id }` via PHPMailer, using the company email from settings

## Frontend reconnect (later, after the API exists)

Point [frontend/src/config/serverApiConfig.js](../frontend/src/config/serverApiConfig.js) at `http://localhost:8888/api/`, restore axios in `request.js` and `auth.service.js`, turn the login gate back on, and restore the Vite `/api` proxy. Keep [frontend/src/data/dummyData.js](../frontend/src/data/dummyData.js) only as the seed source of truth until the SQL seed matches it.

## Build order inside the markdown

1. Project layout, `.env`, PDO, CORS, JSON helper
2. Schema and seed
3. JWT login
4. Settings list, because [frontend/src/apps/ErpApp.jsx](../frontend/src/apps/ErpApp.jsx) will not render until `setting` `listAll` succeeds
5. Client, tax, and payment mode CRUD
6. Invoice, quote, payment, credit, and quote-to-invoice convert
7. Dashboard summaries
8. Uploads, PDF, mail
9. Wire the React client off the local stubs
