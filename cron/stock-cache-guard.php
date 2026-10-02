<?php
/**
 * Flushes the PrestaShop object cache whenever stock or prices change in the database.
 *
 * The Symfonia connector (RtnetSubSync broker) writes ps_stock_available and prices
 * straight to the database and does not invalidate the PrestaShop cache, so with
 * Memcached enabled the shop keeps serving stale stock (and lets customers buy items
 * that are already gone). This script compares a checksum of the relevant tables with
 * the previous run and flushes the object cache when anything changed.
 *
 * CLI only, run from cron as the shop user, e.g.:
 *   *\/5 * * * * /usr/bin/php74 /path/to/shop/modules/wsflushcache/cron/stock-cache-guard.php
 *
 * State and log live outside the docroot: <shop root>/../var/stock-cache-guard.{state,log}
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 404 Not Found');
    exit;
}

$shopRoot = dirname(__DIR__, 3);
$varDir = dirname($shopRoot) . '/var';
$stateFile = $varDir . '/stock-cache-guard.state';
$logFile = $varDir . '/stock-cache-guard.log';

chdir($shopRoot);
require $shopRoot . '/config/config.inc.php';

if (!is_dir($varDir) && !mkdir($varDir, 0700, true)) {
    fwrite(STDERR, "Cannot create $varDir\n");
    exit(1);
}

$prefix = _DB_PREFIX_;
$sql = "SELECT CONCAT_WS('|',
    (SELECT CONCAT(COUNT(*), ':', COALESCE(SUM(quantity), 0), ':',
        COALESCE(BIT_XOR(CRC32(CONCAT_WS(':', id_stock_available, quantity))), 0))
        FROM {$prefix}stock_available),
    (SELECT CONCAT(COUNT(*), ':',
        COALESCE(BIT_XOR(CRC32(CONCAT_WS(':', id_product, id_shop, price, wholesale_price, active))), 0))
        FROM {$prefix}product_shop),
    (SELECT CONCAT(COUNT(*), ':',
        COALESCE(BIT_XOR(CRC32(CONCAT_WS(':', id_specific_price, price, reduction, `from`, `to`))), 0))
        FROM {$prefix}specific_price)
)";

// Bypass the query cache, otherwise the checksum itself could come from Memcached.
$current = (string) Db::getInstance()->getValue($sql, false);
if ($current === '') {
    fwrite(STDERR, "Checksum query returned nothing\n");
    exit(1);
}

$previous = is_file($stateFile) ? trim((string) file_get_contents($stateFile)) : '';
if ($current === $previous) {
    exit(0);
}

// getInstance() returns null when the configured caching class does not exist
// (e.g. CacheFs on PS 8 with the cache disabled), then there is nothing to flush.
$cache = Cache::getInstance();
$flushed = $cache ? $cache->flush() : 'no object cache';
file_put_contents($stateFile, $current . PHP_EOL, LOCK_EX);
file_put_contents(
    $logFile,
    date('Y-m-d H:i:s') . ' change detected, cache flush=' . var_export($flushed, true)
        . ($previous === '' ? ' (first run)' : '') . PHP_EOL,
    FILE_APPEND | LOCK_EX
);
