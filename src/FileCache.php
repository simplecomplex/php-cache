<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-cache/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

use SimpleComplex\Utils\Explorable;
use SimpleComplex\Utils\Utils;
use SimpleComplex\Cache\Exception\LogicException;
use SimpleComplex\Cache\Exception\CacheInvalidArgumentException;
use SimpleComplex\Cache\Exception\InvalidArgumentException;
use SimpleComplex\Cache\Exception\OutOfBoundsException;
use SimpleComplex\Cache\Exception\RuntimeException;

/**
 * PSR-16 Simple Cache file-based
 * with (default) time-to-live 30 minutes and arg ttl respected.
 *
 * Not compatible with 32-bit PHP, because 32-bit integer too small to handle
 * (mock) eternal time-to-live.
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
class FileCache extends Explorable implements ManageableCacheInterface, BackupCacheInterface
{
    // \Psr\SimpleCache\CacheInterface members.---------------------------------

    /**
     * Returns cached item if exists and it's time-to-live hasn't expired.
     *
     * Deletes existing item if time-to-live and garbage collection grade period
     * both expired.
     *
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed|null
     *
     * @throws CacheInvalidArgumentException
     *      Arg key invalid.
     * @throws RuntimeException
     *      If this store's ttlDefault isn't zero, and checking file's modified
     *      time fails.
     *      If failing to read file.
     *      This store is destroyed.
     */
    public function get($key, $default = null)
    {
        if (!CacheKey::validate($key)) {
            throw new CacheInvalidArgumentException('Arg key is not valid, key[' . $key . '].');
        }
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }

        $file = $this->pathReal . '/stores/' . $this->name . '/' . $key;
        if (!file_exists($file)) {
            return $default;
        }

        // Unless time-to-live is to be ignored by all methods/procedures.
        if ($this->ttlDefault || !$this->ttlIgnore) {
            $end_of_life = filemtime($file);
            if (!$end_of_life) {
                throw new RuntimeException('Failed to get modified time of file[' . $file . '].');
            }
            $time = time();
            if ($end_of_life < $time) {
                if (
                    $end_of_life + ($this->ttlDefault ? ($this->ttlDefault * static::GARBAGE_CLLCTN_GRACE_FACTOR) :
                        static::GARBAGE_CLLCTN_GRACE_NO_DEFAULT)
                    < $time
                ) {
                    // Suppress PHP notice/warning; file_exists()+unlink() is not atomic.
                    @unlink($file);
                }
                return $default;
            }
        }

        // Suppress PHP notice/warning;
        // file_exists()+file_get_contents() is not atomic.
        $serialized = @file_get_contents($file);

        // Any serialized variable is truthy; like null: 'N;'.
        if (!$serialized) {
            if (!file_exists($file)) {
                // Apparantly the file was deleted between first call to
                // file_exists() and the call to file_get_contents().
                return $default;
            }
            throw new RuntimeException('Failed to read file[' . $file . '].');
        }

        return unserialize($serialized);
    }

    /**
     * Uses (*nix) atomic file write.
     *
     * @param string $key
     * @param mixed $value
     *      \Serializable
     * @param int|\DateInterval|null $ttl
     *      Null: uses the instance' default ttl.
     *      Zero: forever, no end of life.
     *
     * @return bool
     *
     * @throws CacheInvalidArgumentException
     *      Arg key invalid.
     * @throws RuntimeException
     *      Failing to serialize.
     *      Failing to write to file.
     *      Failing to set modified time of file.
     *      This store is destroyed.
     */
    public function set($key, $value, $ttl = null)
    {
        if (!CacheKey::validate($key)) {
            throw new CacheInvalidArgumentException('Arg key is not valid, key[' . $key . '].');
        }
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }

        $serialized = serialize($value);
        if (!$serialized) {
            throw new RuntimeException('Failed to serialize value.');
        }

        $file = $this->pathReal . (!$this->isCandidate ? '/stores/' : '/candidates/') . $this->name . '/' . $key;
        // Uses rename() because the OS equivalent mv is atomic in *nix systems.
        // @ is PSR-16 illegal in key, so usable as mock directory separator.
        if (!($tmp_file = tempnam($this->pathReal . '/tmp', $this->name . '@' . $key))) {
            throw new RuntimeException('Failed to reserve temp file.');
        }
        if (!($handle = fopen($tmp_file, 'w'))) {
            throw new RuntimeException('Failed to open temp file.');
        }
        if (!($write = fwrite($handle, $serialized))) {
            throw new RuntimeException('Failed to write to temp file.');
        }
        if (!($close = fclose($handle))) {
            throw new RuntimeException('Failed to close temp file.');
        }
        if (!rename($tmp_file, $file)) {
            throw new RuntimeException('Failed to rename temp to final cache file.');
        }
        // tempnam() writes using file mode 0600, and rename doesn't alter mode.
        if (($this->fileMode != 'user') && !chmod($file, static::FILE_MODE['file_' . $this->fileMode])) {
            throw new RuntimeException('Failed to chmod cache file[' . $file . '].');
        }

        // Unless time-to-live is to be ignored by all methods/procedures.
        if ($this->ttlDefault || !$this->ttlIgnore) {
            if (!$ttl) {
                $time_to_live = $ttl === null ? $this->ttlDefault : 0;
            } else {
                $time_to_live = $this->timeToLive($ttl);
            }
            if (!$time_to_live) {
                $time_to_live = static::TTL_FOREVER;
            }
            if (
                $time_to_live
                && !touch($file, time() + $time_to_live)
            ) {
                throw new RuntimeException('Failed to set future modified time of file[' . $file . '].');
            }
        }

        return true;
    }

    /**
     *
     * @code
     * # CLI
     * cd vendor/simplecomplex/cache/src/cli
     * php cli.phpsh cache -h
     * @endcode
     *
     * @param string $key
     *
     * @return bool
     *      Always true; no effective means of detecting error.
     *
     * @throws CacheInvalidArgumentException
     *      Arg key invalid.
     * @throws RuntimeException
     *      This store is destroyed.
     */
    public function delete($key)
    {
        if (!CacheKey::validate($key)) {
            throw new CacheInvalidArgumentException('Arg key is not valid, key[' . $key . '].');
        }
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }
        // Suppress PHP notice/warning; file_exists()+unlink() is not atomic.
        @unlink($this->pathReal . '/stores/' . $this->name . '/' . $key);
        return true;
    }

    /**
     * Clear all caches of this store.
     *
     * @return true
     *
     * @throws \UnexpectedValueException
     *      If this store's cache dir cannot be opened.
     * @throws \RuntimeException
     *      On other failures.
     * @throws RuntimeException
     *      This store is destroyed.
     */
    public function clear()
    {
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }
        $cache_dir = $this->pathReal . '/stores/' . $this->name;
        $dir_iterator = new \DirectoryIterator($cache_dir);
        foreach ($dir_iterator as $item) {
            if (!$item->isDot()) {
                @unlink($cache_dir . '/' . $item->getFilename());
            }
        }
        return true;
    }

    /**
     * @param array|object $keys
     * @param mixed|null $default
     *
     * @return array
     *
     * @throws \TypeError
     * @throws RuntimeException
     *      This store is destroyed.
     */
    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys) && !is_object($keys)) {
            throw new \TypeError('Arg keys type[' . Utils::getType($keys) . '] is not array|object.');
        }
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }
        $list = [];
        foreach ($keys as $key) {
            $list[$key] = $this->get($key, $default);
        }
        return $list;
    }

    /**
     * @param array|object $values
     * @param int|\DateInterval|null $ttl
     *      Null: uses the instance' default ttl.
     *      Zero: forever, no end of life.
     *
     * @return bool
     *
     * @throws \TypeError
     * @throws RuntimeException
     *      This store is destroyed.
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!is_array($values) && !is_object($values)) {
            throw new \TypeError('Arg values type[' . Utils::getType($values) . '] is not array|object.');
        }
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array|object $keys
     *
     * @return bool
     *
     * @throws \TypeError
     * @throws RuntimeException
     *      This store is destroyed.
     */
    public function deleteMultiple($keys)
    {
        if (!is_array($keys) && !is_object($keys)) {
            throw new \TypeError('Arg keys type[' . Utils::getType($keys) . '] is not array|object.');
        }
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }
        foreach ($keys as $key) {
            // Suppress PHP notice/warning; file_exists()+unlink() is not atomic.
            @unlink($this->pathReal . '/stores/' . $this->name . '/' . $key);
        }
        return true;
    }

    /**
     * Acknowledges cached item if exists and it's time-to-live hasn't expired.
     *
     * Deletes existing item if time-to-live and garbage collection grade period
     * both expired.
     *
     * @param string $key
     *
     * @return bool
     *
     * @throws CacheInvalidArgumentException
     * @throws RuntimeException
     *      This store is destroyed.
     */
    public function has($key)
    {
        if (!CacheKey::validate($key)) {
            throw new CacheInvalidArgumentException('Arg key is not valid, key[' . $key . '].');
        }
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }

        $file = $this->pathReal . '/stores/' . $this->name . '/' . $key;
        if (!file_exists($file)) {
            return false;
        }

        // Unless time-to-live is to be ignored by all methods/procedures.
        if ($this->ttlDefault || !$this->ttlIgnore) {
            $end_of_life = filemtime($file);
            if (!$end_of_life) {
                throw new RuntimeException('Failed to get modified time of file[' . $file . '].');
            }
            $time = time();
            if ($end_of_life < $time) {
                if (
                    $end_of_life + ($this->ttlDefault ? ($this->ttlDefault * static::GARBAGE_CLLCTN_GRACE_FACTOR) :
                        static::GARBAGE_CLLCTN_GRACE_NO_DEFAULT)
                    < $time
                ) {
                    // Suppress PHP notice/warning; file_exists()+unlink() is not atomic.
                    @unlink($file);
                }
                return false;
            }
        }

        return true;
    }


    // Explorable.--------------------------------------------------------------

    /**
     * @var array
     */
    protected $explorableIndex = [
        'name',
        'type',
        'path',
        'fileMode',
        'ttlDefault',
        'ttlIgnore',
    ];

    /**
     * @param string $name
     *
     * @return mixed
     *
     * @throws OutOfBoundsException
     *      If no such instance property.
     */
    public function __get($name)
    {
        if (in_array($name, $this->explorableIndex, true)) {
            return $this->{$name};
        }
        throw new OutOfBoundsException(get_class($this) . ' instance exposes no property[' . $name . '].');
    }

    /**
     * @param string $name
     * @param mixed|null $value
     *
     * @return void
     *
     * @throws OutOfBoundsException
     *      If no such instance property.
     * @throws RuntimeException
     *      If that instance property is read-only.
     */
    public function __set($name, $value) /*: void*/
    {
        if (in_array($name, $this->explorableIndex, true)) {
            throw new RuntimeException(get_class($this) . ' instance property[' . $name . '] is read-only.');
        }
        throw new OutOfBoundsException(get_class($this) . ' instance exposes no property[' . $name . '].');
    }

    /**
     * @see \Iterator::current()
     * @see Explorable::current()
     *
     * @return mixed
     */
    public function current()
    {
        // Override to facilitate direct call to __get(); cheaper.
        return $this->__get(current($this->explorableIndex));
    }


    // ManageableCacheInterface.-------------------------------------------------

    /**
     * Check if the cache store was defined as new at instantiation,
     * or regenerated on the basis of a previous instantiation (configuration).
     *
     * @return bool
     */
    public function isNew() : bool
    {
        return $this->isNew;
    }

    /**
     * Check if the cache store has any items at all.
     *
     * @return bool
     *
     * @throws RuntimeException
     *      This store is destroyed.
     */
    public function isEmpty() : bool
    {
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }
        $cache_dir = $this->pathReal . '/stores/' . $this->name;
        $dir_iterator = new \DirectoryIterator($cache_dir);
        foreach ($dir_iterator as $item) {
            if (!$item->isDot()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Set the cache store's default time-to-live.
     *
     * @param int|\DateInterval $ttl
     *
     * @return void
     *
     * @throws \TypeError
     *      Propagated.
     * @throws RuntimeException
     *      Propagated.
     *      This store is destroyed.
     */
    public function setTtlDefault($ttl)
    {
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }
        $time_to_live = $this->timeToLive($ttl);
        // Save to settings .ini file, if different.
        if ($time_to_live != $this->ttlDefault) {
            $this->ttlDefault = $time_to_live;
            $settings = [];
            foreach (static::SETTINGS_KEYS as $key) {
                $settings[$key] = $this->{$key};
            }
            $this->saveSettings($settings);
        }
    }

    /**
     * Control whether the cache store should ignore $ttl argument
     * of setters and getters.
     *
     * @param bool $ignore
     *
     * @return void
     *
     * @throws RuntimeException
     *      This store is destroyed.
     */
    public function setTtlIgnore(bool $ignore)
    {
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }
        // Save to settings .ini file, if different.
        if ($ignore != $this->ttlIgnore) {
            $this->ttlIgnore = $ignore;
            $settings = [];
            foreach (static::SETTINGS_KEYS as $key) {
                $settings[$key] = $this->{$key};
            }
            $this->saveSettings($settings);
        }
    }

    /**
     * Deletes all cache items that have reached end of life.
     *
     * Use a cron job to do this.
     * @code
     * php [document root]/vendor/simplecomplex/cache/src/cli/cli.phpsh cache-clear-expired --all --yes
     * @endcode
     *
     * @see simplecomplex_cache_cli()
     * @see CliCache::executeCommand()
     * @see CliCache::cmdClear()
     *
     * @return int
     *      Number of items cleared.
     *
     * @throws RuntimeException
     *      This store is destroyed.
     */
    public function clearExpired() : int
    {
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }
        $deleted = 0;
        // Unless time-to-live is to be ignored by all methods/procedures.
        if ($this->ttlDefault || !$this->ttlIgnore) {
            $grace = $this->ttlDefault ? ($this->ttlDefault * static::GARBAGE_CLLCTN_GRACE_FACTOR) :
                static::GARBAGE_CLLCTN_GRACE_NO_DEFAULT;
            $cache_dir = $this->pathReal . '/stores/' . $this->name;
            $dir_iterator = new \DirectoryIterator($cache_dir);
            foreach ($dir_iterator as $item) {
                if (
                    !$item->isDot()
                    && ($end_of_life = @$item->getMTime())
                    && $end_of_life < time() + $grace
                ) {
                    @unlink($cache_dir . '/' . $item->getFilename());
                    ++$deleted;
                }
            }
        }
        return $deleted;
    }

    /**
     * Destroys the whole cache store; it's configuration and all items.
     *
     * @see FileCache::clear()
     *
     * @return bool
     *      False on failing to clear().
     *
     * @throws RuntimeException
     *      If this store is already destroyed.
     * @throws \RuntimeException
     *      Failure to remove cache dir.
     *      Failure to delete settings file.
     */
    public function destroy()
    {
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is already destroyed, store[' . $this->name . '].');
        }
        if (!$this->clear()) {
            return false;
        }
        if (!unlink($this->pathReal . '/' . $this->name . '.ini')) {
            throw new \RuntimeException(
                'Failed to delete this store\'s settings file[' . $this->pathReal . '/' . $this->name . '.ini].'
            );
        }
        $this->destroyed = true;
        if (!rmdir($this->pathReal . '/stores/' . $this->name)) {
            throw new \RuntimeException(
                'Failed to remove this store\'s cache dir[' . $this->pathReal . '/stores/' . $this->name . '].'
            );
        }
        return true;
    }

    /**
     * Reads all non-expired and non-null cache items into a keyed array.
     *
     * @return array
     */
    public function export() : array
    {
        $collection = [];
        $store = $this->pathReal . '/stores/' . $this->name;
        $dir_iterator = new \DirectoryIterator($store);
        foreach ($dir_iterator as $item) {
            if (!$item->isDot()) {
                $key = $item->getFilename();
                $value = $this->get($key);
                if ($value !== null) {
                    $collection[$key] = $value;
                }
            }
        }
        ksort($collection);
        return $collection;
    }

    /**
     * Backup the whole cache store.
     *
     * @param string $backupName
     *
     * @return int
     *      Number of files copied.
     *
     * @throws RuntimeException
     * @throws \Throwable
     *      Propagated.
     */
    public function backup(string $backupName)
    {
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }

        $utils = Utils::getInstance();

        // Ensure that stores dir exists.
        // Paranoid (constructor checks), but anyway.
        $dir_original = $this->pathReal . '/stores/' . $this->name;
        if (!file_exists($dir_original)) {
            throw new RuntimeException('This cache store\'s store dir doesn\'t exist, store[' . $this->name . '].');
        }

        // Ensure backup dir.
        $dir_backup = $this->pathReal . '/backup/' . $this->name;
        if (file_exists($dir_backup . '/' . $backupName)) {
            throw new RuntimeException(
                'That backup already exists, store[' . $this->name . '], arg backupName[' . $backupName . '].'
            );
        }
        $dir_backup .= '/' . $backupName;
        $utils->ensurePath($dir_backup, static::FILE_MODE['dir_' . $this->fileMode]);

        $file_group_write = $this->fileMode != 'user';
        $file_mode = static::FILE_MODE['file_' . $this->fileMode];

        // Unless time-to-live is to be ignored by all methods/procedures.
        $clone_modified = $this->ttlDefault || !$this->ttlIgnore;

        $n_items = $modified = 0;
        $dir_iterator = new \DirectoryIterator($dir_original);
        foreach ($dir_iterator as $item) {
            if (!$item->isDot()) {
                $filename = $item->getFilename();
                if ($clone_modified) {
                    $modified = $item->getMTime();
                }
                $file_backup = $dir_backup . '/' . $filename;
                if (!@copy($dir_original . '/' . $filename, $file_backup)) {
                    throw new RuntimeException(
                        'Failed to copy original[' . $dir_original . '/' . $filename
                        . '] to backup[' . $file_backup . '].'
                    );
                }
                if (
                    $file_group_write && !@chmod($file_backup, $file_mode)
                    // Don't err is already group-write, set gid is less important.
                    && !$utils->isFileGroupWrite(fileperms($file_backup))
                ) {
                    throw new RuntimeException('Failed to chmod backup file[' . $file_backup . '].');
                }
                if ($clone_modified && !@touch($file_backup, $modified)) {
                    throw new RuntimeException('Failed to set modified time of backup file[' . $file_backup . '].');
                }
                ++$n_items;
            }
        }

        return $n_items;
    }

    /**
     * Restore the whole cache store from a backup.
     *
     * @param string $backupName
     *
     * @return bool
     */
    public function restore(string $backupName)
    {
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }

        $utils = Utils::getInstance();

        // Ensure that stores dir exists.
        // Paranoid (constructor checks), but anyway.
        $dir_original = $this->pathReal . '/stores/' . $this->name;
        if (!file_exists($dir_original)) {
            throw new RuntimeException('This cache store\'s store dir doesn\'t exist, store[' . $this->name . '].');
        }

        // Ensure backup dir.
        $dir_backup = $this->pathReal . '/backup/' . $this->name . '/' . $backupName;
        if (!file_exists($dir_backup)) {
            throw new RuntimeException(
                'That backup doesn\'t exist, store[' . $this->name . '], arg backupName[' . $backupName . '].'
            );
        }

        // Clear current.
        if (!$this->clear()) {
            throw new RuntimeException('Failed to clear store[' . $this->name . '].');
        }
        if (!@rmdir($dir_original)) {
            throw new RuntimeException('Failed to remove store dir, store[' . $this->name . '].');
        }

        $filemode_dir = static::FILE_MODE['dir_' . $this->fileMode];
        $dir_group_write = $utils->isFileGroupWrite($filemode_dir);

        if (!@rename($dir_backup, $dir_original)) {
            throw new RuntimeException('Failed to move backup to store, store[' . $this->name . '].');
        }
        if (
            $dir_group_write && !@chmod($dir_original, $filemode_dir)
            // Don't err is already group-write, set gid is less important.
            && !$utils->isFileGroupWrite(fileperms($dir_original))
        ) {
            throw new RuntimeException('Failed to chmod store dir, store[' . $this->name . '].');
        }

        return true;
    }

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
     *      Propagated.
     */
    public function setCandidate() /*: void*/
    {
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }
        $utils = Utils::getInstance();
        // Ensure candidates dir.
        $dir = $this->pathReal . '/candidates/' . $this->name;
        if (!file_exists($dir)) {
            $utils->ensurePath($dir, static::FILE_MODE['dir_' . $this->fileMode]);
        }
        $this->isCandidate = true;
    }

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
     * @throws RuntimeException
     * @throws \Throwable
     *      Propagated.
     */
    public function promoteCandidate(string $backupName) : bool
    {
        if ($this->destroyed) {
            throw new RuntimeException('This cache store is destroyed, store[' . $this->name . '].');
        }
        $utils = Utils::getInstance();

        $filemode_dir = static::FILE_MODE['dir_' . $this->fileMode];
        $dir_group_write = $utils->isFileGroupWrite($filemode_dir);

        // Ensure that stores dir exists.
        // Paranoid (constructor checks), but anyway.
        $dir_original = $this->pathReal . '/stores/' . $this->name;
        if (!file_exists($dir_original)) {
            throw new RuntimeException('This cache store\'s store dir doesn\'t exist, store[' . $this->name . '].');
        }

        // Ensure backup dir.
        $dir_backup = $this->pathReal . '/backup/' . $this->name;
        if (file_exists($dir_backup . '/' . $backupName)) {
            throw new RuntimeException(
                'That backup already exists, store[' . $this->name . '], arg backupName[' . $backupName . '].'
            );
        }
        $utils->ensurePath($dir_backup, $filemode_dir);
        $dir_backup .= '/' . $backupName;

        // Check that the candidate exists.
        $dir_candidate = $this->pathReal . '/candidates/' . $this->name;
        if (!file_exists($dir_candidate)) {
            return false;
        }

        // Check that candidate isn't empty.
        $non_empty = false;
        $dir_iterator = new \DirectoryIterator($dir_candidate);
        foreach ($dir_iterator as $item) {
            if (!$item->isDot()) {
                $non_empty = true;
                break;
            }
        }
        if (!$non_empty) {
            throw new RuntimeException('This cache store\'s candidate is empty, store[' . $this->name . '].');
        }

        // Move current to backup.
        if (!@rename($dir_original, $dir_backup)) {
            throw new RuntimeException('Failed to move store to backup, store[' . $this->name . '].');
        }
        if (
            $dir_group_write && !@chmod($dir_backup, $filemode_dir)
            // Don't err is already group-write, set gid is less important.
            && !$utils->isFileGroupWrite(fileperms($dir_backup))
        ) {
            throw new RuntimeException('Failed to chmod backup dir, store[' . $this->name . '].');
        }

        // Move candidate to current.
        if (!@rename($dir_candidate, $dir_original)) {
            throw new RuntimeException('Failed to move candidate to current, store[' . $this->name . '].');
        }
        if (
            $dir_group_write && !@chmod($dir_backup, $filemode_dir)
            // Don't err is already group-write, set gid is less important.
            && !$utils->isFileGroupWrite(fileperms($dir_backup))
        ) {
            throw new RuntimeException('Failed to chmod store dir, store[' . $this->name . '].');
        }

        return true;
    }

    // Custom.------------------------------------------------------------------

    /**
     * File path where an instance' settings and cache files should be stored.
     *
     * Relative path is considered relative to document root.
     *
     * Will eventually contain:
     * - /stores/[some store name]/[...cache files]
     * - /tmp
     * - [some store name].ini
     *
     * @var string
     */
    const PATH_DEFAULT = '../private/lib/simplecomplex/file-cache';

    /**
     * File modes for directory/file writing, using respectively user-only,
     * group read/write/execute and group plus set group id.
     *
     * Must be octal integers, with leading zero, nothing else seem to work;
     * even if decoct/octdec() juggling.
     *
     * @var int[]
     */
    const FILE_MODE = [
        'dir_user' => 0700,
        'file_user' => 0600,
        'dir_group' => 0770,
        'file_group' => 0660,
        'dir_group_setgid' => 02770,
        'file_group_setgid' => 02660,
    ];

    /**
     * Values: user|group|group_setgid.
     *
     * @var string
     */
    const FILE_MODE_DEFAULT = 'user';

    /**
     * There's no such thing as eternity.
     *
     * Incompatible with 32-bit PHP.
     *
     * @var int
     */
    const TTL_FOREVER = 1000 * 365 * 24 * 60 * 60;

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
     * Don't ignore ttl argument of item setters and getters.
     *
     * Ignore time-to-live completely, if ignore AND ttl default none (forever).
     *
     * @var int
     */
    const TTL_IGNORE = false;

    /**
     * Garbage collection grace is - when non-zero default time-to-live -
     * this value times default time-to-live.
     *
     * An expired item still within the grace period will not be deleted;
     * by get(), has(), clearExpired().
     * May reduce the risk of current saving/deleting procedures.
     *
     * The value of 0.5 (half of default time-to-live) is chosen because
     * a grace period that's a multiple of time-to-live should be avoided.
     *
     * @see FileCache::TTL_DEFAULT
     * @see FileCache::get()
     * @see FileCache::has()
     * @see FileCache::clearExpired()
     *
     * @var int
     */
    const GARBAGE_CLLCTN_GRACE_FACTOR = 0.5;

    /**
     * Garbage collection grace used when no default time-to-live.
     *
     * An expired item still within the grace period will not be deleted;
     * by get(), has(), clearExpired().
     * May reduce the risk of current saving/deleting procedures.
     *
     * This value must not be a multiple of common set() ttl arg values
     * (for the particular cache store).
     *
     * @see FileCache::TTL_DEFAULT
     * @see FileCache::get()
     * @see FileCache::has()
     * @see FileCache::clearExpired()
     *
     * @var int
     */
    const GARBAGE_CLLCTN_GRACE_NO_DEFAULT = 15 * 60;

    /**
     * Keys of the settings (and equivalent instance vars) saved to the store's
     * .ini file.
     *
     * @var string[]
     */
    const SETTINGS_KEYS = [
        'fileMode',
        'ttlIgnore',
        'ttlDefault',
    ];

    /**
     * Cache store name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Cache store type.
     *
     * @var string
     */
    protected $type = 'file';

    /**
     * Path given by argument or class default; absolute
     * or relative to document root.
     *
     * @var string
     */
    protected $path = '';

    /**
     * Final absolute path.
     *
     * @var string
     */
    protected $pathReal = '';

    /**
     * Values: user|group|group_setgid.
     *
     * Gets set by constructor via resolveSettings().
     * @see FileCache::resolveSettings()
     *
     * @var string
     */
    protected $fileMode;

    /**
     * Default time-to-live.
     *
     * Values:
     * - zero: forever, used when set method ttl arg null.
     * - positive: used when set method ttl arg null.
     *
     * Gets set by constructor via resolveSettings().
     * @see FileCache::resolveSettings()
     *
     * @var int
     */
    protected $ttlDefault;

    /**
     * Ignore ttl argument of item setters and getters.
     *
     * Ignore time-to-live completely, if ignore AND ttl default none (forever).
     *
     * Gets set by constructor via resolveSettings().
     * @see FileCache::resolveSettings()
     *
     * @var bool
     */
    protected $ttlIgnore;

    /**
     * True upon destroy().
     *
     * @see FileCache::destroy()
     *
     * @var bool
     */
    protected $destroyed = false;

    /**
     * Whether the cache store was defined as new at instantiation,
     * or regenerated on the base of a previous instantiation (configuration).
     *
     * @var bool
     */
    protected $isNew = false;

    /**
     * Write to candidate storage instead of the normal.
     *
     * @var bool
     */
    protected $isCandidate = false;

    /**
     * Create or load cache store.
     *
     * Keeps settings in an .ini file placed along with the cache dir.
     * Optimized for fast regeneration/load, using existing settings.
     * Passing options makes things slower - at _every_ instantiation.
     * Instead, it's recommended to extend this class, overriding class vars
     * TTL_DEFAULT/TTL_IGNORE.
     *
     * @param string $name
     * @param array $options {
     *      @var string $path = ''
     *          Empty: class default (PATH_DEFAULT) rules.
     *      @var string $fileMode = ''
     *          Empty: preexisting setting or class default (FILE_MODE_DEFAULT)
     *          rules.
     *          File mode will only apply fully to all aspects of dir/file
     *          writing the first time this cache store gets created. Later it
     *          will only apply to new cache files.
     *      @var int|\DateInterval|null $ttlDefault = null
     *          Null: preexisting setting or class default (TTL_DEFAULT) rules.
     *          Zero: forever.
     *      @var bool|null $ttlIgnore = null
     *          Null: preexisting setting or class default (TTL_IGNORE) rules.
     *          True: item setters and getters' ttl argument gets ignored.
     * }
     * @throws \LogicException
     *      If PHP is less than 64-bit.
     * @throws InvalidArgumentException
     *      Invalid arg name.
     * @throws \TypeError
     *      Wrong type of arg options bucket.
     * @throws \Throwable
     *      Propagated.
     */
    public function __construct(string $name, array $options = [])
    {
        if (PHP_INT_SIZE < 8) {
            throw new \LogicException(
                get_class($this) . ' requires at least 64-bit PHP.'
            );
        }
        if (!CacheKey::validate($name)) {
            throw new InvalidArgumentException('Arg name is empty or contains illegal char(s), name['
                . $name . '].');
        }
        $this->name = $name;

        if (!empty($options['path'])) {
            if (!is_string($options['path'])) {
                throw new \TypeError('Arg options[path] type[' . Utils::getType($options['path']) . '] is not string.');
            }
            $this->path = $options['path'];
        } else {
            $this->path = static::PATH_DEFAULT;
        }

        // Resolve path.
        // If path exists, try loading previously saved settings.
        // Otherwise do nothing.
        $settings = $this->resolvePath() ? $this->loadSettings() : [];

        // Resolve options and final instance var values, and figure out if we
        // need to update filed settings.
        $save_settings = $this->resolveSettings($settings, $options);

        // Create store dir, cache dir and tmp dir, if they don't exist.
        $this->ensureDirectories();

        // Save/update settings.
        if ($save_settings) {
            $this->saveSettings($settings);
        }
    }

    /**
     * Compares pre-existing settings with passed options, and sets instance vars
     * accordingly.
     *
     * Separated from from constructor to accommodate class extension.
     *
     * @param array &$settings
     *      By reference.
     * @param array $options
     *
     * @return bool
     *      True: No pre-existing settings, or an option passed differs
     *          from the existing setting.
     *
     * @throws LogicException
     *      Pre-existing settings misses a bucket.
     * @throws \TypeError
     *      Bad options bucket.
     */
    protected function resolveSettings(array &$settings, array $options) /*: void*/
    {
        if ($settings) {
            foreach (static::SETTINGS_KEYS as $key) {
                if (!isset($settings[$key])) {
                    throw new LogicException('Loaded non-empty settings misses \'' . $key . '\' bucket.');
                }
                $this->{$key} = $settings[$key];
            }
            // Fast lane for regenerated store;
            // pre-existing settings + no options passed.
            if (!$options) {
                // No reason to save settings.
                return false;
            }

            $settings_exist = true;
            // No reason to save settings - so far.
            $diff = false;
        } else {
            $settings['fileMode'] = $this->fileMode = static::FILE_MODE_DEFAULT;
            $settings['ttlDefault'] = $this->ttlDefault = static::TTL_DEFAULT;
            $settings['ttlIgnore'] = $this->ttlIgnore = static::TTL_IGNORE;
            // Fast lane for entirely new store;
            // no pre-existing settings + no options passed.
            if (!$options) {
                // Settings must be saved, because there are no pre-existing.
                return true;
            }

            $settings_exist = false;
            // Settings must be saved, because there are no pre-existing.
            $diff = true;
        }

        // fileMode.
        if (!empty($options['fileMode'])) {
            // empty() also handles null;
            // that existing setting (or class default) must rule.
            if (!is_string($options['fileMode'])) {
                throw new \TypeError(
                    'Arg options[fileMode] type[' . Utils::getType($options['fileMode']) . '] is not string.'
                );
            }
            switch ($options['fileMode']) {
                case 'user':
                case 'group':
                case 'group_setgid':
                    $this->fileMode = $options['fileMode'];
                    if (!$settings_exist || $settings['fileMode'] != $this->fileMode) {
                        $diff = true;
                        $settings['fileMode'] = $this->fileMode;
                    }
                    break;
                default:
                    throw new InvalidArgumentException(
                        'Arg fileMode must be user|group|group_setgid or empty, fileMode[' . $options['fileMode'] . '].'
                    );
            }
        }

        // ttlDefault.
        if (isset($options['ttlDefault'])) {
            // isset() also handles null;
            //that existing setting (or class default) must rule.
            if (!$options['ttlDefault']) {
                $this->ttlDefault = 0;
            } else {
                $this->ttlDefault = $this->timeToLive($options['ttlDefault']);
            }
            if (!$settings_exist || $settings['ttlDefault'] != $this->ttlDefault) {
                $diff = true;
                $settings['ttlDefault'] = $this->ttlDefault;
            }
        }

        // ttlIgnore.
        if (isset($options['ttlIgnore'])) {
            // isset() also handles null;
            // that existing setting (or class default) must rule.
            $this->ttlIgnore = !!$options['ttlIgnore'];
            if (!$settings_exist || $settings['ttlIgnore'] != $this->ttlIgnore) {
                $diff = true;
                $settings['ttlIgnore'] = $this->ttlIgnore;
            }
        }

        return $diff;
    }

    /**
     * Resolve path and check if exists.
     *
     * @return bool
     *      Whether the path exists already.
     *
     * @throws RuntimeException
     * @throws \Throwable
     *      Propagated.
     */
    protected function resolvePath() : bool
    {
        $this->pathReal = Utils::getInstance()->resolvePath($this->path);

        if (file_exists($this->pathReal)) {
            if (!is_dir($this->pathReal)) {
                throw new RuntimeException('Path exists but is not directory, path[' . $this->pathReal . ']');
            }
            return true;
        }
        return false;
    }

    /**
     * Ensures this class' (writable) path, tmp, backup and stores dir.
     *
     * @see FileCache::__construct()
     * @see FileCache::resolvePath()
     * @see Utils::ensurePath()
     *
     * @return void
     *
     * @throws \Throwable
     *      Propagated, various types from Utils::ensurePath().
     */
    protected function ensureDirectories() /*: void*/
    {
        $utils = Utils::getInstance();
        // Ensure cache dir.
        $dir = $this->pathReal . '/stores/' . $this->name;
        if (!file_exists($dir)) {
            // Seems to be an entirely new cache store.
            $this->isNew = true;
            $utils->ensurePath($dir, static::FILE_MODE['dir_' . $this->fileMode]);
        }
        // Ensure general tmp dir.
        $dir = $this->pathReal . '/tmp';
        if (!file_exists($dir)) {
            $utils->ensurePath($dir, static::FILE_MODE['dir_' . $this->fileMode]);
        }
    }

    /**
     * Load previously created settings of this store, if it already exists.
     *
     * @see FileCache::__construct()
     *
     * @return array
     */
    protected function loadSettings()
    {
        $file = $this->pathReal . '/' . $this->name . '.ini';
        if (file_exists($file)) {
            // Not an error if empty; the file may have been save previously
            // as empty (due to an error or deliberately).
            return Utils::getInstance()->parseIniFile($file, false, true);
        }
        // An entirely new store.
        $this->isNew = true;

        return [];
    }

    /**
     * @param array $settings
     *
     * @return void
     *
     * @throws RuntimeException
     *      Failing to write settings to file.
     */
    protected function saveSettings(array $settings) /*: void*/
    {
        $utils = Utils::getInstance();
        $file = $this->pathReal . '/' . $this->name . '.ini';
        $content = $utils->containerToIniString($settings);
        $set_mode = $this->fileMode != 'user' && !file_exists($file);
        if (!file_put_contents($file, $content)) {
            throw new RuntimeException('Failed to write store settings to file[' . $file . '].');
        }
        if (
            $set_mode
            && !@chmod($file, static::FILE_MODE['file_' . $this->fileMode])
            // Don't err is already group-write, set gid is less important.
            && !$utils->isFileGroupWrite(fileperms($file))
        ) {
            throw new RuntimeException('Failed to chmod settings file[' . $file . '].');
        }
    }

    /**
     * Average, and not exact; doesn't consider the year-divisible-by-400 rule.
     *
     * @var float
     */
    const DAYS_OF_YEAR = 365.25;

    /**
     * Flawed leap year implementation; uses an average, doesn't check if any
     * of current year(s) is leap year.
     *
     * @param int|\DateInterval|null $ttl
     *      Non-empty must be non-negative.
     *
     * @return int
     *      Seconds.
     *
     * @throws \TypeError
     * @throws RuntimeException
     *      Arg ttl resolves to negative integer.
     */
    protected function timeToLive($ttl) : int
    {
        if ($ttl) {
            if (is_int($ttl)) {
                if ($ttl < 0) {
                    throw new RuntimeException('Time-to-live cannot be negative, saw int[' . $ttl . '].');
                }
                return $ttl;
            }
            if (is_a($ttl, \DateInterval::class)) {
                $secs = (int) floor(
                        + ($ttl->y * static::DAYS_OF_YEAR * 24 * 60 * 60)
                        + ($ttl->m * (static::DAYS_OF_YEAR / 12) * 24 * 60 * 60)
                        + ($ttl->d * 24 * 60 * 60)
                        + ($ttl->h * 60 * 60)
                        + ($ttl->i * 60)
                        + $ttl->s
                    ) * (!$ttl->invert ? 1 : -1);
                if ($secs < 0) {
                    throw new RuntimeException('Time-to-live cannot be negative, saw DateInterval['
                        . join(', ', array(
                            'y' => $ttl->y,
                            'm' => $ttl->m,
                            'd' => $ttl->d,
                            'h' => $ttl->h,
                            'i' => $ttl->i,
                            's' => $ttl->s,
                            'invert' => $ttl->invert,
                        ))
                        . '].'
                    );
                }
                return $secs;
            }
            throw new \TypeError('Time-to-live must be integer, DateInterval or null.');
        }
        return 0;
    }

    /**
     * Finds all stores that has been created using that path,
     * instantiates them, and returns a list of them.
     *
     * Looks for [store name].ini files in the path.
     *
     * The instances will/should be of this class or an extending class.
     *
     * @see FileCache::PATH_DEFAULT
     *
     * @param string $path
     *      Defaults to class var PATH_DEFAULT.
     *
     * @return array
     */
    public static function listInstances($path = '')
    {
        $absolute_path = Utils::getInstance()->resolvePath($path ? $path : static::PATH_DEFAULT);
        if (file_exists($absolute_path)) {
            if (!is_dir($absolute_path)) {
                throw new RuntimeException('Path exists but is not directory, path[' . $absolute_path . ']');
            }
            $instances = [];
            $dir_iterator = new \DirectoryIterator($absolute_path);
            foreach ($dir_iterator as $item) {
                if (!$item->isDot() && $item->getExtension() == 'ini') {
                    $name = $item->getBasename('.ini');
                    $instances[] = new static($name);
                }
            }
            return $instances;
        }
        return [];
    }
}
