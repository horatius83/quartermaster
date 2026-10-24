# Quartermaster

A small web app for running a tabletop RPG's economy. The DM keeps every player's pack and purse, opens shops with their own stock, and approves what players put in their carts.

- **DM:** party dashboard, edit any character's inventory and coin, manage stores and who can shop in them, keep an item catalog, approve or decline carts, and review the history of every pack and purse.
- **Players:** browse the shops the DM has opened to them, fill a cart, send it for approval, and see their own pack and its history.

Plain PHP (8.1+) and MySQL/MariaDB. No framework and no Composer, so it runs on shared hosting such as Hostinger. [htmx](https://htmx.org) handles the in-page updates (quantity steppers, filters, cart, approvals) and is loaded from a CDN.

## How money works

Everything is stored in copper pieces (1 gp = 10 sp = 100 cp) and shown as gp/sp/cp. Prices can be typed as `15 gp`, `5 sp`, `1 gp 5 sp`, or a bare number, which counts as gp.

Coin only changes hands when the DM approves a cart. Approving a cart:

1. checks that the purse and the store's stock cover the cart,
2. takes the coin,
3. lowers the stock (a blank stock count means unlimited),
4. stacks the items into the character's pack.

All four steps happen in one transaction.

## Pack history

Every change to a pack or purse is written to `inventory_log`: items the DM gives, − / + adjustments, removals, moves between characters (one entry on each side), cart purchases, and purse edits. Each entry records who made it and when. Several clicks in the same direction on the same stack, within five minutes of the first, are folded into one entry; a − after a + starts a new one, so nothing is erased. Entries keep the character's name, so the history stays readable after a character leaves the party.

The DM sees it under *Pack history*, for the whole party or one character. Players see only their own, under *History*.

### Upgrading an existing install

Databases created before pack history existed get the new table automatically on the first request after you deploy (the app runs `app/sql/upgrade-*.sql` when the table is missing). If your database user can't create tables, import `app/sql/upgrade-001-inventory-log.mysql.sql` yourself through phpMyAdmin; it is safe to run again.

## Deploying to Hostinger

1. **Create the database.** In hPanel go to *Databases → MySQL Databases* and create a database and user. Note the database name, username and password (Hostinger prefixes them with something like `u123456789_`).
2. **Load the schema.** Open phpMyAdmin for that database, choose *Import*, and upload `app/sql/schema.mysql.sql`.
3. **Upload the code.** Use either option.
   - **Git (simplest):** in hPanel use *Advanced → Git* to deploy this repository into `public_html`. The root `.htaccess` sends every request into `public/`, so `app/` and `.git` are never served.
   - **Manual (tidiest):** put the `app/` folder in your home directory, next to `public_html`, and put the *contents* of `public/` into `public_html`.
4. **Configure.** In *File Manager*, copy `app/config.example.php` to `app/config.php` and fill in the database details and your campaign name. `config.php` is git-ignored, so redeploys won't overwrite it.
5. **PHP version.** Use PHP 8.1 or newer under *Advanced → PHP Configuration*. Make sure `pdo_mysql` is enabled; it is by default.
6. **Create the DM account.** Visit your site. The first visit goes to `/setup`, where you create the DM login. After that, add players under *Party & logins*.

Use HTTPS, which Hostinger provides for free: session cookies are marked secure automatically when the site is served over HTTPS.

## Running locally

You don't need MySQL locally. Set `'driver' => 'sqlite'` in `app/config.php`, then:

```bash
cp app/config.example.php app/config.php   # then set driver => 'sqlite'
php scripts/seed-demo.php                  # optional: sample party, stores and catalog
php -S localhost:8000 -t public scripts/dev-router.php
```

Open http://localhost:8000. The demo logins are `dm` / `dungeon123` and players `bryn`, `tamsin`, `oskar`, `wren` / `adventure`. The seed script also works against MySQL, but only on an empty database.

## End-to-end tests

[Playwright](https://playwright.dev) drives the app in Chromium and Firefox to catch regressions: signing in and who can see what, the DM's pack editing, buying through a cart, and pack history. Node is only needed for the tests; the app itself doesn't use it.

```bash
npm install
npx playwright install chromium firefox
npm test
```

`npm run test:chromium` runs just Chromium. `npm run test:docker` runs both browsers inside Microsoft's Playwright Linux image with PHP added (`tests/e2e/Dockerfile`), so you only need Docker and `npm install`: no PHP or browsers on the host. Use it where a browser won't run locally: Playwright's Firefox build fails to start on some Windows 11 machines with a "side-by-side configuration" error. Arguments after `--` go to Playwright, e.g. `npm run test:docker -- --project=firefox`. The first run downloads the image (about 2 GB).

The tests start PHP's built-in server on port 8099 with `tests/e2e/config.php` (picked via the `QM_CONFIG` environment variable), which uses its own SQLite file in the system temp folder. Every test starts from a fresh demo seed, so your `app/config.php` and database are never touched.

PHP is found in this order: the `PHP` environment variable (the path to `php`), `php` on your PATH, then, on Windows, `wsl php`, so PHP installed inside WSL works with Node on Windows. `npm run test:ui` opens Playwright's interactive runner, and `npm run test:report` shows the last HTML report.

## Project layout

```
public/            web root: index.php (routes), .htaccess, assets/ (CSS, JS)
app/bootstrap.php  loads config, libraries and handlers
app/lib/           db (PDO helpers), auth (sessions, CSRF), money, router, shared queries
app/handlers/      one file per area: auth, party, inventory, stores, catalog, carts, player
app/views/         PHP templates; views/partials/ are the fragments htmx swaps in
app/sql/           schema.mysql.sql (production), schema.sqlite.sql (local dev), upgrade-*.sql for existing databases
scripts/           seed-demo.php, dev-router.php
tests/e2e/         Playwright tests, their config.php and reset.php; playwright.config.ts sits at the root
```

## How htmx is used

Every page is rendered on the server. `<body hx-boost="true">` turns ordinary links and forms into partial page swaps. Interactive bits post to small endpoints that return just the HTML fragment to replace:

| Interaction | Request | Swaps |
| --- | --- | --- |
| Pack quantity − / + | `POST /dm/inv/{id}/qty` | that table row, plus the carried-weight line (out-of-band) |
| Category filter chips | `GET /dm/inventory/{id}?cat=…` | the table body; the URL is pushed to history |
| Give item / stock item | `POST …/give`, `…/stock` | the table body; the form resets |
| Item name suggestions | `GET /dm/catalog/suggest` | the `<datalist>` options |
| Inline price, stock and catalog edits | `POST` on `change` | that row |
| Add to cart, ± in cart, submit, withdraw | `POST /shop/…` | the cart panel |
| Pack history “Show older” | `GET /dm/history?before=…`, `GET /history?before=…` | replaces the button row with the next 50 entries |
| Approve / decline / adjust cart | `POST /dm/carts/…` | that cart card, plus the sidebar badge |

Messages are sent as an `HX-Trigger: {"qm-toast": …}` header and shown by `public/assets/app.js`. Errors come back as a 200 status with `HX-Reswap: none` and an error toast, because htmx won't swap 4xx responses. The CSRF token is sent on every htmx request via `hx-headers` on `<body>`.

## Security notes

- Passwords are hashed with `password_hash`.
- Session IDs are regenerated on login.
- Every POST is CSRF-checked.
- All SQL uses prepared statements.
- All output is escaped with `e()`.
- Players can only see their own pack, history and carts, and only the stores the DM has opened to them.
