<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

use SimpleComplex\Utils\Explorable;
use SimpleComplex\Utils\Utils;
use SimpleComplex\Cache\Exception\CacheInvalidArgumentException;
use SimpleComplex\Cache\Exception\InvalidArgumentException;
use SimpleComplex\Cache\Exception\OutOfBoundsException;
use SimpleComplex\Cache\Exception\RuntimeException;

/**
 * PSR-16 Simple Cache file-based.
 *
 * @property-read string $name
 * @property-read string $type
 * @property-read string $path
 * @property-read string $fileMode
 * @property-read int $ttlDefault
 *
 * @package SimpleComplex\Cache
 */
class FileCache extends Explorable implements ManagableCacheInterface
{
    // \Psr\SimpleCache\CacheInterface members.---------------------------------

    /**
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
     */
    public function get($key, $default = null)
    {
        if (!CacheKey::validate($key)) {
            throw new CacheInvalidArgumentException('Arg key is not valid, key[' . $key . '].');
        }

        $file = $this->pathReal . '/stores/' . $this->name . '/' . $key;
        if (!file_exists($file)) {
            return $default;
        }

        // Unless time-to-live is to be ignored by all methods/procedures.
        if ($this->ttlDefault > -1) {
            $end_of_life = filemtime($file);
            if (!$end_of_life) {
                throw new RuntimeException('Failed to get modified time of file[' . $file . '].');
            }
            if ($end_of_life < time()) {
                // Old.
                // Suppress PHP notice/warning; file_exists()+unlink() is not atomic.
                @unlink($file);

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
     */
    public function set($key, $value, $ttl = null)
    {
        if (!CacheKey::validate($key)) {
            throw new CacheInvalidArgumentException('Arg key is not valid, key[' . $key . '].');
        }

        $serialized = serialize($value);
        if (!$serialized) {
            throw new RuntimeException('Failed to serialize value.');
        }

        $file = $this->pathReal . '/stores/' . $this->name . '/' . $key;
        // Uses rename() because that's atomic in *nix systems.
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
        if (($this->fileMode != 'user' || static::FILE_MODE['file_user'] != 0600)
            && !chmod($file, static::FILE_MODE['file_' . $this->fileMode])
        ) {
            throw new RuntimeException('Failed to chmod cache file[' . $file . '].');
        }

        // Unless time-to-live is to be ignored by all methods/procedures.
        if ($this->ttlDefault > -1) {
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
                throw new RuntimeException('Failed to set future modified time of[' . $file . '].');
            }
        }

        return true;
    }

    /**
     * @param string $key
     *
     * @return bool
     *      Always true; no effective means of detecting error.
     *
     * @throws CacheInvalidArgumentException
     *      Arg key invalid.
     */
    public function delete($key)
    {
        if (!CacheKey::validate($key)) {
            throw new CacheInvalidArgumentException('Arg key is not valid, key[' . $key . '].');
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
     */
    public function clear()
    {
        $cache_dir = $this->pathReal . '/stores/' . $this->name;
        $dir_iterator = new \DirectoryIterator($cache_dir);
        foreach ($dir_iterator as $item) {
            if (!$item->isDot()) {
                unlink($cache_dir . '/' . $item->getFilename());
            }
        }
        return true;
    }

    /**
     * @param iterable $keys
     * @param mixed|null $default
     *
     * @return array
     *
     * @throws \TypeError
     */
    public function getMultiple($keys, $default = null)
    {
        if (!Utils::getInstance()->isIterable($keys)) {
            throw new \TypeError(
                'Arg keys type[' . (!is_object($keys) ? gettype($keys) : get_class($keys)) . '] is not iterable.'
            );
        }
        $list = [];
        foreach ($keys as $key) {
            $list[$key] = $this->get($key, $default);
        }
        return $list;
    }

    /**
     * @param iterable $values
     * @param int|\DateInterval|null $ttl
     *      Null: uses the instance' default ttl.
     *      Zero: forever, no end of life.
     *
     * @return bool
     *
     * @throws \TypeError
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!Utils::getInstance()->isIterable($values)) {
            throw new \TypeError(
                'Arg values type[' . (!is_object($values) ? gettype($values) : get_class($values))
                . '] is not iterable.'
            );
        }
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param iterable $keys
     *
     * @return bool
     *
     * @throws \TypeError
     */
    public function deleteMultiple($keys)
    {
        if (!Utils::getInstance()->isIterable($keys)) {
            throw new \TypeError(
                'Arg keys type[' . (!is_object($keys) ? gettype($keys) : get_class($keys)) . '] is not iterable.'
            );
        }
        foreach ($keys as $key) {
            // Suppress PHP notice/warning; file_exists()+unlink() is not atomic.
            @unlink($this->pathReal . '/stores/' . $this->name . '/' . $key);
        }
        return true;
    }

    /**
     * Does not consider time-to-live, even if this instance has a default ttl.
     *
     * @param string $key
     *
     * @return bool
     *
     * @throws CacheInvalidArgumentException
     */
    public function has($key)
    {
        if (!CacheKey::validate($key)) {
            throw new CacheInvalidArgumentException('Arg key is not valid, key[' . $key . '].');
        }
        return file_exists($this->pathReal . '/stores/' . $this->name . '/' . $key);
    }


    // Explorable.--------------------------------------------------------------

    protected $explorableIndex = [
        'name',
        'type',
        'path',
        'fileMode',
        'ttlDefault',
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
        switch ('' . $name) {
            case 'name':
            case 'type':
            case 'path':
            case 'fileMode':
            case 'ttlDefault':
                return $this->{'' . $name};
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
        switch ('' . $name) {
            case 'name':
            case 'type':
            case 'ttlDefault':
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


    // ManagableCacheInterface.-------------------------------------------------

    /**
     * Check if the cache store has any items at all.
     *
     * @return bool
     */
    public function empty() : bool
    {
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
     * @param int $default
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function setDefaultTtl(int $default) /*: void*/
    {
        switch ($default) {
            case ManagableCacheInterface::TTL_IGNORE:
                break;
            case ManagableCacheInterface::TTL_NONE:
                break;
            default:
                if ($default < 0) {
                    throw new InvalidArgumentException(

                    );
                }
        }
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
     * Must be octals integers, with leading zero, nothing else seem to work;
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
     * Will break in 32-bit system.
     *
     * @var int
     */
    const TTL_FOREVER = 1000 * 365 * 24 * 60 * 60;

    /**
     * Default time-to-live.
     *
     * Values:
     * - minus one: forever, and ttl arg to set method ignored
     * - zero: forever, used when set method ttl arg null.
     * - positive: used when set method ttl arg null.
     *
     * @var int
     */
    const TTL_DEFAULT = 0;

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
     * @var string
     */
    protected $fileMode = '';

    /**
     * Default time-to-live.
     *
     * Values:
     * - minus one: forever, and ttl arg to set method ignored
     * - zero: forever, used when set method ttl arg null.
     * - positive: used when set method ttl arg null.
     *
     * @var integer
     */
    protected $ttlDefault = 0;

    /**
     * Create or load cache store.
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
     *          Minus one: forever, and ttl arg to set method ignored.
     *          Zero: forever, used when set method ttl arg null.
     *          Positive int: used when set method ttl arg null.
     * }
     * @throws InvalidArgumentException
     *      Invalid arg name.
     * @throws \TypeError
     *      Wrong type of arg options bucket.
     * @throws \Throwable
     *      Propagated.
     */
    public function __construct(string $name, array $options = [])
    {
        if (!CacheKey::validate($name)) {
            throw new InvalidArgumentException('Arg name is empty or contains illegal char(s), name['
                . $name . '].');
        }
        $this->name = $name;

        if (!empty($options['path'])) {
            if (!is_string($options['path'])) {
                throw new \TypeError('Arg options[path] type['
                    . (!is_object($options['path']) ? gettype($options['path']) :
                        get_class($options['path'])) . '] is not string.');
            }
            $this->path = $options['path'];
        } else {
            $this->path = static::PATH_DEFAULT;
        }

        // Resolve path, and load preexisting settings if they exist.
        $settings = $this->resolvePath() ? $this->loadSettings() : [];
        // Resolve options and final instance var values, and figure out if we
        // need to update filed settings.
        $save_settings = $this->resolveSettings($settings, $options);
        // Create path, cache dir and tmp dir, if they don't exist.
        $this->ensureDirectories();
        // Save/update settings.
        if ($save_settings) {
            $this->saveSettings($settings);
        }
    }

    /**
     * Compares preexiting settings with passed options, and set instance vars
     * accordingly.
     *
     * Separated from from constructor to accommodate class extension.
     *
     * @param array &$settings
     *      By reference.
     * @param array $options
     *
     * @return bool
     *      True: preexisting setting don't exist or differ from options passed.
     *
     * @throws \TypeError
     */
    protected function resolveSettings(array &$settings, array $options) /*: void*/
    {
        $diff = !$settings;
        $settings_exist = !$diff;

        // fileMode.
        if (!empty($options['fileMode'])) {
            if (!is_string($options['fileMode'])) {
                throw new \TypeError('Arg options[fileMode] type['
                    . (!is_object($options['fileMode']) ? gettype($options['fileMode']) :
                        get_class($options['fileMode'])) . '] is not string.');
            }
            switch ($options['fileMode']) {
                case 'user':
                case 'group':
                case 'group_setgid':
                    $this->fileMode = $options['fileMode'];
                    break;
                default:
                    throw new InvalidArgumentException(
                        'Arg fileMode must be user|group|group_setgid or empty, fileMode[' . $options['fileMode'] . '].'
                    );
            }
            if (!$settings_exist || $options['fileMode'] != $settings['fileMode']) {
                $diff = true;
                $settings['fileMode'] = $options['fileMode'];
            }
        } elseif ($settings_exist) {
            $this->fileMode = $settings['fileMode'];
        } else {
            $settings['fileMode'] = $this->fileMode = static::FILE_MODE_DEFAULT;
            // And no settings means diff is already true.
        }

        // ttlDefault.
        if (isset($options['ttlDefault'])) {
            if (!$options['ttlDefault']) {
                $this->ttlDefault = 0;
            } elseif ($options['ttlDefault'] === -1) {
                $this->ttlDefault = -1;
            } else {
                $this->ttlDefault = $this->timeToLive($options['ttlDefault']);
            }
            if (!$settings_exist || $options['ttlDefault'] != $settings['ttlDefault']) {
                $diff = 1;
                $settings['ttlDefault'] = $options['ttlDefault'];
            }
        } elseif ($settings_exist) {
            $this->ttlDefault = $settings['ttlDefault'];
        } else {
            $settings['ttlDefault'] = $this->ttlDefault = static::TTL_DEFAULT;
            // And no settings means diff is already true.
        }
        if (!$this->ttlDefault) {
            $this->ttlDefault = static::TTL_FOREVER;
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
     * Ensures this class' (writable) path, tmp dir and stores dir.
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
        $cache_dir = $this->pathReal . '/stores/' . $this->name;
        if (!file_exists($cache_dir)) {
            $utils->ensurePath($cache_dir, static::FILE_MODE['dir_' . $this->fileMode]);
        }
        // Ensure tmp dir.
        $tmp_dir = $this->pathReal . '/tmp';
        if (!file_exists($tmp_dir)) {
            $utils->ensurePath($tmp_dir, static::FILE_MODE['dir_' . $this->fileMode]);
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
            $settings = Utils::getInstance()->parseIniFile($file, false, true);
            if (!$settings && $settings === false) {
                throw new RuntimeException('Failed to read store settings, file[' . $file . '].');
            }
            return $settings;
        }
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
        $file = $this->pathReal . '/' . $this->name . '.ini';
        $content = Utils::getInstance()->iterableToIniString($settings);
        $set_mode = $this->fileMode != 'user' && !file_exists($file);
        if (!file_put_contents($file, $content)) {
            throw new RuntimeException('Failed to write store settings to file[' . $file . '].');
        }
        if ($set_mode) {
            if (!chmod($file, static::FILE_MODE['file_' . $this->fileMode])) {
                throw new RuntimeException('Failed to chmod settings file[' . $file . '].');
            }
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
}
