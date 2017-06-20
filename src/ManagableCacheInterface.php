<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-cache/blob/master/LICENSE (MIT License)
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
     * Not named empty() to prevent potential future conflict with 'magic'
     * container method.
     *
     * @return bool
     */
    public function isEmpty() : bool;

    /**
     * No time-to-live; in effect forever.
     *
     * @var int
     */
    const TTL_NONE = 0;

    /**
     * Set the cache store's default time-to-live.
     *
     * @param int|\DateInterval $ttl
     *
     * @return void
     *
     * @throws \TypeError
     *      If arg ttl isn't integer or DateInterval.
     * @throws \InvalidArgumentException
     *      If arg ttl is negative integer.
     */
    public function setTtlDefault($ttl);

    /**
     * Control whether the cache store should ignore $ttl argument
     * of item setters and getters.
     *
     * Implementations are furthermore allowed to ignore time-to-live
     * completely if ignore AND ttl default is none (forever).
     *
     * @param bool $ignore
     *
     * @return void
     */
    public function setTtlIgnore(bool $ignore);

    /**
     * Deletes all cache items that have reached end of life.
     *
     * Implementations may chose to return number of cleared items
     * or boolean.
     *
     * @return int|bool
     *      Int: number of items cleared.
     *      Bool: success/failure.
     */
    public function clearExpired();
}
