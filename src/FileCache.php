<?php
/**
 * SimpleComplex PHP Utils
 * @link      https://github.com/simplecomplex/php-utils
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

use Psr\SimpleCache\CacheInterface;
use SimpleComplex\Cache\Exception\InvalidArgumentException;
use SimpleComplex\Cache\Exception\LogicException;
use SimpleComplex\Utils\Explorable;
use SimpleComplex\Utils\CliEnvironment;
use SimpleComplex\Utils\Exception\CacheInvalidArgumentException;
use SimpleComplex\Utils\Exception\ConfigurationException;
use SimpleComplex\Utils\Exception\OutOfBoundsException;
use SimpleComplex\Utils\Exception\RuntimeException;

/**
 * PSR-16 Simple Cache file-based.
 *
 * @property-read $name
 * @property-read $type
 * @property-read $ttlDefault
 *
 * @package SimpleComplex\Cache
 */
class FileCache extends Explorable implements CacheInterface
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
        if (!$this->keyValidate($key)) {
            throw new CacheInvalidArgumentException('Arg key is not valid, key[' . $key . '].');
        }

        $file = $this->file($key);
        if (!file_exists($file)) {
            return $default;
        }

        // Unless time-to-live is to be ignored by all methods/procedures.
        if ($this->ttlDefault) {
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
        if (!$this->keyValidate($key)) {
            throw new CacheInvalidArgumentException('Arg key is not valid, key[' . $key . '].');
        }

        $serialized = serialize($value);
        if (!$serialized) {
            throw new RuntimeException('Failed to serialize value.');
        }

        $file = $this->file($key);

        // @todo: use rename() because atomic on nix

        if (!file_put_contents(
            $file,
            $serialized
        )) {
            throw new RuntimeException('Failed to write to file[' . $file . '].');
        }

        if (
            $ttl
            // Unless time-to-live is to be ignored by all methods/procedures.
            && $this->ttlDefault
            && ($time_to_live = $this->timeToLive($ttl))
            // Set the file's modified time to the (future) end-of-life time.
            && !touch($file, time() + $time_to_live)
        ) {
            throw new RuntimeException('Failed to set future modified time of[' . $file . '].');
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
        if (!$this->keyValidate($key)) {
            throw new CacheInvalidArgumentException('Arg key is not valid, key[' . $key . '].');
        }
        // Suppress PHP notice/warning; file_exists()+unlink() is not atomic.
        @unlink(
            $this->file($key)
        );
        return true;
    }

    /**
     * @throws \LogicException
     *
     * @inheritdoc
     */
    public function clear()
    {
        throw new \LogicException('Method not implemented.');
    }

    /**
     * @throws \LogicException
     *
     * @inheritdoc
     */
    public function getMultiple($keys, $default = null)
    {
        throw new \LogicException('Method not implemented.');
    }

    /**
     * @throws \LogicException
     *
     * @inheritdoc
     */
    public function setMultiple($values, $ttl = null)
    {
        throw new \LogicException('Method not implemented.');
    }

    /**
     * @throws \LogicException
     *
     * @inheritdoc
     */
    public function deleteMultiple($keys)
    {
        throw new \LogicException('Method not implemented.');
    }

    /**
     * @throws \LogicException
     *
     * @inheritdoc
     */
    public function has($key)
    {
        throw new \LogicException('Method not implemented.');
    }


    // Explorable.--------------------------------------------------------------

    protected $explorableIndex = [
        'name',
        'type',
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
        switch ($name) {
            case 'name':
            case 'type':
            case 'ttlDefault':
                return $this->name;
        }
        throw new OutOfBoundsException(get_class($this) . ' instance has no property[' . $name . '].');
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
    public function __set(string $name, $value) /*: void*/
    {
        switch ($name) {
            case 'name':
            case 'type':
            case 'ttlDefault':
                throw new RuntimeException(get_class($this) . ' instance property[' . $name . '] is read-only.');
        }
        throw new OutOfBoundsException(get_class($this) . ' instance has no property[' . $name . '].');
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


    // Custom.------------------------------------------------------------------

    /**
     * Relative path is relative to document root.
     *
     * Will contain:
     * - /stores/[some store name]/[...caches]
     * - /tmp
     * - [some store name].json
     *
     * @var string
     */
    const PATH = '../private/lib/simplecomplex/file-cache';

    /**
     * Relative path is relative to document root.
     *
     * @var string
     */
    const PATH_PARENT_DEFAULT = '../private/lib/simplecomplex/file-cache';

    // @todo: /stores
    // @todo: /tmp  - for rename()ing
    // @todo: /stores.json

    /**
     * File mode used when creating directory.
     */
    const FILE_MODE_DIR = 2770;

    /**
     * File mode used when creating file.
     */
    const FILE_MODE_FILE = 2660;

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
     * @var integer
     */
    protected $ttlDefault = 0;

    /**
     * @var string
     */
    protected $parentPath = '';

    /**
     * @var string
     */
    protected $path = '';

    /**
     * Parent paths ensured to exist and be writable.
     *
     * Class var because we don't want to spend the (usually unnecessary) effort
     * checking (usually the same) path over and over again.
     *
     * @var array
     */
    protected static $parentPathsEnsured = array();

    /**
     * @param string $name
     * @param integer $ttlDefault
     *   Zero: forever, and time-to-live will be ignored by all methods/operations.
     * @param string $parentPath
     *   Path above this store's dir; this store's own dir equals arg name.
     *   Relative path will be considered relative to document root.
     *   Default: empty; use default parent path.
     *
     * @throws \InvalidArgumentException
     *      Invalid arg name.
     * @throws \SimpleComplex\Utils\Exception\ConfigurationException
     *      Cannot resolve document root.
     * @throws \RuntimeException
     *      Unable to create or write to store path.
     */
    public function __construct(string $name, int $ttlDefault = 0, string $parentPath = '')
    {
        if (!$this->nameValidate($name)) {
            throw new InvalidArgumentException('Arg name is empty or contains illegal char(s), $name['
                . $name . '].');
        }

        $this->ttlDefault = $ttlDefault < 1 ? 0 : $ttlDefault;

        $this->parentPath = $parentPath;
    }

    /**
     * Legal non-alphanumeric characters of a key.
     *
     * PSR-16 requirements:
     * - at least: a-zA-Z\d_.
     * - not: {}()/\@:
     * - length: >=2 <=64
     *
     * These keys are selected because they would work in the most basic cache
     * implementation; that is: file (dir names and filenames).
     * Parentheses and colon would have worked too, but forbidden by PSR-16.
     */
    const KEY_VALID_NON_ALPHANUM = [
        '-',
        '.',
        '[',
        ']',
        '_'
    ];

    /**
     * Checks that key is string, and that length and content is legal.
     *
     * @param string $key
     *
     * @return bool
     */
    public function keyValidate(string $key) : bool
    {
        $le = strlen($key);
        if ($le < 2 || $le > 64) {
            return false;
        }
        // Faster than a regular expression.
        return !!ctype_alnum('A' . str_replace(static::KEY_VALID_NON_ALPHANUM, '', $key));
    }

    /**
     * This implementation enforces same rules on store name as cache key.
     *
     * @param string $name
     *
     * @return bool
     */
    public function nameValidate(string $name) : bool
    {
        return $this->keyValidate($name);
    }

    /**
     * Ensures this class' (writable) path, tmp dir and stores dir.
     *
     * @return void
     *
     * @throws ConfigurationException
     *      If document root cannot be determined.
     * @throws LogicException
     *      Algo or configuration error, can't determine whether path is
     *      absolute or relative.
     */
    protected function path() /*: void*/
    {
        $path = static::PATH;
        // Absolute.
        if (
            strpos($path, '/') !== 0
            && (DIRECTORY_SEPARATOR === '/' || strpos($path, ':') !== 1)
        ) {
            // Document root.
            if (!empty($_SERVER['DOCUMENT_ROOT'])) {
                $doc_root = $_SERVER['DOCUMENT_ROOT'];
                if (DIRECTORY_SEPARATOR == '/') {
                    $doc_root = str_replace('\\', '/', $doc_root);
                }
            } elseif (CliEnvironment::cli()) {
                $doc_root = (new CliEnvironment())->documentRoot;
                if (!$doc_root) {
                    throw new ConfigurationException(
                        'Cannot resolve document root, probably no .document_root file in document root.');
                }
            } else {
                throw new ConfigurationException(
                    'Cannot resolve document root, _SERVER[DOCUMENT_ROOT] non-existent or empty.');
            }
            // Relative above document root.
            if (strpos($path, '../') === 0) {
                $path = dirname($doc_root) . substr($path, 2);
            }
            // Relative to self of document root.
            elseif (strpos($path, './') === 0) {
                $path = $doc_root . substr($path, 1);
            }
            else {
                throw new LogicException(
                    'Algo or configuration error, failed to determine whether path[' . $path
                    . '] is absolute or relative.'
                );
            }
        }

        if (!file_exists($path)) {
            if (!mkdir($path, static::FILE_MODE_DIR, true)) {
                throw new \RuntimeException('Failed to create path[' . $path . '].');
            }
            if (!is_writable($path)) {
                throw new \RuntimeException('Not writable path[' . $path . '].');
            }
        }
        // Ensure tmp dir.
        $tmp_dir = $path . '/tmp';
        if (!file_exists($tmp_dir)) {
            if (!mkdir($tmp_dir, static::FILE_MODE_DIR)) {
                throw new \RuntimeException('Failed to create tmp dir[' . $tmp_dir . '].');
            }
            if (!is_writable($tmp_dir)) {
                throw new \RuntimeException('Not writable tmp dir[' . $tmp_dir . '].');
            }
        }
        $stores_dir = $path . '/stores';
        if (!file_exists($stores_dir)) {
            if (!mkdir($stores_dir, static::FILE_MODE_DIR)) {
                throw new \RuntimeException('Failed to create stores dir[' . $stores_dir . '].');
            }
            if (!is_writable($stores_dir)) {
                throw new \RuntimeException('Not writable stores dir[' . $stores_dir . '].');
            }
        }

        $this->path = $path;
    }

    const OPTIONS_DEFAULT = [
        'ttl' => 0,
    ];

    /**
     * Load previously created options of this store, if exists already.
     *
     * @return array
     */
    protected function load()
    {
        $file = $this->path . '/' . $this->name . '.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $options = parse_ini_file($file, false, INI_SCANNER_RAW);
            if (!$json) {
                if ($json === false) {
                    throw new \RuntimeException('Failed to read stores registry, file[' . $file . '].');
                }
            } else {
                $options = json_decode($json, true);
                if (!$options) {
                    throw new \RuntimeException('Failed to JSON parse stores registry, file[' . $file . '].');
                }
                return $options;
            }
        }
        return [];
    }

    /**
     * @param array $options
     */
    protected function prepare(array $options)
    {
        $file = $this->path . '/stores.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            if (!$json) {
                if ($json === false) {
                    throw new \RuntimeException('Failed to read stores registry, file[' . $file . '].');
                }
                $stores = [];
            } else {
                $stores = json_decode($json, true);
                if (!$stores) {
                    throw new \RuntimeException('Failed to JSON parse stores registry, file[' . $file . '].');
                }
            }
        }

    }

    /**
     * @param string $name
     * @param array $options
     *
     *
     *
     */
    protected function setup(string $name, array $options = [])
    {

    }

    /**
     * Resolve path and file name.
     *
     * @param string $key
     *
     * @return string
     */
    protected function file(string $key) {
        if (!$this->path) {
            $parent_path = $this->parentPath;
            if (!$parent_path) {
                $parent_path = static::PATH_PARENT_DEFAULT;
            }
            if (!isset(static::$parentPathsEnsured[$parent_path])) {
                // Secure absolute path.
                if (strpos($parent_path, '..') === 0) {
                    // Remove ../ from final path; assuming that document root is not
                    // a root dir of the file system.
                    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
                        $doc_root_parent = $_SERVER['DOCUMENT_ROOT'];
                        if (DIRECTORY_SEPARATOR == '/') {
                            $doc_root_parent = str_replace('\\', '/', $doc_root_parent);
                        }
                    } elseif (CliEnvironment::cli()) {
                        $doc_root_parent = (new CliEnvironment())->documentRoot;
                        if (!$doc_root_parent) {
                            throw new ConfigurationException(
                                'Cannot resolve document root, probably no .document_root file in document root.');
                        }
                    } else {
                        throw new ConfigurationException(
                            'Cannot resolve document root, _SERVER[DOCUMENT_ROOT] non-existent or empty.');
                    }
                    $doc_root_parent = dirname($doc_root_parent);
                    if ($doc_root_parent == '/') {
                        $doc_root_parent = '';
                    }
                    $parent_path = $doc_root_parent . substr($parent_path, 2);
                }
                // Relative to current dir is illegal.
                elseif ($parent_path{0} === '.') {
                    throw new \RuntimeException('Invalid parent path[' . $parent_path . '].');
                }
            }
            // We don't wanna check parent path and (full) store path separately;
            // checking the latter suffices for most purposes.
            $store_path = $parent_path . '/' . $this->name;
            if (!file_exists($store_path)) {
                if (!mkdir($store_path, static::FILE_MODE_DIR, true)) {
                    throw new \RuntimeException('Failed to create store path[' . $store_path . '].');
                }
            }
            if (!is_writable($store_path)) {
                throw new \RuntimeException('Not writable store path[' . $store_path . '].');
            }
            static::$parentPathsEnsured[$parent_path] = true;

            $this->path = $store_path;
        }

        return $this->path . '/' . $key;
    }

    const DAYS_OF_YEAR = 365.25;

    /**
     * @param int|\DateInterval|null $ttl
     *      Non-empty must be non-negative.
     *
     * @return int
     *      Seconds.
     *
     * @throws InvalidArgumentException
     *      Arg ttl wrong type.
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
                return $ttl;
            }
            throw new InvalidArgumentException('Time-to-live must be integer, DateInterval or null.');
        }
        return 0;
    }
}
