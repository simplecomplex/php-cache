<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Extension of PSR-16 Simple Cache interface.
 *
 * @see \Psr\SimpleCache\CacheInterface
 *
 * @package SimpleComplex\Cache
 */
interface ManagableCacheInterface extends CacheInterface
{
    /**
     * Check if the cache store has any items at all.
     *
     * @return bool
     */
    public function empty() : bool;

    /**
     * No time-to-live; in effect forever.
     *
     * @var int
     */
    const TTL_NONE = 0;

    /**
     * Do not consider time-to-live at all:
     * i. Ignore ttl arguments to setter and getter methods.
     * ii. All items live forever.
     *
     *
     * @var int
     */
    const TTL_IGNORE = -1;

    /**
     * Set the cache store's default time-to-live.
     *
     * This method must support constants TTL_NONE/TTL_IGNORE arg default,
     * but is free to implement the essence of these values in whatever
     * manner desired.
     *
     * @param int $default
     */
    public function setDefaultTtl(int $default);
}
