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
use SimpleComplex\Utils\CliEnvironment;
use SimpleComplex\Utils\Exception\ConfigurationException;

/**
 * PSR-16 Simple Cache file-based.
 *
 * @package SimpleComplex\Cache
 */
class FileCache implements CacheInterface
{
    // \Psr\SimpleCache\CacheInterface members.---------------------------------

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        throw new \LogicException('Method not implemented.');
    }

    /**
     * @throws \LogicException
     *
     * @inheritdoc
     */
    public function set($key, $value, $ttl = null)
    {
        throw new \LogicException('Method not implemented.');
    }

    /**
     * @throws \LogicException
     *
     * @inheritdoc
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
        throw new \LogicException('Method not implemented.');
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

    
    // Non \Psr\SimpleCache\CacheInterface members.

    /**
     * Protected because changing it upon initialisation would de-reference
     * existing cache items.
     *
     * @see FileCache::getStoreName()
     *
     * @var string
     */
    protected $storeName = '';

    /**
     * @var integer
     */
    public $ttlDefault = 0;

    /**
     * @var string
     */
    protected $storePath = '';

    /**
     * @throws \InvalidArgumentException
     *      Invalid arg storeName.
     * @throws \SimpleComplex\Utils\Exception\ConfigurationException
     *      Cannot resolve document root.
     * @throws \RuntimeException
     *      Unable to create or write to store path.
     *
     * @param string $storeName
     * @param integer $ttlDefault
     *   Zero: forever.
     * @param string $parentPath
     *   Path above this store's dir; this store's own dir equals arg storeName.
     *   Relative path will be considered relative to document root.
     *   Default: empty; use default parent path.
     */
    public function __construct(string $storeName, int $ttlDefault = 0, string $parentPath = '')
    {
        if (!$this->nameValidate($storeName)) {
            throw new InvalidArgumentException('Arg storeName is empty or contains illegal char(s), $storeName['
                . $storeName . '].');
        }

        $this->ttlDefault = $ttlDefault < 1 ? 0 : $ttlDefault;

        $parent_path = $parentPath;
        if (!$parent_path) {
            $parent_path = static::$parentPathDefault;
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
                } elseif (PHP_SAPI == 'cli') {
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
        $store_path = $parent_path . '/' . $storeName;
        if (!file_exists($store_path)) {
            if (!mkdir($store_path, static::$fileModeDir, true)) {
                throw new \RuntimeException('Failed to create store path[' . $store_path . '].');
            }
        }
        if (!is_writable($store_path)) {
            throw new \RuntimeException('Not writable store path[' . $store_path . '].');
        }
        static::$parentPathsEnsured[$parent_path] = true;

        $this->storePath = $store_path;
    }

    /**
     * @return string
     */
    public function getStoreName()
    {
        return $this->storeName;
    }

    /**
     * @see FileCache::setFileModeDir()
     *
     * @var integer
     */
    protected static $fileModeDir = 2770;

    /**
     * Consider extending this class instead.
     *
     * @see FileCache::$fileModeDir
     *
     * @param integer $mode
     *   Must be (at least) 4 digits; use trailing zero if NNN.
     */
    public static function setFileModeDir($mode)
    {
        static::$fileModeDir = $mode;
    }

    /**
     * @see FileCache::setFileModeFile()
     *
     * @var integer
     */
    protected static $fileModeFile = 2660;

    /**
     * Consider extending this class instead.
     *
     * @see FileCache::$fileModeFile
     *
     * @param integer $mode
     */
    public static function setFileModeFile($mode)
    {
        static::$fileModeFile = $mode;
    }

    /**
     * Relative path will be considered relative to document root.
     *
     * @see FileCache::setParentPathDefault()
     *
     * @var string
     */
    protected static $parentPathDefault = '../private/simplecomplex/file-cache';

    /**
     * Consider extending this class instead.
     *
     * @see FileCache::$parentPathDefault
     *
     * @param string $path
     *   Relative path will be considered relative to document root.
     */
    public static function setParentPathDefault($path)
    {
        static::$parentPathDefault = $path;
    }

    /**
     * @var array
     */
    protected static $parentPathsEnsured = array();

    /**
     * Cache keys (and cache store name) must consist of lower- and uppercase
     * letters (in locale) plus a limited range of ASCII printable characters.
     *
     * Allowed non-alphanums are:
     * ()-.:[]_
     *
     * @var array
     */
    protected static $keyValidNonAlphaNum = array(
        '(', ')', '-', '.', ':', '[', ']', '_'
    );

    /**
     * Legal non-alphanumeric characters of a key.
     *
     * These keys are selected because they would work in the most basic cache
     * implementation; that is: file (dir names and filenames).
     */
    const KEY_VALID_NON_ALPHANUM = [
        '(',
        ')',
        '-',
        '.',
        ':',
        '[',
        ']',
        '_'
    ];



    /**
     * Checks that stringified key is non-empty and only contains legal chars.
     *
     * @param string $name
     *      Allows alphanum, underscore and hyphen.
     *
     * @return bool
     */
    public function nameValidate(string $name) : bool
    {
        if (!$name && $name === '') {
            return false;
        }
        // Faster than a regular expression.
        return !!ctype_alnum('A' . str_replace(['_', '-'], '', $name));
    }

    /**
     * Checks that stringified key is non-empty and only contains legal chars.
     *
     * @param string $key
     *
     * @return bool
     */
    public function keyValidate(string $key) : bool
    {
        if (!$key && $key === '') {
            return false;
        }
        // Faster than a regular expression.
        return !!ctype_alnum('A' . str_replace(static::KEY_VALID_NON_ALPHANUM, '', $key));
    }



}
