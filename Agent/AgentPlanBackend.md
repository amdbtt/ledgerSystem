# PHP and SQL backend plan

Build a new API in PHP and MySQL that the existing React screens can call. Do not bring back Node, Express, or MongoDB.

There is no `backend/` directory. [AgentremovingBackend.md](AgentremovingBackend.md) records that the old API was deleted. The UI on port 3000 still opens every screen, but it answers from memory:

| Piece | Location | What it does today |
| --- | --- | --- |
| UI | `frontend/` | Vite, React, Ant Design, Redux, port 3000 |
| Local data | `frontend/src/data/dummyData.js` | Customers, invoices, quotes, payments, taxes, payment modes, settings |
| HTTP stub | `frontend/src/request/request.js` | Same method names as the old API, no network |
| Auth stub | `frontend/src/auth/auth.service.js` | Always returns a shell admin |
| API URLs | `frontend/src/config/serverApiConfig.js` | Empty strings |
| Login gate | `frontend/src/redux/auth/reducer.js` | `isLoggedIn: true`, so the login screen is skipped |

This document is the plan only. It does not add PHP files, a database, or frontend wiring.

## Stack

| Choice | Value |
| --- | --- |
| Language | PHP 8.2 |
| Shape | One front controller, PDO, no Laravel |
| Database | MySQL 8, or MariaDB |
| Auth | JWT in `Authorization: Bearer` |
| Listen | `http://localhost:8888` |
| Files | `backend/public/uploads/` |
| PDF | Dompdf, after CRUD works |
| Mail | PHPMailer, after CRUD works |

Every JSON body uses:

```json
{
  "success": true,
  "result": {},
  "message": "",
  "pagination": { "page": 1, "count": 0 }
}
```

`list` and `search` include `pagination`. `read`, `create`, `update`, and `delete` include `result`. SQL primary keys are integers. Every record the UI reads also has `_id` as a string of that integer. List and read responses expand `client`, `invoice`, `paymentMode`, and `items` into objects. The forms submit ids, not nested objects.

## Screens this API must serve

Routes in `frontend/src/router/routes.jsx`:

| Route | Entity |
| --- | --- |
| `/` | Dashboard summaries for `invoice`, `quote`, `payment`, `client` |
| `/customer` | `client` |
| `/invoice` | `invoice` |
| `/quote` | `quote` |
| `/payment` | `payment` |
| `/taxes` | `taxes` |
| `/payment/mode` | `paymentMode` |
| `/settings` | `setting` |
| `/profile` | `admin` |
| `/about` | No API |

Leave these unused forms out of the first version: Lead, Employee, Inventory, Order, Currency, and `frontend/src/forms/CustomerForm.jsx`. The live customer screen uses `frontend/src/pages/Customer/config.js`: `name`, `country`, `address`, `phone`, `email`.

`frontend/src/apps/ErpApp.jsx` calls `setting` `listAll` on startup and stays on the loader until `success` is true. Settings must work before the other screens are useful.

## SQL schema

Soft-delete business rows with `removed` (`0` or `1`). Lists hide `removed = 1`. Add `created_at` and `updated_at` on every business table so "new this month" and number order have a source.

Store money as `DECIMAL(12,2)`. Store `tax_rate` as the percent the form sends (`10`, not `0.10`). The read screen prints `taxRate` with a `%` sign.

### `admins`

| Column | Notes |
| --- | --- |
| `id` | Primary key. JSON `_id` |
| `name`, `surname`, `email` | Email unique |
| `password_hash` | `password_hash()` / bcrypt |
| `photo` | File name or null |
| `role` | Default `admin` |
| `enabled` | Default `1` |
| `removed` | Default `0` |

Seed one admin: `admin@admin.com` / `admin123`.

### `password_resets`

| Column | Notes |
| --- | --- |
| `id` | Primary key |
| `admin_id` | Foreign key to `admins` |
| `token_hash` | Store the hash, email the raw token |
| `expires_at` | Short lifetime |
| `used_at` | Null until the reset is consumed |

