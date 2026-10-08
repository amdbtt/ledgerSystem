# Removing the backend

This guide removes the Express and MongoDB API and leaves a React UI that still starts on port 3000. Deleting `backend/` by itself is not enough. Login, lists, settings, PDFs, and uploads all call `http://localhost:8888`. Follow the steps in order.

The app has no root `package.json` and no Docker Compose. The API and the UI are separate packages.

| Piece | Location | Stack | Port |
| --- | --- | --- | --- |
| API | `backend/` | Node, Express, MongoDB, JWT | 8888 |
| UI | `frontend/` | Vite, React, Ant Design, Redux | 3000 |

## What stays

Keep these. They are the interface.

- `frontend/` pages, layouts, forms, components, locales, and CSS
- `frontend/src/router/routes.jsx` (Dashboard, Customer, Invoice, Quote, Payment, Taxes, Payment Mode, Settings, Profile, About)
- `doc/`, `features/`, `LICENSE`, and the other root markdown files
- Root assets `idurar-crm-erp.svg` and `image.png`

## What stops working

After these steps the screens still open. They no longer talk to a server.

- Real login, logout, forget-password, and reset-password
- Saved customers, invoices, quotes, payments, taxes, and payment modes
- Company settings, logo upload, and profile photo
- PDF download and email send
- MongoDB, JWT, file uploads, and the Resend mailer

---

## Step 1. Stop the API

Stop the process that is running `npm run dev` inside `backend/` (port 8888).

Do not start MongoDB. Do not run these scripts from `backend/package.json`:

- `npm start`
- `npm run dev`
- `npm run setup`
- `npm run reset`
- `npm run upgrade`

`npm run setup` seeds an admin (`admin@admin.com` / `admin123`) and default settings. A UI-only app does not need that seed.

## Step 2. Delete the backend tree

Delete the whole `backend/` directory, including `node_modules`, `package.json`, `package-lock.json`, and `backend/.env`.

That directory holds:

| Path | Role |
| --- | --- |
| `backend/src/server.js` | Process entry. Listens on `PORT` or 8888 |
| `backend/src/app.js` | Mounts `/api`, `/download`, and `/public` |
| `backend/src/models/appModels/` | Client, Invoice, Quote, Payment, PaymentMode, Taxes |
| `backend/src/models/coreModels/` | Admin, AdminPassword, Setting, Upload |
| `backend/src/controllers/` | CRUD, auth, settings, PDF |
| `backend/src/routes/` | `coreAuth`, `coreApi`, `appApi`, download, public files |
| `backend/src/pdf/` | Pug templates for invoice, quote, payment, offer |
| `backend/src/emailTemplate/` | HTML mail builders |
| `backend/src/setup/` | Database seed and reset |
| `backend/src/public/uploads/` | Stored admin and setting images |
| `backend/.env` | `DATABASE`, `JWT_SECRET`, `PORT=8888` |

Leave `frontend/`, `doc/`, `features/`, and the root markdown files in place.

`backend/.env` contains the database URI and JWT secret. Delete the file with the folder. Do not copy those values into the frontend.

## Step 3. Drop backend env, proxy, and install docs

### Frontend environment

In `frontend/.env`, remove:

```
VITE_FILE_BASE_URL='http://localhost:8888/'
VITE_BACKEND_SERVER="http://localhost:8888/"
```

`PROD=false` can stay. It only toggles the Vite dev flag used by the Redux store.

Delete `frontend/temp.env`. It only documents a remote `VITE_BACKEND_SERVER`.

### Frontend scripts

In `frontend/package.json`, remove the `dev:remote` script:

```json
"dev:remote": "cross-env VITE_DEV_REMOTE=remote npm run dev"
```

`cross-env` is only used by that script. Remove it from `dependencies` if nothing else imports it.

### Vite proxy

In `frontend/vite.config.js`, delete the `/api` proxy and the `proxy_url` block. The dev server should only set the React plugin, the `@` alias, and port 3000:

```js
server: {
  port: 3000,
},
```

`loadEnv` can go too once `VITE_BACKEND_SERVER` is unused.

### Docs

Rewrite the backend half of the install docs so a new clone only starts the UI.

In `README.md`, the "Getting started" list (steps 2 through 7) points at MongoDB, `backend/.env`, `npm run setup`, and the API server. Replace that list with:

1. Clone the repository (`INSTALLATION-INSTRUCTIONS.md` step 1).
2. Install frontend dependencies.
3. Run the frontend server.

