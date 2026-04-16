<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SeoMeta;
use App\Services\LegalContentSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportWpData extends Command
{
    protected $signature = 'wp:import
                            {sql_path : Path to the WordPress SQL dump file}
                            {uploads_path : Path to the wp-content/uploads/ directory}
                            {--dry-run : Preview what would be imported without writing to the database}';

    protected $description = 'Import data from a WordPress/WooCommerce SQL dump into the Laravel schema';

    // Parsed table data keyed by table name
    private array $tables = [];

    // Lookup indexes built after parsing
    private array $postMetaByPost = [];
    private array $userMetaByUser = [];
    private array $orderItemMetaByItem = [];

    // WP ID -> Laravel ID mappings
    private array $productMap = [];
    private array $categoryMap = [];
    private array $customerMap = [];
    private array $orderMap = [];

    // Counters
    private array $counts = [
        'categories' => 0,
        'products' => 0,
        'customers' => 0,
        'orders' => 0,
        'order_items' => 0,
        'seo_metas' => 0,
        'images_copied' => 0,
    ];

    public function handle(): int
    {
        $sqlPath = $this->argument('sql_path');
        $uploadsPath = rtrim($this->argument('uploads_path'), '/');
        $dryRun = $this->option('dry-run');

        if (!file_exists($sqlPath)) {
            $this->error("SQL file not found: {$sqlPath}");
            return 1;
        }

        if (!is_dir($uploadsPath)) {
            $this->error("Uploads directory not found: {$uploadsPath}");
            return 1;
        }

        if ($dryRun) {
            $this->warn('=== DRY RUN MODE - No data will be written ===');
        }

        $this->info('Step 1/7: Parsing SQL dump...');
        $this->parseSqlDump($sqlPath);

        $this->info('Step 2/7: Building lookup indexes...');
        $this->buildIndexes();

        $this->info('Step 3/7: Importing product categories...');
        $this->info('Step 4/7: Importing products...');
        $this->info('Step 5/7: Importing customers...');
        $this->info('Step 6/7: Importing orders...');
        $this->info('Step 7/7: Importing SEO meta...');

        if ($dryRun) {
            $this->dryRunPreview($uploadsPath);
        } else {
            DB::beginTransaction();
            try {
                $this->importCategories();
                $this->importProducts($uploadsPath);
                $this->importCustomers();
                $this->importOrders();
                $this->importSeoMeta();

                DB::commit();
                $this->newLine();
                $this->info('=== Import completed successfully ===');
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Import failed, all changes rolled back: {$e->getMessage()}");
                $this->error($e->getTraceAsString());
                return 1;
            }
        }

        $this->printSummary();
        return 0;
    }

    // =========================================================================
    // SQL Parsing
    // =========================================================================

    private function parseSqlDump(string $path): void
    {
        $neededTables = [
            'wp_posts', 'wp_postmeta',
            'wp_terms', 'wp_term_taxonomy', 'wp_term_relationships',
            'wp_users', 'wp_usermeta',
            'wp_wc_orders', 'wp_wc_order_addresses', 'wp_wc_order_operational_data',
            'wp_wc_order_stats',
            'wp_woocommerce_order_items', 'wp_woocommerce_order_itemmeta',
            'wp_wc_orders_meta',
        ];

        foreach ($neededTables as $t) {
            $this->tables[$t] = [];
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new \RuntimeException("Cannot open SQL file: {$path}");
        }

        $fileSize = filesize($path);
        $bytesRead = 0;
        $bar = $this->output->createProgressBar($fileSize);
        $bar->setFormat(' %current%/%max% bytes [%bar%] %percent:3s%%');

        $buffer = '';
        $inInsert = false;
        $currentTable = null;
        $currentColumns = [];

        while (($line = fgets($handle)) !== false) {
            $bytesRead += strlen($line);
            $bar->setProgress(min($bytesRead, $fileSize));

            $trimmed = rtrim($line, "\r\n");

            // Detect INSERT INTO for a table we care about
            // Sequel Ace format: INSERT INTO `table` (`col1`, `col2`, ...)
            if (!$inInsert && preg_match('/^INSERT INTO `([^`]+)`\s*\((.+)\)\s*(VALUES)?/i', $trimmed, $m)) {
                $tableName = $m[1];
                if (!isset($this->tables[$tableName])) {
                    continue;
                }
                $currentTable = $tableName;
                $currentColumns = array_map(function ($c) {
                    return trim($c, '` ');
                }, explode(',', $m[2]));
                $inInsert = true;
                $buffer = '';
                continue;
            }

            if ($inInsert) {
                // Skip the standalone "VALUES" line (Sequel Ace format)
                if (preg_match('/^\s*VALUES\s*$/', $trimmed)) {
                    continue;
                }

                $buffer .= $trimmed;

                // Each row in Sequel Ace dumps is on its own line starting with a tab
                // and ending with either ), or );
                // We process complete row tuples one at a time
                while ($this->extractRow($buffer, $currentTable, $currentColumns)) {
                    // extractRow modifies $buffer by reference
                }

                // End of INSERT block: line ends with ;
                if (preg_match('/;\s*$/', $trimmed)) {
                    $inInsert = false;
                    $currentTable = null;
                    $currentColumns = [];
                    $buffer = '';
                }
            }
        }

        fclose($handle);
        $bar->finish();
        $this->newLine();

        foreach ($neededTables as $t) {
            $count = count($this->tables[$t]);
            $this->line("  Parsed {$t}: {$count} rows");
        }
    }

    /**
     * Try to extract one (col1,col2,...) tuple from the beginning of $buffer.
     * Returns true if a row was extracted, false otherwise.
     */
    private function extractRow(string &$buffer, string $table, array $columns): bool
    {
        $buffer = ltrim($buffer);

        // Skip leading comma
        if (str_starts_with($buffer, ',')) {
            $buffer = ltrim(substr($buffer, 1));
        }

        // Must start with (
        if (!str_starts_with($buffer, '(')) {
            return false;
        }

        // Walk through to find the matching closing )
        $len = strlen($buffer);
        $i = 1; // skip opening (
        $values = [];
        $current = '';
        $inString = false;
        $escape = false;

        while ($i < $len) {
            $ch = $buffer[$i];

            if ($escape) {
                $current .= $ch;
                $escape = false;
                $i++;
                continue;
            }

            if ($ch === '\\') {
                $escape = true;
                $current .= $ch;
                $i++;
                continue;
            }

            if ($inString) {
                if ($ch === "'") {
                    // Check for '' escape
                    if ($i + 1 < $len && $buffer[$i + 1] === "'") {
                        $current .= "''";
                        $i += 2;
                        continue;
                    }
                    $inString = false;
                    $current .= $ch;
                    $i++;
                    continue;
                }
                $current .= $ch;
                $i++;
                continue;
            }

            // Not in string
            if ($ch === "'") {
                $inString = true;
                $current .= $ch;
                $i++;
                continue;
            }

            if ($ch === ',') {
                $values[] = $this->parseSqlValue(trim($current));
                $current = '';
                $i++;
                continue;
            }

            if ($ch === ')') {
                $values[] = $this->parseSqlValue(trim($current));
                // Successfully parsed a row
                $buffer = substr($buffer, $i + 1);

                if (count($values) === count($columns)) {
                    $this->tables[$table][] = array_combine($columns, $values);
                }
                return true;
            }

            $current .= $ch;
            $i++;
        }

        // Could not find closing ) - need more data
        return false;
    }

    private function parseSqlValue(string $raw): ?string
    {
        if ($raw === 'NULL') {
            return null;
        }
        // Remove surrounding quotes
        if (strlen($raw) >= 2 && $raw[0] === "'" && $raw[-1] === "'") {
            $inner = substr($raw, 1, -1);
            // Unescape
            $inner = str_replace("''", "'", $inner);
            $inner = str_replace("\\'", "'", $inner);
            $inner = str_replace('\\\\', '\\', $inner);
            $inner = str_replace('\\n', "\n", $inner);
            $inner = str_replace('\\r', "\r", $inner);
            $inner = str_replace('\\t', "\t", $inner);
            $inner = str_replace('\\0', "\0", $inner);
            return $inner;
        }
        return $raw;
    }

    // =========================================================================
    // Index Building
    // =========================================================================

    private function buildIndexes(): void
    {
        // Post meta: post_id -> [{meta_key, meta_value}, ...]
        foreach ($this->tables['wp_postmeta'] as $row) {
            $this->postMetaByPost[$row['post_id']][$row['meta_key']] = $row['meta_value'];
        }

        // User meta: user_id -> [meta_key => meta_value]
        foreach ($this->tables['wp_usermeta'] as $row) {
            $this->userMetaByUser[$row['user_id']][$row['meta_key']] = $row['meta_value'];
        }

        // Order item meta: order_item_id -> [meta_key => meta_value]
        foreach ($this->tables['wp_woocommerce_order_itemmeta'] as $row) {
            $this->orderItemMetaByItem[$row['order_item_id']][$row['meta_key']] = $row['meta_value'];
        }

        $this->info('  Indexes built: ' .
            count($this->postMetaByPost) . ' posts, ' .
            count($this->userMetaByUser) . ' users, ' .
            count($this->orderItemMetaByItem) . ' order items'
        );
    }

    // =========================================================================
    // Product Categories
    // =========================================================================

    private function importCategories(): void
    {
        // Build term_id -> taxonomy row
        $taxonomies = [];
        foreach ($this->tables['wp_term_taxonomy'] as $row) {
            if ($row['taxonomy'] === 'product_cat') {
                $taxonomies[$row['term_id']] = $row;
            }
        }

        // Build term_id -> term row
        $terms = [];
        foreach ($this->tables['wp_terms'] as $row) {
            $terms[$row['term_id']] = $row;
        }

        // We need to resolve parent relationships, so import parents first
        // Build a list of product_cat terms with their parent info
        $catData = [];
        foreach ($taxonomies as $termId => $tax) {
            if (!isset($terms[$termId])) {
                continue;
            }
            $term = $terms[$termId];
            $catData[$termId] = [
                'term_id' => $termId,
                'term_taxonomy_id' => $tax['term_taxonomy_id'],
                'name' => $term['name'],
                'slug' => $term['slug'],
                'description' => $tax['description'] ?? null,
                'parent_term_id' => $tax['parent'] ?? '0',
            ];
        }

        // Sort: parents first (parent=0 first)
        uasort($catData, fn($a, $b) => ($a['parent_term_id'] <=> $b['parent_term_id']));

        $bar = $this->output->createProgressBar(count($catData));
        foreach ($catData as $cat) {
            $parentId = null;
            if ($cat['parent_term_id'] && $cat['parent_term_id'] !== '0') {
                $parentId = $this->categoryMap[$cat['parent_term_id']] ?? null;
            }

            $category = ProductCategory::create([
                'name' => $cat['name'],
                'slug' => $this->uniqueSlug($cat['slug'], 'product_categories'),
                'description' => $cat['description'] ?: null,
                'parent_id' => $parentId,
            ]);

            $this->categoryMap[$cat['term_taxonomy_id']] = $category->id;
            // Also map by term_id for parent resolution
            $this->categoryMap[$cat['term_id']] = $category->id;
            $this->counts['categories']++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("  Imported {$this->counts['categories']} product categories");
    }

    // =========================================================================
    // Products
    // =========================================================================

    private function importProducts(string $uploadsPath): void
    {
        // Get published products from wp_posts
        $products = array_filter($this->tables['wp_posts'], function ($row) {
            return $row['post_type'] === 'product' && $row['post_status'] === 'publish';
        });

        // Build attachment lookup: ID -> guid (URL)
        $attachments = [];
        foreach ($this->tables['wp_posts'] as $row) {
            if ($row['post_type'] === 'attachment') {
                $attachments[$row['ID']] = $row['guid'];
            }
        }

        // Build term_relationships: object_id -> [term_taxonomy_id, ...]
        $termRels = [];
        foreach ($this->tables['wp_term_relationships'] as $row) {
            $termRels[$row['object_id']][] = $row['term_taxonomy_id'];
        }

        $destDir = storage_path('app/public/products');
        if (!File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $bar = $this->output->createProgressBar(count($products));
        foreach ($products as $post) {
            $postId = $post['ID'];
            $meta = $this->postMetaByPost[$postId] ?? [];

            $price = $this->numericOrNull($meta['_price'] ?? null);
            if ($price === null) {
                $price = $this->numericOrNull($meta['_regular_price'] ?? null) ?? 0;
            }

            $salePrice = $this->numericOrNull($meta['_sale_price'] ?? null);
            $sku = $meta['_sku'] ?? null;
            $stockQty = (int) ($meta['_stock'] ?? 0);
            $stockStatus = ($meta['_stock_status'] ?? 'instock') === 'instock' ? 'instock' : 'outofstock';

            // Main image
            $imagePath = null;
            $thumbnailId = $meta['_thumbnail_id'] ?? null;
            if ($thumbnailId && isset($attachments[$thumbnailId])) {
                $imagePath = $this->copyImage($attachments[$thumbnailId], $uploadsPath, $destDir);
            }

            // Gallery images
            $gallery = [];
            $galleryIds = $meta['_product_image_gallery'] ?? '';
            if ($galleryIds) {
                foreach (explode(',', $galleryIds) as $attachId) {
                    $attachId = trim($attachId);
                    if ($attachId && isset($attachments[$attachId])) {
                        $copied = $this->copyImage($attachments[$attachId], $uploadsPath, $destDir);
                        if ($copied) {
                            $gallery[] = $copied;
                        }
                    }
                }
            }

            $sanitizer = app(LegalContentSanitizer::class);
            $desc = $post['post_content'] ?: null;
            $shortDesc = $post['post_excerpt'] ?: null;
            $product = Product::create([
                'name' => $sanitizer->sanitizeText($post['post_title']),
                'slug' => $this->uniqueSlug($post['post_name'], 'products'),
                'description' => $desc ? $sanitizer->process($desc, 'product') : null,
                'short_description' => $shortDesc ? $sanitizer->sanitize($shortDesc) : null,
                'price' => $price,
                'combo_price' => null,
                'vip_price' => null,
                'sale_price' => $salePrice,
                'sku' => $sku ?: null,
                'stock_quantity' => $stockQty,
                'stock_status' => $stockStatus,
                'image' => $imagePath,
                'gallery' => !empty($gallery) ? $gallery : null,
                'is_active' => true,
                'sort_order' => (int) ($post['menu_order'] ?? 0),
                'wp_id' => (int) $postId,
            ]);

            $this->productMap[$postId] = $product->id;

            // Attach categories via pivot
            if (isset($termRels[$postId])) {
                $categoryIds = [];
                foreach ($termRels[$postId] as $ttId) {
                    if (isset($this->categoryMap[$ttId])) {
                        $categoryIds[] = $this->categoryMap[$ttId];
                    }
                }
                if ($categoryIds) {
                    $product->categories()->sync(array_unique($categoryIds));
                }
            }

            $this->counts['products']++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("  Imported {$this->counts['products']} products, copied {$this->counts['images_copied']} images");
    }

    // =========================================================================
    // Customers
    // =========================================================================

    private function importCustomers(): void
    {
        $users = array_filter($this->tables['wp_users'], function ($row) {
            return (int) $row['ID'] !== 1; // Skip admin
        });

        $bar = $this->output->createProgressBar(count($users));
        foreach ($users as $user) {
            $userId = $user['ID'];
            $meta = $this->userMetaByUser[$userId] ?? [];

            $firstName = $meta['first_name'] ?? '';
            $lastName = $meta['last_name'] ?? '';
            $name = trim("{$lastName}{$firstName}") ?: ($user['display_name'] ?? $user['user_login']);

            // Skip bot/system users
            if (str_contains($user['user_login'], 'wpra.source.author')) {
                $bar->advance();
                continue;
            }

            $customer = Customer::create([
                'name' => $name,
                'email' => $user['user_email'],
                'phone' => $meta['billing_phone'] ?? null,
                'password' => Hash::make(Str::random(32)), // random password, user must reset
                'is_vip' => false,
                'address_city' => $meta['billing_city'] ?? null,
                'address_district' => null,
                'address_detail' => $meta['billing_address_1'] ?? null,
                'address_zip' => $meta['billing_postcode'] ?? null,
                'wp_user_id' => (int) $userId,
            ]);

            $this->customerMap[$userId] = $customer->id;
            $this->counts['customers']++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("  Imported {$this->counts['customers']} customers");
    }

    // =========================================================================
    // Orders
    // =========================================================================

    private function importOrders(): void
    {
        $wcOrders = $this->tables['wp_wc_orders'];

        // Build lookup: order_id -> operational data
        $opData = [];
        foreach ($this->tables['wp_wc_order_operational_data'] as $row) {
            $opData[$row['order_id']] = $row;
        }

        // Build lookup: order_id -> stats
        $stats = [];
        foreach ($this->tables['wp_wc_order_stats'] as $row) {
            $stats[$row['order_id']] = $row;
        }

        // Build lookup: order_id -> address rows
        $addresses = [];
        foreach ($this->tables['wp_wc_order_addresses'] as $row) {
            $addresses[$row['order_id']][$row['address_type']] = $row;
        }

        // Build lookup: order_id -> order meta
        $ordersMeta = [];
        foreach ($this->tables['wp_wc_orders_meta'] as $row) {
            $ordersMeta[$row['order_id']][$row['meta_key']] = $row['meta_value'];
        }

        // Build lookup: order_id -> order items
        $orderItems = [];
        foreach ($this->tables['wp_woocommerce_order_items'] as $row) {
            $orderItems[$row['order_id']][] = $row;
        }

        // Filter to shop_order type only
        $wcOrders = array_filter($wcOrders, fn($o) => ($o['type'] ?? '') === 'shop_order');

        $bar = $this->output->createProgressBar(count($wcOrders));
        foreach ($wcOrders as $wcOrder) {
            $orderId = $wcOrder['id'];
            $op = $opData[$orderId] ?? [];
            $stat = $stats[$orderId] ?? [];
            $billing = $addresses[$orderId]['billing'] ?? [];
            $shipping = $addresses[$orderId]['shipping'] ?? [];
            $meta = $ordersMeta[$orderId] ?? [];

            // Map WC status to new status
            $status = $this->mapOrderStatus($wcOrder['status'] ?? 'wc-pending');

            // Try to match customer by billing email or wp customer_id
            $customerId = null;
            $wcCustomerId = $wcOrder['customer_id'] ?? '0';
            if ($wcCustomerId && $wcCustomerId !== '0' && isset($this->customerMap[$wcCustomerId])) {
                $customerId = $this->customerMap[$wcCustomerId];
            } elseif ($wcOrder['billing_email']) {
                $customer = Customer::where('email', $wcOrder['billing_email'])->first();
                if ($customer) {
                    $customerId = $customer->id;
                }
            }

            $shippingTotal = (float) ($op['shipping_total_amount'] ?? $stat['shipping_total'] ?? 0);
            $total = (float) ($wcOrder['total_amount'] ?? 0);
            $subtotal = $total - $shippingTotal;

            // Shipping info from order meta (CVS store)
            $shippingStoreId = $meta['_shipping_cvs_store_ID'] ?? null;
            $shippingStoreName = $meta['_shipping_cvs_store_name'] ?? null;

            // Build shipping address
            $shippingAddress = null;
            if (!empty($shipping['address_1'])) {
                $parts = array_filter([
                    $shipping['city'] ?? null,
                    $shipping['state'] ?? null,
                    $shipping['address_1'] ?? null,
                    $shipping['address_2'] ?? null,
                ]);
                $shippingAddress = implode('', $parts);
            }

            // Determine shipping method from order items (type=shipping)
            $shippingMethod = null;
            $items = $orderItems[$orderId] ?? [];
            foreach ($items as $item) {
                if (($item['order_item_type'] ?? '') === 'shipping') {
                    $itemMeta = $this->orderItemMetaByItem[$item['order_item_id']] ?? [];
                    $shippingMethod = $itemMeta['method_id'] ?? null;
                    break;
                }
            }

            // Shipping name and phone from billing address (WC typically copies)
            $shippingName = trim(($shipping['last_name'] ?? '') . ($shipping['first_name'] ?? ''))
                ?: trim(($billing['last_name'] ?? '') . ($billing['first_name'] ?? ''));
            $shippingPhone = $shipping['phone'] ?? $billing['phone'] ?? null;

            $order = Order::create([
                'order_number' => (string) $orderId,
                'customer_id' => $customerId,
                'status' => $status,
                'pricing_tier' => 'regular',
                'subtotal' => max($subtotal, 0),
                'shipping_fee' => $shippingTotal,
                'total' => $total,
                'payment_method' => $wcOrder['payment_method'] ?? null,
                'payment_status' => $this->mapPaymentStatus($wcOrder['status'] ?? ''),
                'ecpay_trade_no' => $wcOrder['transaction_id'] ?: null,
                'shipping_method' => $shippingMethod,
                'shipping_name' => $shippingName ?: null,
                'shipping_phone' => $shippingPhone,
                'shipping_address' => $shippingAddress,
                'shipping_store_id' => $shippingStoreId,
                'shipping_store_name' => $shippingStoreName,
                'note' => $wcOrder['customer_note'] ?: null,
                'wp_order_id' => (int) $orderId,
                'created_at' => $wcOrder['date_created_gmt'] ?? now(),
                'updated_at' => $wcOrder['date_updated_gmt'] ?? now(),
            ]);

            $this->orderMap[$orderId] = $order->id;

            // Import line items
            foreach ($items as $item) {
                if (($item['order_item_type'] ?? '') !== 'line_item') {
                    continue;
                }

                $itemMeta = $this->orderItemMetaByItem[$item['order_item_id']] ?? [];
                $wpProductId = $itemMeta['_product_id'] ?? null;
                $productId = $wpProductId ? ($this->productMap[$wpProductId] ?? null) : null;
                $qty = (int) ($itemMeta['_qty'] ?? 1);
                $lineTotal = (float) ($itemMeta['_line_total'] ?? 0);
                $unitPrice = $qty > 0 ? round($lineTotal / $qty, 2) : $lineTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'product_name' => $item['order_item_name'] ?? 'Unknown',
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineTotal,
                    'created_at' => $wcOrder['date_created_gmt'] ?? now(),
                ]);

                $this->counts['order_items']++;
            }

            $this->counts['orders']++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("  Imported {$this->counts['orders']} orders with {$this->counts['order_items']} line items");
    }

    // =========================================================================
    // SEO Meta
    // =========================================================================

    private function importSeoMeta(): void
    {
        $count = 0;
        foreach ($this->productMap as $wpId => $laravelId) {
            $meta = $this->postMetaByPost[$wpId] ?? [];

            $title = $meta['rank_math_title'] ?? null;
            $description = $meta['rank_math_description'] ?? null;
            $focusKeyword = $meta['rank_math_focus_keyword'] ?? null;

            if (!$title && !$description && !$focusKeyword) {
                continue;
            }

            SeoMeta::create([
                'metable_type' => Product::class,
                'metable_id' => $laravelId,
                'title' => $title,
                'description' => $description,
                'focus_keyword' => $focusKeyword,
            ]);

            $count++;
        }

        $this->counts['seo_metas'] = $count;
        $this->info("  Imported {$count} SEO meta records");
    }

    // =========================================================================
    // Dry Run Preview
    // =========================================================================

    private function dryRunPreview(string $uploadsPath): void
    {
        $this->newLine();

        // Categories
        $taxonomies = array_filter($this->tables['wp_term_taxonomy'], fn($r) => $r['taxonomy'] === 'product_cat');
        $terms = [];
        foreach ($this->tables['wp_terms'] as $row) {
            $terms[$row['term_id']] = $row;
        }
        $this->info("Product Categories to import: " . count($taxonomies));
        foreach ($taxonomies as $tax) {
            $t = $terms[$tax['term_id']] ?? null;
            if ($t) {
                $this->line("  - {$t['name']} ({$t['slug']})");
                $this->counts['categories']++;
            }
        }

        // Products
        $products = array_filter($this->tables['wp_posts'], fn($r) => $r['post_type'] === 'product' && $r['post_status'] === 'publish');
        $this->newLine();
        $this->info("Products to import: " . count($products));
        foreach ($products as $p) {
            $meta = $this->postMetaByPost[$p['ID']] ?? [];
            $price = $meta['_price'] ?? 'N/A';
            $sku = $meta['_sku'] ?? 'N/A';
            $this->line("  - [{$p['ID']}] {$p['post_title']} (SKU: {$sku}, Price: {$price})");
            $this->counts['products']++;
        }

        // Customers
        $users = array_filter($this->tables['wp_users'], fn($r) => (int) $r['ID'] !== 1 && !str_contains($r['user_login'], 'wpra.source.author'));
        $this->newLine();
        $this->info("Customers to import: " . count($users));
        foreach ($users as $u) {
            $this->line("  - [{$u['ID']}] {$u['display_name']} ({$u['user_email']})");
            $this->counts['customers']++;
        }

        // Orders
        $orders = array_filter($this->tables['wp_wc_orders'], fn($o) => ($o['type'] ?? '') === 'shop_order');
        $this->newLine();
        $this->info("Orders to import: " . count($orders));
        foreach ($orders as $o) {
            $status = $this->mapOrderStatus($o['status'] ?? 'wc-pending');
            $total = $o['total_amount'] ?? '0';
            $this->line("  - #{$o['id']} Status: {$status}, Total: {$total}, Email: {$o['billing_email']}");
            $this->counts['orders']++;
        }

        // SEO
        $seoCount = 0;
        foreach ($products as $p) {
            $meta = $this->postMetaByPost[$p['ID']] ?? [];
            if (($meta['rank_math_title'] ?? null) || ($meta['rank_math_description'] ?? null) || ($meta['rank_math_focus_keyword'] ?? null)) {
                $seoCount++;
            }
        }
        $this->newLine();
        $this->info("SEO Meta records to import: {$seoCount}");
        $this->counts['seo_metas'] = $seoCount;

        // Images
        $attachments = [];
        foreach ($this->tables['wp_posts'] as $row) {
            if ($row['post_type'] === 'attachment') {
                $attachments[$row['ID']] = $row['guid'];
            }
        }
        $imageCount = 0;
        foreach ($products as $p) {
            $meta = $this->postMetaByPost[$p['ID']] ?? [];
            $thumbnailId = $meta['_thumbnail_id'] ?? null;
            if ($thumbnailId && isset($attachments[$thumbnailId])) {
                $localPath = $this->resolveLocalImagePath($attachments[$thumbnailId], $uploadsPath);
                $exists = $localPath && file_exists($localPath);
                $this->line("  Image [{$p['post_title']}]: " . ($exists ? 'FOUND' : 'MISSING') . " - " . basename($attachments[$thumbnailId]));
                if ($exists) $imageCount++;
            }
            $galleryIds = $meta['_product_image_gallery'] ?? '';
            if ($galleryIds) {
                foreach (explode(',', $galleryIds) as $aid) {
                    $aid = trim($aid);
                    if ($aid && isset($attachments[$aid])) {
                        $localPath = $this->resolveLocalImagePath($attachments[$aid], $uploadsPath);
                        if ($localPath && file_exists($localPath)) $imageCount++;
                    }
                }
            }
        }
        $this->counts['images_copied'] = $imageCount;
        $this->info("Images found locally: {$imageCount}");
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function mapOrderStatus(string $wcStatus): string
    {
        return match ($wcStatus) {
            'wc-completed' => 'completed',
            'wc-processing' => 'processing',
            'wc-on-hold', 'wc-pending' => 'pending',
            'wc-cancelled', 'wc-failed' => 'cancelled',
            'wc-refunded' => 'refunded',
            default => 'pending',
        };
    }

    private function mapPaymentStatus(string $wcStatus): ?string
    {
        return match ($wcStatus) {
            'wc-completed', 'wc-processing' => 'paid',
            'wc-refunded' => 'refunded',
            'wc-on-hold', 'wc-pending' => 'pending',
            'wc-cancelled', 'wc-failed' => 'failed',
            default => null,
        };
    }

    private function numericOrNull(?string $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }

    private function uniqueSlug(string $slug, string $table): string
    {
        $original = $slug;
        $counter = 1;
        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }
        return $slug;
    }

    /**
     * Copy an image from WP uploads to Laravel storage.
     * Returns the public-relative path (e.g. "products/filename.jpg") or null.
     */
    private function copyImage(string $wpUrl, string $uploadsPath, string $destDir): ?string
    {
        $localPath = $this->resolveLocalImagePath($wpUrl, $uploadsPath);
        if (!$localPath || !file_exists($localPath)) {
            return null;
        }

        $filename = basename($localPath);
        // Avoid collisions
        $destPath = "{$destDir}/{$filename}";
        if (file_exists($destPath)) {
            // If same size, assume same file
            if (filesize($destPath) !== filesize($localPath)) {
                $filename = Str::random(8) . '_' . $filename;
                $destPath = "{$destDir}/{$filename}";
            }
        }

        if (!file_exists($destPath)) {
            copy($localPath, $destPath);
        }

        $this->counts['images_copied']++;
        return "products/{$filename}";
    }

    /**
     * Given a WP attachment URL like https://js-store.com.tw/wp-content/uploads/2025/09/img.jpg
     * resolve it to a local file path under the uploads directory.
     */
    private function resolveLocalImagePath(string $wpUrl, string $uploadsPath): ?string
    {
        // Extract the relative path after wp-content/uploads/
        if (preg_match('#wp-content/uploads/(.+)$#', $wpUrl, $m)) {
            $relativePath = $m[1];
            $fullPath = "{$uploadsPath}/{$relativePath}";
            return $fullPath;
        }

        // Fallback: just try the filename
        $filename = basename($wpUrl);
        if (!$filename) {
            return null;
        }

        // Search common year/month directories
        $globResults = glob("{$uploadsPath}/*/{$filename}") ?: glob("{$uploadsPath}/*/*/{$filename}");
        return $globResults[0] ?? null;
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('=== Import Summary ===');
        $this->table(
            ['Entity', 'Count'],
            [
                ['Product Categories', $this->counts['categories']],
                ['Products', $this->counts['products']],
                ['Customers', $this->counts['customers']],
                ['Orders', $this->counts['orders']],
                ['Order Items', $this->counts['order_items']],
                ['SEO Meta', $this->counts['seo_metas']],
                ['Images Copied', $this->counts['images_copied']],
            ]
        );
    }
}
