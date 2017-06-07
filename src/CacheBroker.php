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

/**
 * Cache broker.
 *
 * @package SimpleComplex\Cache
 */
class CacheBroker
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var CacheBroker
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return CacheBroker
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }

    /**
     * @var string
     */
    const CLASS_TYPE_FILE = FileCache::class;

    /**
     * @var array
     */
    protected $stores = [];

    /**
     * Get or create cache store.
     *
     * @throws InvalidArgumentException
     *      Arg name is empty or contains illegal char(s).
     *
     * @param string $name
     *      Allows alpha
     * @param array $storeConstructorArgs
     *      Arguments to the store type class' constructor or make().
     *      If empty, this method will provide fitting arguments.
     *
     * @return \Psr\SimpleCache\CacheInterface
     */
    public function getStore(string $name, array $storeConstructorArgs = []) : CacheInterface
    {
        if (!$this->nameValidate($name)) {
            throw new InvalidArgumentException('Arg name is empty or contains illegal char(s), name[' . $name . '].');
        }
        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }
        $args = $storeConstructorArgs ? $storeConstructorArgs : [
            $name,
        ];
        $class = static::CLASS_TYPE_FILE;
        $this->stores[$name] = $store = new $class(...$args);
        return $store;
    }

    /**
     * @throws InvalidArgumentException
     *      Arg name is empty or contains illegal char(s).
     *
     * @param string $name
     *
     * @return boolean
     */
    public function hasStore(string $name) : bool
    {
        if (!$this->nameValidate($name)) {
            throw new InvalidArgumentException('Arg name is empty or contains illegal char(s), name[' . $name . '].');
        }
        return isset($this->stores[$name]);
    }

    /**
     * @throws InvalidArgumentException
     *      Arg name is empty or contains illegal char(s).
     *
     * @param string $name
     * @param CacheInterface $store
     *
     * @return bool
     */
    public function registerStore(string $name, CacheInterface $store) : bool
    {
        if (!$this->nameValidate($name)) {
            throw new InvalidArgumentException('Arg name is empty or contains illegal char(s), name[' . $name . '].');
        }
        $this->stores[$name] = $store;
        return true;
    }

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
}
