<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dry  = in_array('--dry-run', $argv, true);

chdir($root);

function git(string $cmd): array
{
    $out = [];
    exec('git ' . $cmd . ' 2>&1', $out, $code);
    return [$code, $out];
}

function subject_for(string $rel): string
{
    $exact = [

        '.gitignore'         => 'chore(repo): ignore local overrides, logs and editor noise',
        '.htaccess'          => 'chore(security): harden Apache config and deny access to app code',
        'index.php'          => 'refactor(store): send the site root to the home page',
        'README.md'          => 'docs: document setup, architecture and the inventory system',

        'database/schema.sql'    => 'feat(db): define the full schema for the store',
        'database/inventory.sql' => 'feat(db): add the stock_movements ledger and inventory columns',
        'database/upgrade.sql'   => 'chore(db): add an idempotent migration for existing installs',

        'app/config.php'           => 'feat(config): centralise store, database and app settings',
        'app/bootstrap.php'        => 'refactor(core): add one bootstrap for config, database and session',
        'app/helpers.php'          => 'refactor(core): add shared helpers for output, money, auth and flash messages',
        'app/inventory.php'        => 'feat(inventory): add the stock ledger and the only writer of products.stock',
        'app/cart_actions.php'     => 'feat(cart): add the add, update and remove cart actions',
        'app/payments/paymongo.php'=> 'feat(payments): add the Paymongo gateway wrapper',

        'app/views/layout/head.php'  => 'feat(store): build the shared page shell, header and single search bar',
        'app/views/layout/footer.php'=> 'feat(store): build the site footer with store, shop and account links',

        'app/views/admin/head.php'    => 'feat(admin): build the admin shell with sidebar, topbar and flash messages',
        'app/views/admin/footer.php'  => 'feat(admin): close the admin shell with the drawer, scripts and toasts',
        'app/views/admin/helpers.php' => 'refactor(admin): add the admin table, badge and form helpers',

        'app/views/shop/product_card.php'      => 'refactor(store): extract the product card and grid into reusable functions',
        'app/views/shop/catalog.php'           => 'feat(store): add the shared catalogue listing used by every browse page',
        'app/views/shop/quick_view_modal.php'  => 'feat(store): add the quick view modal shell',

        'admin/dashboard.php'        => 'feat(admin): add the dashboard with sales, order and stock KPIs',
        'admin/inventory.php'        => 'feat(inventory): add the stock adjustment screen',
        'admin/stock_movements.php'  => 'feat(inventory): add the stock ledger with CSV export',
        'admin/product_stock.php'    => 'feat(inventory): add per-product stock history',
        'admin/order_view.php'       => 'feat(admin): add the order detail screen with cancel and restock',
        'admin/placed_orders.php'    => 'feat(admin): add the order list with status filtering',
        'admin/products.php'         => 'feat(admin): add the product list with inline quick edit',
        'admin/update_product.php'   => 'feat(admin): add the product editor with absolute stock correction',
        'admin/product_archive.php'  => 'feat(admin): add the product archive with reversible restore',
        'admin/order_receipt.php'    => 'feat(admin): add the printable order receipt',
        'admin/admin_login.php'      => 'feat(admin): add the admin login',
        'admin/register_admin.php'   => 'feat(admin): add the admin signup',
        'admin/admin_accounts.php'   => 'feat(admin): add admin account management',
        'admin/update_profile.php'   => 'feat(admin): add the admin profile editor',
        'admin/users_accounts.php'   => 'feat(admin): add the customer account list',
        'admin/messages.php'         => 'feat(admin): add the contact message inbox',
        'admin/index.php'            => 'refactor(admin): route the admin root to the dashboard',
        'admin/logout.php'           => 'feat(admin): add the admin logout endpoint',

        'home.php'                   => 'feat(store): add the home page with hero, categories and featured products',
        'products.php'               => 'feat(store): add the full product catalogue with filters and sorting',
        'discounted_products.php'    => 'feat(store): add the sale page listing every discounted product',
        'discounted products.php'    => 'refactor(store): keep the legacy spaced URL working as a redirect',
        'category.php'               => 'feat(store): add the per-category listing page',
        'search.php'                 => 'feat(store): add search results with sorting and paging',
        'quick_view.php'             => 'feat(store): add the quick view product fragment',
        'cart.php'                   => 'feat(cart): add the cart page with live quantity updates',
        'checkout.php'               => 'feat(checkout): add checkout that logs a sale movement per line',
        'payment.php'                => 'feat(payments): add the payment step and its unpaid order state',
        'receipt.php'                => 'feat(orders): add the customer receipt view',
        'download_receipt.php'       => 'feat(orders): add the receipt download endpoint',
        'orders.php'                 => 'feat(orders): add the customer order history and tracking',
        'login.php'                  => 'feat(auth): add customer login',
        'register.php'               => 'feat(auth): add customer signup',
        'logout.php'                 => 'feat(auth): add the shared logout endpoint',
        'profile.php'                => 'feat(account): add the customer profile and address',
        'update_profile.php'         => 'feat(account): add the profile update handler',
        'update_address.php'         => 'feat(account): add the delivery address update handler',
        'contact.php'                => 'feat(store): add the contact page and message form',
        'about.php'                  => 'feat(store): add the about page',

        'assets/css/style.css'       => 'style(css): build the storefront design system and breakpoint matrix',
        'assets/css/admin_style.css' => 'style(css): build the admin design system with an off-canvas drawer',
        'assets/js/script.js'        => 'feat(js): add storefront interactivity for the carousel, cart and quick view',
        'assets/js/admin_script.js'  => 'feat(js): add admin interactivity for the drawer, dropdowns and toasts',

        'tools/admin_password.php'      => 'chore(tools): add the admin password re-hash helper',
        'tools/commit_per_file.php'     => 'chore(tools): add the per-file history rebuild script',
        'tools/config_audit.php'        => 'test(tools): add the config audit check',
        'tools/css_audit.php'           => 'test(tools): add the css cascade audit',
        'tools/flows.php'               => 'test(tools): add the end-to-end flow checks',
        'tools/responsive_audit.php'    => 'test(tools): add the responsive audit check',
        'tools/smoke.php'               => 'test(tools): add the HTTP smoke checks',
        'tools/strip_comments.php'      => 'chore(tools): add the comment stripper state machine',
        'tools/strip_comments_test.php' => 'test(tools): add the strip comments test suite',
        'tools/strip_damage_check.php'  => 'chore(tools): add the strip damage detector',
        'tools/strip_damage_diff.php'   => 'chore(tools): add the strip damage diff helper',
        'tools/build_schema.php'        => 'chore(tools): add the schema rebuild helper',
    ];

    if (isset($exact[$rel])) {
        return $exact[$rel];
    }

    if (str_starts_with($rel, 'assets/img/')) {
        $base = basename($rel);
        $stem = pathinfo($rel, PATHINFO_FILENAME);
        $words = strtolower(trim(preg_replace('/[_\-]+/', ' ', $stem) ?? $stem));
        $words = preg_replace('/\s+(img|image|icon|picture)$/', '', $words) ?? $words;

        $descriptions = [
            'about-img.svg'       => 'illustration for the about page',
            'contact-img.svg'     => 'illustration for the contact page',
            'drinks.png'          => 'category banner for beverages',
            'grocery-cart.png'    => 'icon for the grocery category',
            'must-have.png'       => 'category banner for must-have essentials',
            'order.png'           => 'icon for order tracking',
            'payment.png'         => 'payment method illustration (legacy)',
            'paymentss.png'       => 'payment method illustration (legacy variant)',
            'personal-care.png'   => 'category banner for personal care',
            'placeholder.svg'     => 'fallback product image used when no photo is set',
            'promo1.png'          => 'promo carousel slide 1 (unused in current layout)',
            'promo2.png'          => 'promo carousel slide 2: fresh restocks',
            'promo3.png'          => 'promo carousel slide 3: deals you can use',
            'promo4.png'          => 'promo carousel slide 4: groceries without the trip',
            'snack.png'           => 'category banner for snacks',
            'storenijayann.png'   => 'store logo used in header and footer',
            'user-icon.png'       => 'default user avatar placeholder',
        ];

        if (isset($descriptions[$base])) {
            return sprintf('chore(assets): add %s', $descriptions[$base]);
        }
        return sprintf('chore(assets): add the %s asset', $words);
    }

    if (str_starts_with($rel, 'libs/')) {
        $base = basename($rel);
        $dir  = basename(dirname($rel));
        $stem = pathinfo($rel, PATHINFO_FILENAME);

        if ($dir === 'doc') {
            $method = str_replace(['set', 'get'], ['set', 'get'], $stem);
            return sprintf('chore(vendor): add FPDF doc page for %s()', $stem);
        }

        if ($dir === 'font') {
            $families = [
                'courier'   => 'Courier',
                'courierb'  => 'Courier Bold',
                'courierbi' => 'Courier Bold Italic',
                'courieri'  => 'Courier Italic',
                'helvetica'    => 'Helvetica',
                'helveticab'   => 'Helvetica Bold',
                'helveticabi'  => 'Helvetica Bold Italic',
                'helveticai'   => 'Helvetica Italic',
                'symbol'    => 'Symbol (dingbats)',
                'times'     => 'Times Roman',
                'timesb'    => 'Times Bold',
                'timesbi'   => 'Times Bold Italic',
                'timesi'    => 'Times Italic',
                'zapfdingbats' => 'Zapf Dingbats',
            ];
            if (isset($families[$stem])) {
                return sprintf('chore(vendor): add FPDF core font metrics for %s', $families[$stem]);
            }
            return sprintf('chore(vendor): add FPDF font metric %s', $stem);
        }

        if ($dir === 'makefont') {
            if ($base === 'makefont.php') {
                return 'chore(vendor): add FPDF makefont utility to install custom fonts';
            }
            if ($base === 'ttfparser.php') {
                return 'chore(vendor): add FPDF TTF parser for font conversion';
            }
            $encoding = $stem;
            return sprintf('chore(vendor): add FPDF encoding map %s', $encoding);
        }

        if ($dir === 'tutorial') {
            $tutoMap = [
                'tuto1.php' => 'basic hello world example',
                'tuto1.htm' => 'basic hello world example (HTML output)',
                'tuto2.php' => 'header, footer, page break and image example',
                'tuto2.htm' => 'header, footer, page break and image example (HTML output)',
                'tuto3.php' => 'colored table with header rows example',
                'tuto3.htm' => 'colored table with header rows example (HTML output)',
                'tuto4.php' => 'improved table with pagination example',
                'tuto4.htm' => 'improved table with pagination example (HTML output)',
                'tuto5.php' => 'multi-cell and automatic page break example',
                'tuto5.htm' => 'multi-cell and automatic page break example (HTML output)',
                'tuto6.php' => 'write and link example with flowing text',
                'tuto6.htm' => 'write and link example with flowing text (HTML output)',
                'tuto7.php' => 'custom font loading with CevicheOne example',
                'tuto7.htm' => 'custom font loading with CevicheOne example (HTML output)',
            ];
            if (isset($tutoMap[$base])) {
                return sprintf('chore(vendor): add FPDF tutorial %s', $tutoMap[$base]);
            }
            if (str_ends_with($base, '.ttf') || str_ends_with($base, '.z')) {
                return sprintf('chore(vendor): add FPDF tutorial font asset %s', $base);
            }
            if (str_ends_with($base, '.txt')) {
                return sprintf('chore(vendor): add FPDF tutorial data file %s', $base);
            }
            return sprintf('chore(vendor): add FPDF tutorial asset %s', $base);
        }

        $rootMap = [
            'fpdf.php'     => 'the FPDF class itself',
            'fpdf.css'     => 'FPDF bundled CSS for HTML output',
            'install.txt'  => 'FPDF installation instructions',
            'license.txt'  => 'FPDF licence (permissive, reproduced here)',
            'FAQ.htm'      => 'FPDF frequently asked questions',
            'changelog.htm'=> 'FPDF changelog',
        ];
        if (isset($rootMap[$base])) {
            return sprintf('chore(vendor): add FPDF %s', $rootMap[$base]);
        }
        return sprintf('chore(vendor): add FPDF asset %s', $base);
    }

    if (str_starts_with($rel, 'uploads/products/')) {
        $base = basename($rel);
        $productNames = [
            'coffee.jpeg'                                         => 'Kopiko 3in1 Coffee - Blanca Twin Pack',
            '1734357572_summit.webp'                              => 'Summit Natural Drinking Water 350ml',
            '2004895934-1.png'                                    => 'Buko Pandan Rice Green Sack 25kg',
            'H103397_1b88.png'                                    => 'ORAL B Soft 3D White Whitening Manual Toothbrush 3pcs',
            'skyflakes.webp'                                      => 'M.Y. San Sky Flakes Crackers Original 25g x 10',
            'colgate-mcp-great-regular-flavor-toohpaste-214g-box.jpg' => 'Colgate Maximum Cavity Protection Toothpaste 214g',
            '1735896902_vcut.png'                                 => 'Jack n Jill V Cut Potato Chips Spicy BBQ 162g',
            '1735897083_cheeserings.webp'                         => 'Regent Cheese Ring Snacks 60g',
            '1735898564_SILKA_Whitening_Herbal_Soap_Green_Papaya_135g.png.webp' => 'SILKA Whitening Herbal Soap Green Papaya 135g',
            '1735898673_4801981118502COKE295MLP13.75_800x_1_-removebg-preview.png.webp' => 'COCA-COLA Regular Mismo 290ml',
            '1735898720_Piattos-Cheese-40g.png.webp'              => 'PIATTOS Cheese Flavored Potato Chips 40g',
            '1735898846_image_05b1afaa-7d73-48ac-b341-185a34257307_1_-removebg-preview.png.webp' => 'LUDY\'S SALABAT Ginger Brew Classic 8g',
            '1735898903_S733e34095ba64f44bd04c11ec53798634-removebg-preview.png.webp' => 'STING Energy Drink Strawberry 290ml',
            '1735898962_51vD7lOzp_L.jpg.webp'                     => 'DR. S. WONG\'S SULFUR SOAP Yellow 135g',
            '1735899037_10107001_milcu-underarm-and-foot-deo-pdr-40g_1_-removebg-preview.png.webp' => 'MILCU Underarm and Foot Deodorant Powder 40g',
            '1735899097_81wkfQI6rnL.jpg.webp'                     => 'LISTERINE Mouthwash 250mL Cool Mint',
            '1735899146_4806502359754_1024x-removebg-preview[1].png.webp' => 'FEMME Bathroom Tissue 2 Ply 150 Pulls',
            '1735899214_ezgif-4-cc15e1839f-removebg-preview[1].png.webp' => 'SISTERS Night Plus Heavy Flow with Wings 8 Pads',
            '1735899283_c5292204540082a4dc45aa231b2a5915.jpg.webp' => 'NESTOGEN 1 Infant Milk Formula 135g',
            '1735899359_Bear_Brand_Junior_1__400g.png.webp'       => 'BEAR BRAND Junior 1+ Milk 400g',
            '1735899503_tcr1025e_fl_45_za_1-removebg-preview[1].png.webp' => 'BENCH FIX Professional Clay Doh 25g',
            'del.webp'                                            => 'DEL MONTE 100% Pineapple Juice with A-C-E 220ml',
            '1735898790_lucky_me_go_cup_batchoy.jpg.webp'         => 'LUCKY ME Go Cup Batchoy Cup Noodles 40g',
            'safeguard.jpg'                                       => 'Safeguard Family Germ Protection Soap',
        ];
        if (isset($productNames[$base])) {
            return sprintf('chore(assets): add product image for %s', $productNames[$base]);
        }
        return sprintf('chore(assets): add orphan product image %s', $base);
    }

    return sprintf('chore: add %s', $rel);
}