### `settings`

Key-value rows. The UI maps them by `settingCategory` and `settingKey`.

| Column | Notes |
| --- | --- |
| `id` | Primary key |
| `setting_category` | `money_format_settings`, `company_settings`, `app_settings`, `finance_settings` |
| `setting_key` | Unique |
| `setting_value` | Text. Numbers and booleans are stored as text and returned in the type the UI already uses |

Seed from `frontend/src/data/dummyData.js`, then add the keys the settings forms edit that the dummy file does not list:

| Category | Keys |
| --- | --- |
| `money_format_settings` | `default_currency_code` (`PKR`), `currency_code` (`PKR`), `currency_name`, `currency_symbol` (`Rs`), `currency_position` (`before`), `decimal_sep`, `thousand_sep`, `cent_precision` (`2`), `zero_format` (`false`) |
| `company_settings` | `company_name`, `company_address`, `company_state`, `company_country`, `company_email`, `company_phone`, `company_website`, `company_tax_number`, `company_vat_number`, `company_reg_number`, `company_logo` |
| `app_settings` | `idurar_app_date_format` (`DD/MM/YYYY`), `idurar_app_company_email` |
| `finance_settings` | `last_invoice_number` (`1004`), `last_quote_number` (`503`), `last_payment_number` (`2003`) |

`cent_precision` is returned as a number. `zero_format` is returned as a boolean. `useMoney` reads `currency_code`, so that key must exist next to `default_currency_code`.

### `clients`

| Column | JSON |
| --- | --- |
| `id` | `_id` |
| `name` | `name` |
| `country` | `country` |
| `address` | `address` |
| `phone` | `phone` |
| `email` | `email` |
| `removed` | Hidden from lists |

### `taxes`

| Column | JSON |
| --- | --- |
| `id` | `_id` |
| `tax_name` | `taxName` |
| `tax_value` | `taxValue` |
| `is_default` | `isDefault` |
| `enabled` | `enabled` |
| `removed` | Hidden from lists |

Only one row may have `is_default = 1`. Setting a new default clears the previous one.

### `payment_modes`

| Column | JSON |
| --- | --- |
| `id` | `_id` |
| `name` | `name` |
| `description` | `description` |
| `is_default` | `isDefault` |
| `enabled` | `enabled` |
| `removed` | Hidden from lists |

Same single-default rule as taxes.

### `invoices` and `invoice_items`

Invoice header:

| Column | JSON |
| --- | --- |
| `id` | `_id` |
| `client_id` | Expanded to `client` |
| `number`, `year` | Unique together |
| `date`, `expired_date` | `date`, `expiredDate` |
| `currency` | From `default_currency_code` when the form omits it |
| `status` | `draft`, `pending`, `sent`, `paid`, `overdue` |
| `payment_status` | `paymentStatus`: `unpaid`, `partially`, `paid` |
| `notes` | `notes` |
| `sub_total`, `tax_rate`, `tax_total`, `total` | `subTotal`, `taxRate`, `taxTotal`, `total` |
| `discount` | Default `0`. The record-payment screen subtracts it |
| `credit` | Default `0`. Sum of payments against this invoice |
| `removed` | Hidden from lists |

The create form does not send totals. On create and update the API sets:

- each item `total = quantity * price`
- `sub_total` = sum of item totals
- `tax_total` = `sub_total * (tax_rate / 100)`
- `total` = `sub_total + tax_total`
- `currency` from settings when missing
- `credit = 0` and `payment_status = unpaid` on create

Item rows:

| Column | JSON |
| --- | --- |
| `id` | `_id` |
| `invoice_id` | Parent |
| `item_name` | `itemName` |
| `description` | `description` |
| `price`, `quantity`, `total` | Same names |

Update replaces the item rows. The update payload omits item ids.

### `quotes` and `quote_items`

Same columns as invoices and invoice items, with two differences:

- No `payment_status` and no `credit`
- `status` is `draft`, `pending`, `sent`, `accepted`, or `declined`

