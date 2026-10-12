<?php
/**
 * Keeps getRow() consistent with and without the object cache.
 *
 * Cache::setQuery() stores an empty result as [], so a cache hit returns []
 * where an uncached call returns false, and getValue() turns that into null.
 * Core code checks for === false, e.g. Product::getIdProductAttributeByIdAttributes():
 * with Memcached on, the "closest combination" fallback never ran and changing
 * one attribute group on the product page snapped back to the default combination.
 */
abstract class Db extends DbCore
{
    public function getRow($sql, $use_cache = true)
    {
        $result = parent::getRow($sql, $use_cache);

        return $result === [] ? false : $result;
    }
}