function sort_key(string $rel): array
{
    $group = 5;

    if ($rel === '.gitignore' || $rel === '.htaccess') $group = 0;
    elseif (str_starts_with($rel, 'database/')) $group = 1;
    elseif (str_starts_with($rel, 'app/')) $group = 2;
    elseif (str_starts_with($rel, 'admin/')) $group = 3;
    elseif (!str_contains($rel, '/')) $group = 4;
    elseif (str_starts_with($rel, 'assets/')) $group = 5;
    elseif (str_starts_with($rel, 'tools/')) $group = 6;
    elseif (str_starts_with($rel, 'libs/')) $group = 7;
    elseif (str_starts_with($rel, 'uploads/')) $group = 8;
    elseif ($rel === 'README.md') $group = 9;

    return [$group, $rel];
}

[, $tracked] = git('ls-files');
$files = array_values(array_filter(array_map('trim', $tracked)));

[, $untracked] = git('ls-files --others --exclude-standard');
foreach (array_map('trim', $untracked) as $extra) {
    if ($extra !== '' && !in_array($extra, $files, true)) {
        $files[] = $extra;
    }
}

usort($files, static fn (string $a, string $b): int => sort_key($a) <=> sort_key($b));

printf("%d files to commit\n\n", count($files));

if ($dry) {
    foreach ($files as $f) {
        printf("  %s\n", subject_for($f));
    }
    exit(0);
}

git('checkout --orphan per-file');
git('rm -r --cached . -q');
git('config core.autocrlf false');
git('config core.eol lf');

$done = 0;
foreach ($files as $rel) {
    $subject = subject_for($rel);

    [, $addOut] = git('add -- ' . escapeshellarg($rel));
    if ($addOut && !str_contains(implode("\n", $addOut), 'fatal')) {
        printf("  add failed: %s\n", $rel);
        continue;
    }

    [, $commitOut] = git('commit -q --allow-empty -m ' . escapeshellarg($subject));
    if ($commitOut) {
        printf("  commit failed: %s\n  %s\n", $rel, implode("\n", $commitOut));
        exit(1);
    }

    $done++;
    if ($done % 25 === 0) {
        printf("  ... %d/%d\n", $done, count($files));
    }
}

printf("\n%d commits created\n", $done);
[, $log] = git('log --oneline -5');
foreach ($log as $l) {
    printf("  %s\n", $l);
}
