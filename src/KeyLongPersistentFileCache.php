<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-cache/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

use SimpleComplex\Cache\Interfaces\KeyLongCacheInterface;

/**
 * Persistent file cache which allow long keys.
 *
 * @package SimpleComplex\Cache
 */
class KeyLongPersistentFileCache extends PersistentFileCache implements KeyLongCacheInterface
{
    /**
     * @uses CacheKey::validate()
     *
     * @param string $key
     *
     * @return bool
     */
    public function validateKey(string $key)
    {
        return CacheKeyLong::validate($key);
    }
}
