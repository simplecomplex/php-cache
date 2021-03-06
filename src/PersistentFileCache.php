<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-cache/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

use SimpleComplex\Cache\Interfaces\PersistentCacheInterface;

/**
 * PSR-16 Simple Cache file-based
 * with time-to-live forever and arg ttl ignored.
 *
 * @property-read string $name
 * @property-read string $type
 * @property-read string $path
 * @property-read string $fileMode
 * @property-read int $ttlDefault
 * @property-read bool $ttlIgnore
 *
 * @package SimpleComplex\Cache
 */
class PersistentFileCache extends FileCache implements PersistentCacheInterface
{
    /**
     * Default time-to-live: zero (forever).
     *
     * Values:
     * - zero: forever.
     * - positive: seconds.
     *
     * @var int
     */
    const TTL_DEFAULT = 0;

    /**
     * Ignore ttl argument of item setters.
     *
     * @var int
     */
    const TTL_IGNORE = true;
}
