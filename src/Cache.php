<?php

namespace KkSeb\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 Simple Cache implementation, supporting (at least) file-based storage.
 *
 * This class is just a relay to actual CacheInterface implementations.
 *
 * @package KkSeb\Cache
 */
class Cache implements CacheInterface {

  // Non \Psr\SimpleCache\CacheInterface members.

  /**
   * Class name of \KkSeb\Cache\FileBasedCache or extending class.
   *
   * @code
   * // Overriding class must use fully qualified (namespaced) class name.
   * const CLASS_FILE_BASED = '\\Package\\Library\\FileBasedCache';
   * @endcode
   *
   * @var string
   */
  const CLASS_FILE_BASED = FileBasedCache::class;

  /**
   * @var array
   */
  protected static $stores = array();

  /**
   * Get or create cache store.
   *
   * @throws \InvalidArgumentException
   *   Unsupported arg type.
   * @throws \LogicException
   *
   * @param string $storeName
   * @param string $type
   *   Default: empty; this class decides.
   * @param array $storeArguments
   *   Arguments to the store type class' constructor or make().
   *   If empty, this method will provide fitting arguments.
   *
   * @return \Psr\SimpleCache\CacheInterface
   */
  public static function getStore($storeName, $type = '', $storeArguments = []) {
    $store_type = $type = '' . $type;
    switch ($type) {
      case 'file':
        break;
      case '':
        $store_type = 'file';
        break;
      default:
        throw new \InvalidArgumentException('Unsupported cache type[' . $type . '].');
    }

    $stores =& static::$stores;
    if (!empty($stores[$storeName])) {
      if (isset($stores[$storeName][$store_type])) {
        return $stores[$storeName][$store_type];
      }
      // If caller doesn't care which type, and there's a same-named of other
      // type than the default type.
      if (!$type) {
        return reset($stores[$storeName]);
      }
    }

    switch ($store_type) {
      case 'file':
        if (!$storeArguments) {
          $storeArguments = [
            $storeName
          ];
        }
        /**
         * @var FileCache $store
         */
        $store = call_user_func_array(array(static::CLASS_FILE_BASED, '__construct'), $storeArguments);
        $stores[$storeName][$store_type] =& $store;
        return $store;
      default:
        throw new \LogicException('Switch misses case for store type[' . $store_type . '].');
    }
  }

  /**
   * Alias of getStore().
   *
   * @see Cache::getStore()
   *
   * @param string $storeName
   * @param string $type
   * @param array $storeArguments
   *
   * @return \Psr\SimpleCache\CacheInterface
   */
  public static function getInstance($storeName, $type = '', $storeArguments = []) {
    return static::getStore($storeName, $type, $storeArguments);
  }

  /**
   * @throws \InvalidArgumentException
   *   Unsupported arg type.
   *
   * @param string $storeName
   * @param string $type
   *   Default: empty; this class decides.
   *
   * @return boolean
   */
  public function storeExists($storeName, $type = '') {
    $store_type = $type = '' . $type;
    switch ($type) {
      case 'file':
        break;
      case '':
        $store_type = 'file';
        break;
      default:
        throw new \InvalidArgumentException('Unsupported cache type[' . $type . '].');
    }

    $stores =& static::$stores;
    if (!empty($stores[$storeName])) {
      if (isset($stores[$storeName][$store_type])) {
        return true;
      }
      // If caller doesn't care which type, and there's a same-named of other
      // type than the default type.
      if (!$type) {
        return true;
      }
    }

    return false;
  }

  /**
   * This class is not to be instantiated.
   *
   * @throws \LogicException
   */
  final private function __construct() {
    throw new \LogicException('Class not supposed to be instantiated.');
  }


  // \Psr\SimpleCache\CacheInterface members.

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  final public function get($key, $default = null) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  final public function set($key, $value, $ttl = null) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   *   MUST be thrown if the $key string is not a legal value.
   */
  final public function delete($key) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  final public function clear() {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  final public function getMultiple($keys, $default = null) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  final public function setMultiple($values, $ttl = null) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  final public function deleteMultiple($keys) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }

  /**
   * @throws \LogicException
   *
   * @inheritdoc
   */
  final public function has($key) {
    throw new \LogicException('Class not supposed to be instantiated.');
  }
}
