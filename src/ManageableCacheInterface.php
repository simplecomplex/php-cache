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
 * Extension of PSR-16 Simple Cache interface, requiring cache managing methods.
 *
 * @see \Psr\SimpleCache\CacheInterface
 *
 * @package SimpleComplex\Cache
 */
interface ManageableCacheInterface extends CacheInterface
{
    // @todo: isNew() method.
    // @todo: alterSettings() method.
    /**
     * Check if the cache store has any items at all.
     *
     * Not named empty() to prevent potential future conflict with 'magic'
     * container method.
     *
     * @return bool
     *
     * @throws \Throwable
     *      On error.
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
     *
     * @throws \Throwable
     *      On error.
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
     *
     * @throws \Throwable
     *      On error.
     */
    public function clearExpired();

    /**
     * Destroys the whole cache store; it's configuration and all items.
     *
     * Must call clear() before removind store configuration.
     *
     * @see CacheInterface::clear()
     *
     * @return bool
     *
     * @throws \Throwable
     *      On error.
     */
    public function destroy();

    /**
     * Reads all non-expired and non-null cache items into a keyed array.
     *
     * @return array
     */
    public function export();

    /**
     * Finds all stores created via this class (or at least all instances
     * related to some configuration),
     * instantiates them, and returns a list of them.
     *
     * Static methods make little sense in interface.
     * On the other hand, it wouldn't make sense to implement such a method
     * as an instance method.
     * And this method is an inevitable requirement if cache stores are to be
     * manageable.
     *
     * @return mixed
     */
    public static function listInstances();
}
