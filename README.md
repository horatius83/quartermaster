# Quartermaster

A small web app for running a tabletop RPG's economy. The DM keeps every player's pack and purse, opens shops with their own stock, and approves what players put in their carts.

- **DM:** run several campaigns side by side, each with its own party, stores and history; party dashboard, edit any character's inventory and coin, manage stores and who can shop in them, keep an item catalog per system, approve or decline carts, accept, counter or reject players' offers to sell, and review the history of every pack and purse.
- **Players:** browse the shops the DM has opened to them, fill a cart, send it for approval, offer things from their pack for sale, and see their own pack and its history.
- **Admin:** load each system's SRD gear and magic items into its catalog from the dashboard, add and delete DMs and other logins, and read the account log of every login added or deleted.

Plain PHP (8.1+) and MySQL/MariaDB. No framework and no Composer, so it runs on shared hosting such as Hostinger. [htmx](https://htmx.org) handles the in-page updates (quantity steppers, filters, cart, approvals). htmx and the fonts are served from `public/assets`, so pages load nothing from other sites. Their licences are alongside them, and they need to stay deployed with them.

## How money works

Everything is stored in copper pieces (1 gp = 10 sp = 100 cp) and shown as gp/sp/cp. Prices can be typed as `15 gp`, `5 sp`, `1 gp 5 sp`, or a bare number, which counts as gp.

Coin only changes hands when the DM approves a cart. Approving a cart:

1. checks that the purse and the store's stock cover the cart,
2. takes the coin,
3. lowers the stock (a blank stock count means unlimited),
4. stacks the items into the character's pack.

All four steps happen in one transaction.

## Selling from a pack

Players sell from *My pack*: *Sell…* on a row asks how many and an asking price for the lot (the catalog's price for one is shown as a guide), with an optional note. The offer goes to the DM under *Sale offers*, with a badge in the sidebar, and nothing leaves the pack until it's sold. Items already on offer can't be offered again, and a player can withdraw an offer until the DM answers it. The DM can:

- **accept** the asking price, which takes the items from the pack and pays the coin into the purse, in one transaction. This works after a counter-offer too, for a DM who changes their mind;
- **counter-offer** another price, with a note. The offer goes back to the player, whose *My pack* link shows a badge until they answer. They accept (and the sale goes through at the DM's price) or declines (and keeps the items). Until they answer, the DM can change the counter-offer or take it back. If it changes while the player has the old one open, their *Accept* is refused rather than taking the new price unseen;
- **reject** it, with a note the player sees. The items stay in the pack.

A sale only goes through if the pack still holds the items, so a DM who has meanwhile removed or moved them is told to reject the offer instead. Sales are written to the pack history as *Sold*, one entry for the items and one for the coin, like purchases. Sold items leave the game; they don't go onto a shop's shelves. An open offer keeps its item in the catalog; once every offer for an item is settled, deleting the item from the catalog deletes those offers too, and the pack history still shows the sales.

## Campaigns

One DM login can run several campaigns, and each DM sees only their own. Each campaign has its own party, stores, carts and pack history; the item catalog is shared by every campaign of the same system and by every DM, so each can stock the same gear at its own prices (see *Systems and the SRD items*). The DM picks the campaign at the top of the sidebar, and every DM page shows only that campaign. Start, rename and delete campaigns under *Campaigns*. A campaign can only be deleted once its characters and stores are gone.

Each player login belongs to one character, so it sees only that character's campaign. Someone playing in two campaigns gets a login for each.

## Systems and the SRD items

Each campaign plays one system, 5e, 3.5e, Pathfinder 1e or Pathfinder 2e. It's chosen when the campaign starts and can't be changed; a DM with no campaign yet is asked to start one before anything else. To fix a wrong choice, start another campaign and delete the empty one. (Campaigns from before systems can set theirs once: see upgrade 007.) To add a system, add it to `CAMPAIGN_RULESETS` in `app/lib/repo.php`.

Each catalog item belongs to one system, and its name only has to be unique within that system (the unique key is system + name), so the 5e and 3.5e Longswords are separate items with their own weight, price and description. A campaign only sees its own system's items and tags, in the catalog and in the give and stock forms, and a name typed there that's new is added to that system. Campaigns of the same system share its items, including other DMs' campaigns. The catalog shows 50 items a page, so a newly added item may land on another page: search for it to find it.

The admin's *Dashboard* has a card for each system with two buttons. *Load 5e SRD gear* and *Load 3.5e SRD gear* add that system's weapons, armor, ammunition, gear and tools from `app/data/srd-5e.php` or `app/data/srd-3.5e.php`. *Load 5e SRD magic items* and *Load 3.5e SRD magic items* add its magic items from `app/data/srd-5e-magic.php` or `app/data/srd-3.5e-magic.php`, so a DM who wants plain shops can have the gear without them. Each skips any name that system already has, so a DM's own prices and descriptions are never overwritten. Each says whether its items are loaded, partly loaded (and how many loading would add) or not loaded, counted from the names in the catalog. Once every name is there, its button is disabled; if someone deletes or renames one of the items, the button comes back and loading restores it. Loading also takes a few sentences that earlier versions of the data had (each pointing to a table the catalog doesn't include, listed in `SRD_STALE_TEXT`) out of descriptions already in the catalog, changing nothing else in them; while any are left, the button stays enabled and the card says so. Until a system's catalog has none of its SRD gear, its DMs' catalog page points them to the admin. Mounts aren't included. Because weapons have no damage columns, damage goes in the description and properties in the tags. Pathfinder has no items to load, so DMs add their own.

Magic items go in the *Magic Item* category, one row for each version that has its own price (each +1, +2 and +3 weapon, each belt of giant strength, each bag of holding type), and the catalog's 1,000-character descriptions hold the SRD's rules text, shortened where it runs longer. Weights are the SRD's where it gives one; magic weapons and armor weigh what their base item does, so they're listed at 0.

- **5e:** SRD 5.1 gives magic items a rarity but no price, so each is priced by rarity: common 100 gp, uncommon 500 gp, rare 5,000 gp, very rare 50,000 gp, legendary 200,000 gp. The one exception is the potion of healing, which the SRD's gear table prices at 50 gp. The rarity, the kind (*wondrous*, *potion*, *ring*, *weapon* and so on) and *attunement*, where it's needed, are tags. The artifact is left out.
- **3.5e:** every item the SRD's magic item tables price, plus the potions, oils and wands it lists and a scroll for each spell level, at the SRD's prices. Where an item's own entry and its table disagree on the price (the helm of underwater action, the periapt of health, the druid's vestment, the assassin's dagger), the table's is used: the tables are sorted by price, and its price is the one that fits. Tags give the kind and the SRD table it's rolled on (*minor*, *medium*, *major*). *Armor, +1*, *Weapon, +1* and the like are priced with the masterwork cost but without the base item's, which the DM adds. Epic, cursed and intelligent items and artifacts are left out.

The 5e data comes from SRD 5.1 (CC BY 4.0) by way of the [5e-bits database](https://github.com/5e-bits/5e-database), and the 3.5e data from the System Reference Document (Open Game License 1.0a). Both require notices, which are in `public/assets/srd-notices.txt` and linked from the DM sidebar and the foot of every player page. Keep that file deployed with the data.

## Logins and the admin

There are three kinds of login. The **admin** manages logins under *Users*: it adds DMs (or more admins), deletes any login except its own and the last admin, and hands a campaign, party and all, from one DM to another. It doesn't run campaigns, so if you're also a DM you keep a separate DM login. **DMs** add their players under *Party & logins*, each with a character.

Deleting a player leaves their character in the party without a login. A DM can only be deleted once their campaigns have no characters or stores (hand a campaign to another DM to keep it); their empty campaigns are deleted with them.

Admins and DMs change their own password under *Change password*. The admin can also reset a DM's password from *Users*, and DMs reset their players' under *Party & logins*. A new password signs that login out everywhere else.

Wrong passwords slow guessing down, at sign-in and on *Change password* alike. After 5 wrong tries for one name from one address, or 20 from one address in all, that address waits 15 minutes; a session guessing on *Change password* is signed out. An IPv6 connection is handed a whole /64 of addresses, so those count as one address. Guesses spread over many addresses are held back too: after 50 wrong tries for one name from anywhere, nobody can sign in under that name for 15 minutes, right password or not. Someone already signed in isn't paused by other people's mistakes, at a shared address or anywhere else. Each pause is written to the account log.

If you put the site behind a proxy or CDN such as Cloudflare, every visitor's address looks like the proxy's, so one person's wrong passwords would pause everyone. Set `client_ip_header` in `config.php` to the header holding the real address (`'HTTP_CF_CONNECTING_IP'` for Cloudflare). Leave it unset otherwise, since visitors could fake that header.

Every login added or deleted, every password changed or reset, and every campaign handed over is written to `user_log` with who did it, by the admin or by a DM managing their party. The passwords themselves are never recorded. The admin reads it under *Account log*.

A new install has no admin at first: `/setup` makes the first DM. Until an admin exists, DMs see *Create the admin account* in the sidebar. Use it once to make the admin login, then sign in with it.

## Pack history

Every change to a pack or purse is written to `inventory_log`: items the DM gives, − / + adjustments, removals, moves between characters (one entry on each side), cart purchases, and purse edits. Each entry records who made it and when. Several clicks in the same direction on the same stack, within five minutes of the first, are folded into one entry; a − after a + starts a new one, so nothing is erased. Entries keep the character's name, so the history stays readable after a character leaves the party.

The DM sees it under *Pack history*, for the whole party or one character. Players see only their own, under *History*.

## Upgrading an existing install

Older databases are brought up to date automatically on the first request after you deploy: the app runs whichever `app/sql/upgrade-*.sql` files the database is missing. Only one request upgrades at a time, and if an upgrade is interrupted, the next request finishes it.

- **001, pack history:** adds the history table. Safe to run again.
- **002, campaigns:** adds campaigns and moves your existing party, stores and history into one campaign. It's named from `campaign_name` in your `config.php` if that's set, otherwise "My Campaign"; rename it under *Campaigns*. After that `campaign_name` is no longer used and you can delete it from `config.php`.
- **003, admin:** adds the admin login and the account log, and gives every campaign an owning DM. Existing campaigns go to your oldest DM login. Afterwards, sign in as a DM and use *Create the admin account*. If you had more than one DM login, sign in as the admin and use *Hand over* under *Users* to give each campaign back to the DM who runs it. If the database had no DM login at all, `/setup` opens again and the DM it makes takes every campaign.

- **004, sign-in:** lets a new password sign that login out everywhere else, and adds the table that limits wrong passwords. Everyone has to sign in again once after this upgrade.
- **005, item tags:** adds the table for the tags DMs put on catalog items. Safe to run again.
- **006, rulesets:** gives items and campaigns a system and makes item names unique per system. Everything existing starts as 5e.
- **007, system choice:** lets each existing campaign set its real system once, under *Campaigns*, which copies the items that campaign uses (in its packs, on its shelves, in its carts and its history) into that system and moves the campaign to the copies. Catalog items it doesn't use stay 5e only, so re-add any you want. The 5e originals stay for campaigns that really are 5e; if none are, delete the leftovers from a 5e campaign's catalog should you ever start one. A name the chosen system already has means that item, so a pack holding it takes on that item's weight and description.
- **008, sales:** adds the table for players' offers to sell from their packs. Safe to run again.

If your database user can't change tables, import the missing files yourself through phpMyAdmin, in order. Import 002, 003, 004, 006 and 007 only once each: unlike 001, 005 and 008, a second import stops at the first column that already exists. (When the app runs it, it skips the changes that are already there.)

## Deploying to Hostinger

1. **Create the database.** In hPanel go to *Databases → MySQL Databases* and create a database and user. Note the database name, username and password (Hostinger prefixes them with something like `u123456789_`).
2. **Load the schema.** Open phpMyAdmin for that database, choose *Import*, and upload `app/sql/schema.mysql.sql`.
3. **Upload the code.** Use either option.
   - **Git (simplest):** in hPanel use *Advanced → Git* to deploy this repository into `public_html`. The root `.htaccess` sends every request into `public/`, so `app/` and `.git` are never served.
   - **Manual (tidiest):** put the `app/` folder in your home directory, next to `public_html`, and put the *contents* of `public/` into `public_html`.
4. **Configure.** In *File Manager*, copy `app/config.example.php` to `app/config.php` and fill in the database details. `config.php` is git-ignored, so redeploys won't overwrite it.
5. **PHP version.** Use PHP 8.1 or newer under *Advanced → PHP Configuration*. Make sure `pdo_mysql` is enabled; it is by default.
6. **Create the DM account.** Visit your site. The first visit goes to `/setup`, where you create the DM login and name your first campaign. After that, add players under *Party & logins*, and use *Create the admin account* in the sidebar to make the admin login that adds more DMs.

Use HTTPS, which Hostinger provides for free: session cookies are marked secure automatically when the site is served over HTTPS.

## Running locally

You don't need MySQL locally. Set `'driver' => 'sqlite'` in `app/config.php`, then:

```bash
cp app/config.example.php app/config.php   # then set driver => 'sqlite'
php scripts/seed-demo.php                  # optional: sample party, stores and catalog
php -S localhost:8000 -t public scripts/dev-router.php
```

Open http://localhost:8000. The demo logins are `admin` / `quartermaster`, `dm` / `dungeon123` and players `bryn`, `tamsin`, `oskar`, `wren` / `adventure`. The demo has a second, smaller campaign with one player, `sela` / `adventure`, so you can try switching. The seed script also works against MySQL, but only on an empty database.

## End-to-end tests

[Playwright](https://playwright.dev) drives the app in Chromium and Firefox to catch regressions: signing in and who can see what, the DM's pack editing, buying through a cart, selling from a pack, pack history, and keeping campaigns apart. Node is only needed for the tests; the app itself doesn't use it.

```bash
npm install
npx playwright install chromium firefox
npm test
```

`npm run test:chromium` runs just Chromium. `npm run test:docker` runs both browsers inside Microsoft's Playwright Linux image with PHP added (`tests/e2e/Dockerfile`), so you only need Docker and `npm install`: no PHP or browsers on the host. Use it where a browser won't run locally: Playwright's Firefox build fails to start on some Windows 11 machines with a "side-by-side configuration" error. Arguments after `--` go to Playwright, e.g. `npm run test:docker -- --project=firefox`. The first run downloads the image (about 2 GB).

The tests start PHP's built-in server on port 8099 (`QM_E2E_PORT` moves it) with `tests/e2e/config.php` (picked via the `QM_CONFIG` environment variable), which uses its own SQLite files in `/dev/shm` (memory-backed storage, so writes don't wait on the disk), or in the system temp folder where there's no `/dev/shm`. Your `app/config.php` and database are never touched. The demo seed is built once per run, and every test starts from a fresh copy of it, made by the test server's `/__test/reset` route (`tests/e2e/router.php`). Four workers run tests side by side (`QM_E2E_WORKERS` changes how many), each with a database of its own (`tests/e2e/test-db.php`), named by the server's port as well, so runs on different ports never share files. Requests that don't name their worker's database are refused rather than given another's. The test config hashes passwords at bcrypt's lowest cost, since PHP's default takes about 0.2 s per hash. Outside CI, a server already listening on the port is reused; if it is one left over from an older checkout, without the test routes, the run stops straight away and says so.

Those tests all use SQLite. `npm run test:mysql` checks the item tags, rulesets and sales upgrades, the tags table, settling a sale and the SRD data against throwaway MySQL and MariaDB containers (`tests/mysql/`), and needs only Docker.

PHP is found in this order: the `PHP` environment variable (the path to `php`), `php` on your PATH, then, on Windows, `wsl php`, so PHP installed inside WSL works with Node on Windows. With WSL's default networking, Windows reaches that server through a relay, and the odd browser request can fail with `net::ERR_ADDRESS_IN_USE`. Setting `networkingMode=mirrored` under `[wsl2]` in `%USERPROFILE%\.wslconfig`, then running `wsl --shutdown`, removes the relay. `npm run test:ui` opens Playwright's interactive runner, and `npm run test:report` shows the last HTML report.

## Project layout

```
public/            web root: index.php (routes), .htaccess, assets/ (CSS, JS, htmx, fonts)
app/bootstrap.php  loads config, libraries and handlers
app/lib/           db (PDO helpers), auth (sessions, CSRF), money, router, shared queries
app/handlers/      one file per area: auth, campaigns, party, inventory, stores, catalog, carts, sales, player
app/views/         PHP templates; views/partials/ are the fragments htmx swaps in
app/sql/           schema.mysql.sql (production), schema.sqlite.sql (local dev), upgrade-*.sql for existing databases
app/data/          the SRD gear and magic items each system can load; their licence notices are in public/assets/srd-notices.txt
scripts/           seed-demo.php, dev-router.php
tests/e2e/         Playwright tests, their config.php and reset.php; playwright.config.ts sits at the root
tests/mysql/       checks of item tags, rulesets, sales and the SRD data against real MySQL and MariaDB (npm run test:mysql)
```

## How htmx is used

Every page is rendered on the server. `<body hx-boost="true">` turns ordinary links and forms into partial page swaps. Interactive bits post to small endpoints that return just the HTML fragment to replace:

| Interaction | Request | Swaps |
| --- | --- | --- |
| Pack quantity − / + | `POST /dm/inv/{id}/qty` | that table row, plus the carried-weight line (out-of-band) |
| Catalog pages | `GET /dm/catalog?page=…` | the table body and, out-of-band, the pager; the URL is pushed to history. Changing a filter goes back to page 1; saves and deletes stay on the page |
| Category filter chips | `GET /dm/inventory/{id}?cat=…` | the table body; the URL is pushed to history |
| Tag filter chips | `GET /shop/{id}?tag[]=…`, `GET /dm/catalog?tag[]=…` | the wares or the table body, showing items with every chosen tag; the URL is pushed to history |
| Give item / stock item | `POST …/give`, `…/stock` | the table body; the form resets |
| Item name suggestions | `GET /dm/catalog/suggest` | the `<datalist>` options |
| Inline price and stock edits | `POST` on `change` | that row |
| Inline catalog edits | `POST /dm/catalog/{id}` on `change` | that row, or the whole table if the edit takes it out of the filters; the tag filter fetches itself again if the tags in use changed |
| Add to cart, ± in cart, submit, withdraw | `POST /shop/…` | the cart panel |
| Pack history “Show older” | `GET /dm/history?before=…`, `GET /history?before=…` | replaces the button row with the next 50 entries |
| Approve / decline / adjust cart | `POST /dm/carts/…` | that cart card, plus the sidebar badge |
| Accept / counter / reject an offer to sell | `POST /dm/sales/…` | that offer's card, plus the sidebar badge |

A catalog row posts all its fields when one changes, so a row showing old values would save them over newer ones. Four things keep rows current: Back and Forward reload the page from the server (`hx-history="false"`), a row locks while it saves, all rows lock while the table redraws (`hx-disabled-elt`), and a redraw waits for saves in flight (`public/assets/app.js`). Don't use `hx-sync` with `queue` for this: htmx silently drops a queued request once the response before it has replaced its element. The shop also opts out of history snapshots, because they'd show an old cart and old stock counts.

Messages are sent as an `HX-Trigger: {"qm-toast": …}` header and shown by `public/assets/app.js`. Errors come back as a 200 status with `HX-Reswap: none` and an error toast, because htmx won't swap 4xx responses. The CSRF token is sent on every htmx request via `hx-headers` on `<body>`.

## Security notes

- Passwords are hashed with `password_hash`.
- Session IDs are regenerated on login.
- Every POST is CSRF-checked.
- All SQL uses prepared statements.
- All output is escaped with `e()`.
- Players can only see their own pack, history, carts and offers to sell, and only the stores the DM has opened to them.