In `INSTALLATION-INSTRUCTIONS.md`, delete steps 2 through 7 (MongoDB account, `backend/.env`, Mongo URI, `cd backend`, `npm install`, `npm run setup`, `npm run dev`). Keep the frontend install and `npm run dev` steps, and state that the site is `http://localhost:3000`.

## Step 4. Bypass the login gate

`frontend/src/apps/IdurarOs.jsx` reads `isLoggedIn` from Redux. When it is false, the app renders `AuthRouter` (the login screens) and never mounts `ErpApp`.

Two places default that flag to false:

- `frontend/src/redux/auth/reducer.js` — `INITIAL_STATE.isLoggedIn`
- `frontend/src/redux/store.js` — `AUTH_INITIAL_STATE.isLoggedIn`

Set both to a signed-in shell user:

```js
const AUTH_INITIAL_STATE = {
  current: {
    name: 'Admin',
    surname: 'User',
    email: 'admin@localhost',
    photo: null,
    token: null,
  },
  isLoggedIn: true,
  isLoading: false,
  isSuccess: true,
};
```

Use the same object as `INITIAL_STATE` in the auth reducer.

`frontend/src/redux/store.js` prefers `localStorage` key `auth` over that default (`storePersist.get('auth')`). After a previous real login, that key still exists and will put the app back behind the login screen. Clear it once in the browser:

```js
localStorage.removeItem('auth');
localStorage.removeItem('isLogout');
```

Or call `storePersist.remove('auth')` from `frontend/src/redux/storePersist.js` before the store reads it.

Logout (`frontend/src/pages/Logout.jsx` and `LOGOUT_SUCCESS` in the auth reducer) sets `isLoggedIn` back to false. For a UI-only shell, make logout navigate to `/` and leave `isLoggedIn` true, so the ERP layout stays on screen.

## Step 5. Replace network calls with local results

Every authenticated screen goes through `frontend/src/request/request.js`. It sets `axios.defaults.baseURL` from `API_BASE_URL` in `frontend/src/config/serverApiConfig.js` (`http://localhost:8888/api/` in dev) and sends `Authorization: Bearer <token>`.

`frontend/src/apps/ErpApp.jsx` dispatches `settingsAction.list({ entity: 'setting' })` on startup and renders `PageLoader` until `isSuccess` is true. If `request.listAll` fails, the layout never appears. The stub has to return `success: true`.

Replace the axios body of `frontend/src/request/request.js` with local resolvers. Keep every method name. Callers are:

- `frontend/src/redux/crud/actions.js`
- `frontend/src/redux/erp/actions.js`
- `frontend/src/redux/adavancedCrud/actions.js`
- `frontend/src/redux/settings/actions.js`
- `frontend/src/redux/auth/actions.js` (profile update via `updateAndUpload`)
- `frontend/src/modules/DashboardModule/index.jsx`
- `frontend/src/modules/DashboardModule/components/RecentTable/index.jsx`
- `frontend/src/components/SelectAsync/index.jsx`
- `frontend/src/components/AutoCompleteAsync/index.jsx`
- `frontend/src/components/MultiStepSelectAsync/index.jsx`
- `frontend/src/pages/ForgetPassword.jsx`
- `frontend/src/hooks/useMail.jsx`

### Response shapes the UI already expects

`list` and `filter` are read as:

```js
{
  success: true,
  result: [],
  pagination: { page: 1, count: 0 },
}
```

`listAll` for entity `setting` is mapped in `frontend/src/redux/settings/actions.js` with `datas.map(...)`. `result` must be an array of `{ settingCategory, settingKey, settingValue }`. An empty array loads the shell. Include money-format rows so dashboard cards can render amounts:

```js
const SETTINGS = [
  { settingCategory: 'money_format_settings', settingKey: 'default_currency_code', settingValue: 'USD' },
  { settingCategory: 'money_format_settings', settingKey: 'currency_symbol', settingValue: '$' },
  { settingCategory: 'money_format_settings', settingKey: 'currency_position', settingValue: 'before' },
  { settingCategory: 'money_format_settings', settingKey: 'decimal_sep', settingValue: '.' },
  { settingCategory: 'money_format_settings', settingKey: 'thousand_sep', settingValue: ',' },
  { settingCategory: 'money_format_settings', settingKey: 'cent_precision', settingValue: 2 },
  { settingCategory: 'money_format_settings', settingKey: 'zero_format', settingValue: false },
  { settingCategory: 'company_settings', settingKey: 'company_name', settingValue: 'UI Preview' },
];
```

Return `{ success: true, result: SETTINGS }` when `entity === 'setting'`. Return `{ success: true, result: [] }` for every other `listAll`.