`quote_items` uses `quote_id`. Unique `(number, year)`.

Optional later column: `invoices.quote_id`, so converting a quote twice can be refused. The first version can convert without that column.

### `payments`

| Column | JSON |
| --- | --- |
| `id` | `_id` |
| `number` | `number` |
| `client_id` | Expanded to `client` |
| `invoice_id` | Expanded to `invoice` |
| `payment_mode_id` | Expanded to `paymentMode` |
| `date` | `date` |
| `amount` | `amount` |
| `currency` | Copied from the invoice when the form omits it |
| `ref` | `ref` |
| `description` | `description` |
| `removed` | Hidden from lists |

Do not store `subTotal`, `total`, or `credit` on the payment row. Those live on the invoice and change when another payment is recorded. The payment read screen still expects them on the payment object, so the read and list JSON copies `subTotal`, `total`, `credit`, and `currency` from the invoice onto the payment result. The nested `invoice` still includes `number`, `year`, and `items`.

Recording a payment:

1. Insert the payment.
2. Add `amount` to `invoices.credit`.
3. Set `payment_status` to `unpaid` when credit is `0`, `paid` when credit is greater than or equal to `total - discount`, otherwise `partially`.

### `uploads`

| Column | Notes |
| --- | --- |
| `id` | Primary key |
| `model` | `admin` or `setting` |
| `model_id` | Admin id, or the setting row id |
| `file_name` | Stored name |
| `path` | Under `backend/public/uploads/` |

`admins.photo` and the `company_logo` setting store the public file name the UI prefixes with `FILE_BASE_URL`.

## Seed

Load the rows already in `frontend/src/data/dummyData.js`:

- 4 clients
- 3 taxes and 3 payment modes
- 4 invoices with their items
- 3 quotes with their items
- 3 payments
- Settings, currency PKR
- Admin `admin@admin.com` / `admin123`

Keep dummy ids only as a guide. New integer ids are fine. Nested client, invoice, and payment mode links must still resolve after the seed.

## HTTP routes

Match the method names in `frontend/src/request/request.js`. When the frontend is reconnected, those methods become these paths.

CRUD for `client`, `invoice`, `quote`, `payment`, `paymentMode`, `taxes`:

| Method | Path |
| --- | --- |
| POST | `/api/{entity}/create` |
| GET | `/api/{entity}/read/{id}` |
| PATCH | `/api/{entity}/update/{id}` |
| DELETE | `/api/{entity}/delete/{id}` |
| GET | `/api/{entity}/list?page=&items=` |
| GET | `/api/{entity}/listAll` |
| GET | `/api/{entity}/search?q=&fields=` |
| GET | `/api/{entity}/filter?filter=&equal=` |
| GET | `/api/{entity}/summary` |

`list` returns `pagination.page` and `pagination.count`. The Redux list action reads those two fields. `filter` is how invoice, quote, and payment tables limit by client: `filter` is the related entity name and `equal` is the client `_id`.

Auth and profile:

| Method | Path |
| --- | --- |
| POST | `/api/login` |
| POST | `/api/logout` |
| POST | `/api/forgetpassword` |
| POST | `/api/resetpassword` |
| PATCH | `/api/admin/profile/update` |
| PATCH | `/api/admin/profile/password` |

Login accepts `email` and `password`. A success result the auth action stores is:

```json
{
  "success": true,
  "result": {
    "_id": "1",
    "name": "Admin",
    "surname": "User",
    "email": "admin@admin.com",
    "photo": null,
    "token": "<jwt>"
  }
}
```

Profile update is multipart and may include `file`. Password update reads `password` from `frontend/src/modules/ProfileModule/components/PasswordModal.jsx`.

Settings:

| Method | Path |
| --- | --- |
| GET | `/api/setting/listAll` |
| PATCH | `/api/setting/updateBySettingKey/{key}` |
| PATCH | `/api/setting/updateManySetting` |
| POST | `/api/setting/upload/{settingKey}` |

