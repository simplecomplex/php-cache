<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-cache/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

use SimpleComplex\Cache\Interfaces\FixedTtlCacheInterface;

/**
 * PSR-16 Simple Cache file-based
 * with time-to-live 30 minutes and arg ttl ignored.
 *
 * @package SimpleComplex\Cache
 */
class FixedTtlFileCache extends FileCache implements FixedTtlCacheInterface
{
    /**
     * Default time-to-live: 30 minutes.
     *
     * 30 minutes should be compatible with common session timeout;
     * 24 or 30 minutes.
     *
     * Values:
     * - zero: forever.
     * - positive: seconds.
     *
     * @var int
     */
    const TTL_DEFAULT = 30 * 60;

    /**
     * Ignore ttl argument of item setters.
     *
     * @var int
     */
    const TTL_IGNORE = true;
}
