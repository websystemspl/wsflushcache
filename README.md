# wsflushcache

PrestaShop module for holdentalesklep.eu.

- **Back office button** (Advanced Parameters > Performance): flushes the object cache,
  Smarty, XML, media and the module index in one click.
- **`cron/stock-cache-guard.php`**: automatic cache flush after stock or price changes.
  The Symfonia connector (RtnetSubSync) writes to the database without invalidating the
  PrestaShop cache, so with Memcached on the shop shows stale stock. The guard checksums
  `ps_stock_available`, `ps_product_shop` and `ps_specific_price` and flushes the object
  cache when they change.

## Cron

Run as the shop user, every 5 minutes:

```
*/5 * * * * /usr/bin/php74 /home/holdental/public_html/modules/wsflushcache/cron/stock-cache-guard.php >/dev/null 2>&1
```

State and log are kept outside the docroot in `<shop root>/../var/`
(`stock-cache-guard.state`, `stock-cache-guard.log`). The script refuses to run over HTTP.
With the object cache disabled (dev) it only records the checksum.

## Db::getRow() override

`override/classes/db/Db.php` fixes a core inconsistency of the query cache: a cached empty
`getRow()` result comes back as `[]` instead of `false`, and `getValue()` then returns
`null`. `Product::getIdProductAttributeByIdAttributes()` checks for `=== false`, so with
Memcached on, picking a value that has no combination with the other selected attributes
(e.g. "Rodzaj: Opaque Dentin" while "Kolor: A1B" is selected) reset the product page to
the default combination instead of finding the closest one. Installed on module install,
or by `upgrade/upgrade-1.0.4.php` on existing shops.
