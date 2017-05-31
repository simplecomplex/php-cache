<?php

namespace KkSeb\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 Simple Cache file-based.
 *
 * @package KkSeb\Cache
 */
class FileCache implements CacheInterface {

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
   *   Invalid arg storeName.
   * @throws \RuntimeException
   *   Unable to create or write to store path.
   *
   * @param string $storeName
   * @param integer $ttlDefault
   *   Zero: forever.
   * @param string $parentPath
   *   Path above this store's dir; this store's own dir equals arg name.
   *   Relative path will be considered relative to document root.
   *   Default: empty; use default parent path.
   */
  public function __construct($storeName, $ttlDefault = 0, $parentPath = '') {
    if (!static::keyValidate($storeName)) {
      throw new \InvalidArgumentException('Invalid store name[' . $storeName . '].');
    }

    $this->ttlDefault = $ttlDefault < 1 ? 0 : $ttlDefault;

    if (!$parentPath) {
      $parentPath = static::$parentPathDefault;
    }
    if (!isset(static::$parentPathsEnsured[$parentPath])) {
      // Secure absolute path.
      if (strpos($parentPath, '..') === 0) {
        // Remove ../ from final path; assuming that document root is not
        // a root dir of the file system.
        $doc_root_parent = dirname(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : getcwd());
        if ($doc_root_parent == '/') {
          $doc_root_parent = '';
        }
        $parentPath = $doc_root_parent . substr($parentPath, 2);
      }
      // Relative to current dir is illegal.
      elseif ($parentPath{0} === '.') {
        throw new \RuntimeException('Invalid parent path[' . $parentPath . '].');
      }
    }
    // We don't wanna check parent path and (full) store path separately;
    // checking the latter suffices for most purposes.
    $store_path = $parentPath . '/' . $storeName;
    if (!file_exists($store_path)) {
      if (!mkdir($store_path, static::$fileModeDir, true)) {
        throw new \RuntimeException('Failed to create store path[' . $store_path . '].');
      }
    }
    if (!is_writable($store_path)) {
      throw new \RuntimeException('Not writable store path[' . $store_path . '].');
    }
    static::$parentPathsEnsured[$parentPath] = true;

    $this->storePath = $store_path;
  }

  /**
   * @return string
   */
  public function getStoreName() {
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
  public static function setFileModeDir($mode) {
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
  public static function setFileModeFile($mode) {
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
  public static function setParentPathDefault($path) {
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
   * @param string|mixed $key
   *   Gets stringified.
   *
   * @return boolean
   */
  public static function keyValidate($key) {
    $key = '' . $key;
    if (!$key && $key === '') {
      return false;
    }
    // This must be faster than a regular expression.
    return !!ctype_alnum('A' . str_replace(static::$keyValidNonAlphaNum, '', $key));
  }


  // \Psr\SimpleCache\CacheInterface members.

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */

  /**
   * @param string $key
   * @param mixed $default
   *
   * @return mixed
   */
  public function get($key, $default = null) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  public function set($key, $value, $ttl = null) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   *   MUST be thrown if the $key string is not a legal value.
   */
  public function delete($key) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  public function clear() {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  public function getMultiple($keys, $default = null) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  public function setMultiple($values, $ttl = null) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  public function deleteMultiple($keys) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  public function has($key) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }
}
