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
 * PSR-16 Simple Cache file-based which allow long keys.
 *
 * @package SimpleComplex\Cache
 */
class KeyLongFileCache extends FileCache implements KeyLongCacheInterface
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
