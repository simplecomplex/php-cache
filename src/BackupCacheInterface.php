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
 * Extension of PSR-16 Simple Cache interface, requiring backup related methods.
 *
 * @see \Psr\SimpleCache\CacheInterface
 *
 * @package SimpleComplex\Cache
 */
interface BackupCacheInterface extends CacheInterface
{
    /**
     * Backup the whole cache store.
     *
     * @param string $backupName
     *
     * @return int|bool
     *      Number of items, or true.
     *
     * @throws \Throwable
     *      On error.
     */
    public function backup(string $backupName);

    /**
     * Restore the whole cache store from a backup.
     *
     * @param string $backupName
     *
     * @return int|bool
     *      Number of items, or true.
     */
    public function restore(string $backupName);

    /**
     * Make setters write to a 'candidate' physical store instead of the normal
     * store.
     *
     * Facilitates safe mode cache building. Build a new cache, but don't use
     * it until all items (delivered by a third party, like configuration)
     * have been set.
     *
     * @return void
     *
     * @throws \Throwable
     *      On error.
     */
    public function setCandidate();

    /**
     * Backup normal physical store, and replace it with a candidate store.
     *
     * Facilitates safe mode cache building. Build a new cache, but don't use
     * it until all items (delivered by a third party, like configuration)
     * have been set.
     *
     * @param string $backupName
     *
     * @return bool
     *      False: Candidate for this store doesn't exist.
     *
     * @throws \Throwable
     *      On error.
     */
    public function promoteCandidate(string $backupName);
}
