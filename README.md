# Jayann's Store

A small neighbourhood grocery store, built as a portfolio piece. Customers browse
a catalogue, fill a cart and check out; staff run the same catalogue from an
admin panel that keeps a real stock ledger rather than a single number that
drifts out of sync.

The interesting part of this project is not the shop front. It is the inventory
model: `products.stock` is a fast-to-read balance, and `stock_movements` is an
append-only ledger that explains every change to it.

---

## Contents

- [What it does](#what-it-does)
- [Stack](#stack)
- [Getting started](#getting-started)
- [Signing in](#signing-in)
- [Project layout](#project-layout)
- [The inventory system](#the-inventory-system)
- [Design](#design)
- [Verification tooling](#verification-tooling)
- [Notes and limitations](#notes-and-limitations)

---

## What it does

**Storefront**

- Home page with a hero carousel, category tiles, featured products, sale items
  and a three-step explainer.
- Full catalogue with category, price, availability and discount filters, plus
  sorting and paging.
- Category listing, "on sale" listing and keyword search.
- Product quick view, live cart quantities, and a checkout that records a stock
  movement for every line it sells.
- Customer accounts: register, sign in, profile, delivery address, order
  history and printable receipts.

**Admin panel**

- Dashboard with sales, order and stock-at-value figures, plus a reorder list.
- Product management with inline quick edit, a full editor, and an archive that
  is reversible.
- Order handling: list with status filtering, detail view, cancellation, and a
  printable receipt.
- A real inventory system: adjust stock, browse the movement ledger, export it
  to CSV, and view per-product history.
- Customer accounts, contact-message inbox and admin account management.

---

## Stack

| Piece | Choice |
| --- | --- |
| Language | PHP 8.2 |
| Database | MySQL / MariaDB 10.4, accessed through PDO with prepared statements |
| Markup | Server-rendered PHP templates, semantic HTML5 |
| Styling | Hand-written CSS with custom properties. No framework, no build step |
| Scripting | Vanilla JavaScript, deferred. No framework, no bundler |
| Server | Apache on XAMPP, with `.htaccess` hardening |
| PDF | FPDF, vendored under `libs/` for receipts |

There is no Composer, no `node_modules` and no build pipeline. Clone it, import
the schema, and it runs.

---

## Getting started

### Requirements

XAMPP (or any Apache + PHP 8.1+ + MySQL/MariaDB stack).

### Install

1. Put the project in your web root. With XAMPP that is `C:\xampp\htdocs\Jayann_Store`,
   served at `http://localhost/Jayann_Store`.

2. Start Apache and MySQL from the XAMPP control panel.

3. Create the database and import the schema:

   ```bash
   mysql -u root -e "CREATE DATABASE jayann_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root jayann_store < database/schema.sql
   ```

4. Seed data, including the inventory tables and 24 sample products:

   ```bash
   mysql -u root jayann_store < database/inventory.sql
   ```

### Configuration

Credentials are read from the environment with sensible local defaults, so
nothing secret is committed. `app/config.php` is the only file that needs
editing, and usually it does not.

| Variable | Default |
| --- | --- |
| `JAYANN_DB_HOST` | `localhost` |
| `JAYANN_DB_PORT` | `3306` |
| `JAYANN_DB_NAME` | `jayann_store` |
| `JAYANN_DB_USER` | `root` |
| `JAYANN_DB_PASSWORD` | *(empty)* |

The same file holds the store's own settings: name, tagline, address, service
area, opening hours, the free-delivery threshold and the Paymongo keys.

### Verify the install

```bash
php tools/smoke.php
```

That walks the site over HTTP and reports on 65 checks: page status codes,
redirect targets, the security headers, and whether the application code and
schema are actually blocked from the web.

---

## Signing in

The admin panel lives at `/admin/admin_login.php`.

| Role | Username | Password |
| --- | --- | --- |
| Admin | `admin` | `password123` |

Customer accounts are created through the storefront signup form.

> Change the seeded admin password before putting this anywhere public.

---

## Project layout

```
Jayann_Store/
├── admin/                 Admin panel screens, one file per screen
│   ├── admin_login.php    Sign in
│   ├── dashboard.php      KPIs, recent orders, reorder list
│   ├── products.php       Product list with inline quick edit
│   ├── update_product.php Product editor
│   ├── product_archive.php Reversible archive and restore
│   ├── inventory.php      Stock adjustment
│   ├── stock_movements.php Movement ledger with CSV export
│   ├── product_stock.php  Per-product stock history
│   ├── placed_orders.php  Order list
│   ├── order_view.php     Order detail, cancel and restock
│   ├── order_receipt.php  Printable receipt
│   └── ...
│
├── app/                   Application code, never web-accessible
│   ├── config.php         All configuration, env-overridable
│   ├── bootstrap.php      Wires config, database, session, helpers
│   ├── helpers.php        Output escaping, money, auth, flash, redirect
│   ├── inventory.php      The stock ledger and its invariants
│   ├── cart_actions.php   Cart add, update and remove
│   ├── payments/
│   │   └── paymongo.php   Paymongo gateway wrapper
│   └── views/
│       ├── layout/        head.php (shell + header), footer.php
│       ├── admin/         head.php (admin shell), footer.php, helpers.php
│       └── shop/          product_card.php, catalog.php, quick_view_modal.php
│
├── assets/
│   ├── css/style.css      Storefront design system
│   ├── css/admin_style.css Admin design system
│   ├── js/script.js       Storefront behaviour
│   ├── js/admin_script.js Admin behaviour
│   └── img/               Logo, promo and category artwork
│
├── database/
│   ├── schema.sql         Full schema, safe to import into an empty database
│   ├── inventory.sql      Idempotent inventory migration
│   └── upgrade.sql        Idempotent migration for earlier installs
│
├── libs/                  Vendored FPDF
├── tools/                 Verification and maintenance scripts
├── uploads/products/      Product photography
├── .htaccess              Blocks web access to app/ and database/
└── index.php              Redirects to the home page
```

### Database tables

| Table | Purpose |
| --- | --- |
| `products` | Catalogue, including the `stock` balance |
| `stock_movements` | Append-only ledger of every stock change |
| `product_archive` | Reversible archive of deleted products |
| `orders` | Orders and their status |
| `users` | Customer accounts |
| `cart` | Persistent carts |
| `admin` | Admin accounts |
| `messages` | Contact form submissions |

---

## The inventory system

Most small shops keep one number per product and edit it by hand. That number
drifts: a sale adjusts it, a return adjusts it, a lost box adjusts it, and
within a month nobody can say what it should be. This project keeps two things
instead.

**`products.stock`** is the balance, read on every catalogue page. It is fast and
it is always current.

**`stock_movements`** is the ledger. One row per change, never updated and never
deleted, each recording the product, the signed quantity change, the reason, a
reference such as an order id, who did it, and a note.

The rule that keeps them honest: **`app/inventory.php` contains the only function
in the codebase that is allowed to write `products.stock`.** Every adjustment,
sale, cancellation and correction goes through it. Nothing else touches the
column, so the balance can always be explained by the ledger.

### Movement reasons

| Reason | Meaning |
| --- | --- |
| `opening` | Balance when a product is created |
| `sale` | Sold at checkout |
| `restock` | New stock received |
| `correction` | Set to a counted value |
| `return` | Customer returned goods |
| `damage` | Written off as damaged |
| `loss` | Missing stock |
| `archive` | Written off when a product is permanently deleted |
| `reconcile` | Legacy balance differed; the difference is recorded, not hidden |

### Design decisions worth calling out

**Archiving does not write off stock.** Archiving a product is reversible, so
destroying its stock would destroy information. Only a permanent delete logs an
`archive` write-off.

**Corrections are absolute, deltas are relative.** `adjust_stock()` takes a
signed delta and clamps the result at zero, recording the delta that was
*actually* applied rather than the one requested, so a request to remove more
than exists leaves an honest trail.

**Updates are idempotent.** A counting session that runs twice does not
double-count. Where older data left the balance and the ledger disagree, the
difference is recorded as a `reconcile` movement and the ledger is considered
the source of truth from then on.

**Concurrency is handled.** Adjustments take a `SELECT ... FOR UPDATE` row lock,
so two staff members adjusting the same product cannot interleave into a
corrupted balance.

---

## Design

Both stylesheets are built as design systems rather than pages, so a change to a
token restyles the whole site.

- **Tokens.** Ninety-two custom properties in `:root` cover the brand palette,
  spacing scale, radii, shadows, typography and every semantic colour. The admin
  panel reuses the same tokens on a dark sidebar shell, so the two halves of the
  app cannot drift apart.
- **Responsive.** Every layout is verified from 1600px down to 320px, plus short
  landscape phones and touch devices. The category bar and header adapt, product
  grids step down from four columns to two, the admin sidebar becomes an
  off-canvas drawer behind a burger, and forms collapse to one column.
- **Touch targets.** On `hover: none` devices, buttons and chips grow to at least
  2.5rem because a mouse pointer is not available to aim with.
- **No JavaScript dependency for layout.** The drawer, menus and quick view all
  work as plain HTML and CSS first, and JavaScript only enhances them.

Accessibility is handled throughout: skip link, focus-visible rings, `aria-*`
state on the drawer and dropdowns, labelled form fields, and
`prefers-reduced-motion` support.

---

## Verification tooling

The `tools/` directory holds the scripts used to check this project. They are
part of the deliverable, not scaffolding.

| Tool | What it does |
| --- | --- |
| `smoke.php` | 65 checks over HTTP: status codes, redirects, security headers, and that `app/` and `database/` are not reachable |
| `flows.php` | 164 checks that exercise real journeys end to end: browse, cart, checkout, stock effects, admin login, cancellation and restock. Cleans up after itself |
| `responsive_audit.php` | Parses the stylesheets and every page's markup to flag elements likely to overflow at small widths |
| `config_audit.php` | Resolves every `config()` path used across the codebase against `app/config.php` |
| `css_audit.php` | Resolves the real CSS cascade per breakpoint and reports any mobile rule that a more specific rule silently defeats |
| `strip_comments.php` | Token-aware comment stripper for PHP, CSS and JS |
| `strip_comments_test.php` | 26 cases proving the stripper does not mistake strings, regex literals, heredocs or data URIs for comments |
| `strip_damage_check.php` | Reproduces a strip and diffs it against the tree to prove no code was lost |
| `admin_password.php` | Re-hashes an admin password |

Run them all:

```bash
php tools/smoke.php
php tools/flows.php
php tools/responsive_audit.php
php tools/config_audit.php
php tools/css_audit.php
```

`css_audit.php` earns its keep: it found the admin sidebar being declared
`position: sticky` *after* its own mobile `position: fixed` override, which
defeated the override and left the drawer in flow as a full-height grid item,
pushing the main content a whole screen down on phones.

---

## Notes and limitations

- **Payments.** Paymongo is wired in but was deprioritised while the shop front
  and the inventory model were built. The default flow is pay on delivery.
- **Search** is `LIKE`-based. Fine at this catalogue size; it wants full-text
  indexes or a search service at real scale.
- **Passwords** verify against bcrypt, bare SHA-1 or legacy plain text, and
  transparently upgrade with `password_needs_rehash()` on next sign-in.
- **`libs/`** is vendored FPDF and includes the manual pages it ships with. They
  are committed so the copy is complete and auditable.
- **`uploads/`** holds real product photography and is committed, so the
  catalogue renders immediately after clone.
- The seeded admin password is a known default. Rotate it before any real use.

---

## Licence

FPDF is bundled under its own permissive licence, reproduced in
`libs/license.txt`. The rest of the project is provided as portfolio work.