`listAll` returns an array of `{ settingCategory, settingKey, settingValue }`. `updateManySetting` receives `{ settings: [{ settingKey, settingValue }] }`. Logo upload uses setting key `company_logo`.

Finance rules:

- Creating an invoice, quote, or payment advances `last_invoice_number`, `last_quote_number`, or `last_payment_number` when the saved number is higher than the stored one.
- Recording a payment updates invoice `credit` and `paymentStatus` as described above.
- `GET /api/quote/convert/{id}` copies the quote into a new invoice with `status = pending`, `paymentStatus = unpaid`, and `credit = 0`, and advances `last_invoice_number`.

Summary, filtered by the `currency` query when the entity has a currency:

| Entity | `result` |
| --- | --- |
| `invoice`, `quote` | `total` (this month), `total_undue`, `performance[]` of `{ status, percentage }` |
| `payment` | `total` (this month), `total_undue` (`0`), `performance` (`[]`) |
| `client` | `total`, `active`, `new` |

`total_undue` for invoices is the sum of `total - discount - credit` where `payment_status` is not `paid`. Dashboard cards read `invoiceResult.total`, `invoiceResult.total_undue`, `result.performance`, `clientResult.active`, and `clientResult.new`.

PDF and mail, after CRUD works:

| Method | Path |
| --- | --- |
| GET | `/download/{invoice\|quote\|payment}/{name}.pdf` |
| POST | `/api/{entity}/mail` |

Mail body is `{ "id": "<_id>" }`. Send with PHPMailer using the company email in settings. PDF uses Dompdf and the company settings plus the same fields the read screen shows.

CORS allows `http://localhost:3000`. Authenticated routes require the Bearer token. Login, forget-password, and reset-password stay public.

## Frontend reconnect

Do this only after the API answers. Until then the UI keeps using `dummyData.js`.

1. In `frontend/src/config/serverApiConfig.js`, set `API_BASE_URL` to `http://localhost:8888/api/`, `DOWNLOAD_BASE_URL` and `FILE_BASE_URL` to `http://localhost:8888/`, and keep `ACCESS_TOKEN_NAME` as `x-auth-token`.
2. Restore axios in `frontend/src/request/request.js` so each method hits the paths in the table above, and send `Authorization: Bearer <token>`.
3. Restore `frontend/src/auth/auth.service.js` so login, logout, forget-password, and reset-password call the API.
4. Set `isLoggedIn` back to `false` in `frontend/src/redux/auth/reducer.js` and `frontend/src/redux/store.js` so the login screen is the gate again.
5. Point the PDF buttons in the invoice, quote, and payment read views at `DOWNLOAD_BASE_URL` again.
6. Optionally restore the Vite `/api` proxy in `frontend/vite.config.js`.

`frontend/src/data/dummyData.js` stays the seed checklist until the SQL seed matches it. After the API is wired, the screens must keep their data across a refresh.

## Build order

1. Project layout, `.env` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `JWT_SECRET`, `PORT=8888`), PDO, CORS, and the JSON helper.
2. Schema and seed.
3. JWT login and logout.
4. Settings `listAll` and update. The ERP shell does not render until this succeeds.
5. Client, tax, and payment mode CRUD, including the single-default rule.
6. Invoice, quote, and payment CRUD, credit updates, and quote-to-invoice convert.
7. Dashboard summaries.
8. Uploads, PDF, and mail.
9. Wire the React client off the local stubs and confirm each route against a real login.

## Done when

- `backend/` is a PHP app on port 8888 with MySQL, and the old `backend/` Node tree is not restored.
- Login with `admin@admin.com` / `admin123` returns a token.
- Settings load and the dashboard, customer, invoice, quote, payment, tax, and payment mode screens show the seed.
- Create, update, and delete survive a browser refresh.
- Recording a payment changes the invoice `credit` and `paymentStatus`.
- Converting a quote creates an invoice.
- PDF download and email send work, or stay explicitly disabled until SMTP and Dompdf are configured.