`read`, `create`, `update`, `delete`, `search`, `post`, `get`, `patch`, `upload`, `createAndUpload`, `updateAndUpload`, `mail`, and `convert` should resolve:

```js
{ success: true, result: {} }
```

`summary` feeds the dashboard cards. Return zeros so `invoiceResult.total`, `total_undue`, `performance`, and `clientResult.active` / `new` stay defined:

```js
{
  success: true,
  result: {
    total: 0,
    total_undue: 0,
    performance: [],
    active: 0,
    new: 0,
  },
}
```

`source` can return `{ token: null, cancel() {} }`. Nothing should import `axios` from this file after the change.

Do the same in `frontend/src/auth/auth.service.js`. `login`, `register`, `verify`, `resetpassword`, and `logout` must resolve locally and must not call `API_BASE_URL`. A `login` result the auth action accepts is:

```js
{
  success: true,
  result: {
    name: 'Admin',
    surname: 'User',
    email: 'admin@localhost',
    photo: null,
    token: null,
  },
}
```

`frontend/src/request/checkImage.js` also uses axios. Make it resolve `{ success: false }` so missing logos do not hit the network.

In `frontend/src/config/serverApiConfig.js`, set the backend URLs to empty strings so a leftover import cannot point at port 8888:

```js
export const API_BASE_URL = '';
export const BASE_URL = '';
export const DOWNLOAD_BASE_URL = '';
export const FILE_BASE_URL = '';
export const ACCESS_TOKEN_NAME = 'x-auth-token';
export const WEBSITE_URL = 'http://localhost:3000/';
```

Profile password updates go through `frontend/src/modules/ProfileModule/components/PasswordModal.jsx` and the same `request` helpers. They succeed locally and change nothing on a server.

## Step 6. Remove file and PDF URLs

These screens open a backend URL in a new tab. Point the handlers at a no-op so the buttons stay on the page and do not navigate to `localhost:8888`.

| File | What it opens |
| --- | --- |
| `frontend/src/modules/DashboardModule/components/RecentTable/index.jsx` | `DOWNLOAD_BASE_URL` + PDF |
| `frontend/src/modules/ErpPanelModule/DataTable.jsx` | `DOWNLOAD_BASE_URL` + PDF |
| `frontend/src/modules/ErpPanelModule/ReadItem.jsx` | `DOWNLOAD_BASE_URL` + PDF, and `useMail` |
| `frontend/src/modules/PaymentModule/ReadPaymentModule/components/ReadItem.jsx` | `DOWNLOAD_BASE_URL` + PDF, and `useMail` |

`frontend/src/hooks/useMail.jsx` dispatches `erp.mail`, which will already no-op once `request.mail` returns `{ success: true }`.

Avatar and logo images are prefixed with `FILE_BASE_URL`:

| File | Usage |
| --- | --- |
| `frontend/src/apps/Header/HeaderContainer.jsx` | `currentAdmin.photo` |
| `frontend/src/modules/ProfileModule/components/AdminInfo.jsx` | `currentAdmin.photo` |

With `FILE_BASE_URL` set to `''` and `photo: null` on the shell user, those `<img>` tags stay empty. Upload actions call `request.upload` / `updateAndUpload` and should not post a file.

## Step 7. Run only the UI

From the repo root:

```bash
cd frontend
npm install
npm run dev
```

Open `http://localhost:3000`.

Confirm each route renders with empty tables (no spinner that never ends, no request to port 8888):

| Route | Screen |
| --- | --- |
| `/` | Dashboard |
| `/customer` | Customers |
| `/invoice` | Invoices |
| `/quote` | Quotes |
| `/payment` | Payments |
| `/taxes` | Taxes |
| `/payment/mode` | Payment modes |
| `/settings` | Settings |
| `/profile` | Profile |
| `/about` | About |

In the browser network panel, traffic should be the Vite app only. There should be no calls to `/api`, `/download`, or `/public` on port 8888.

Create, edit, and delete still show the existing forms. Values disappear on refresh because nothing is stored.

## Checklist

- `backend/` is gone, including `.env`
- Nothing listens on port 8888
- `frontend/.env` has no `VITE_BACKEND_SERVER` or `VITE_FILE_BASE_URL`
- `frontend/vite.config.js` has no `/api` proxy
- `README.md` and `INSTALLATION-INSTRUCTIONS.md` describe `cd frontend && npm install && npm run dev` only
- Opening `http://localhost:3000` shows the ERP shell without a login request
- Dashboard, Customer, Invoice, Quote, Payment, Taxes, Payment Mode, Settings, and Profile render with empty data
