<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

use Psr\SimpleCache\CacheInterface;
use SimpleComplex\Utils\Explorable;
use SimpleComplex\Cache\Interfaces\ManageableCacheInterface;
use SimpleComplex\Cache\Exception\InvalidArgumentException;
use SimpleComplex\Cache\Exception\OutOfBoundsException;
use SimpleComplex\Cache\Exception\RuntimeException;

/**
 * Cache broker is an abstraction of actual CacheInterface classes/instances,
 * allowing change of cache implementation and medium without consequences
 * to code using the CacheBroker.
 *
 * @dependency-injection-container-id cache-broker
 *      Suggested ID of the CacheBroker instance.
 *
 * All registered stores are accessible via 'magic' getters.
 * @property-read CacheInterface *
 *
 * @package SimpleComplex\Cache
 */
class CacheBroker extends Explorable
{
    // Explorable.--------------------------------------------------------------

    protected $explorableIndex = [];

    /**
     * Retrieves a store.
     *
     * @param string $name
     *
     * @return CacheInterface|ManageableCacheInterface
     *
     * @throws OutOfBoundsException
     *      If no such instance property.
     */
    public function __get($name)
    {
        if (isset($this->stores['' . $name])) {
            return $this->stores['' . $name];
        }
        throw new OutOfBoundsException(get_class($this) . ' instance has no store[' . $name . '].');
    }

    /**
     * All stores are read-only.
     *
     * @param string $name
     * @param mixed|null $value
     *
     * @return void
     *
     * @throws OutOfBoundsException
     *      If no such instance property.
     * @throws RuntimeException
     *      If such instance property declared.
     */
    public function __set($name, $value) /*: void*/
    {
        switch ('' . $name) {
            case 'stores':
                throw new RuntimeException(get_class($this) . ' instance store[' . $name . '] is read-only.');
        }
        throw new OutOfBoundsException(get_class($this) . ' instance has no store[' . $name . '].');
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


    // Business.----------------------------------------------------------------

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
     * @deprecated Use a dependency injection container instead.
     * @see \SimpleComplex\Utils\Dependency
     * @see \Slim\Container
     *
     * @return CacheBroker
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance()
    {
        // Unsure about null ternary ?? for class and instance vars.
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Cache classes.
     *
     * Extended CacheBroker class could override this list.
     *
     * @var string[]
     */
    const CACHE_CLASSES = [
        'base' => FileCache::class,
        'default' => FileCache::class,
        'variable_ttl' => FileCache::class,
        'fixed_ttl' => FixedTtlFileCache::class,
        'persistent' => PersistentFileCache::class,
        'key_long_variable_ttl' => KeyLongFileCache::class,
        'key_long_fixed_ttl' => KeyLongFixedTtlFileCache::class,
        'key_long_persistent' => KeyLongPersistentFileCache::class,
    ];

    /**
     * Base cache class alias.
     *
     * @var string
     */
    const CACHE_BASE = 'base';

    /**
     * Variable time-to-live cache class alias.
     *
     * @var string
     */
    const CACHE_VARIABLE_TTL = 'variable_ttl';

    /**
     * Fixed time-to-live cache class alias.
     *
     * @var string
     */
    const CACHE_FIXED_TTL = 'fixed_ttl';

    /**
     * Persistent cache class alias.
     *
     * @var string
     */
    const CACHE_PERSISTENT = 'persistent';

    /**
     * Variable time-to-live (long keys) cache class alias.
     *
     * @var string
     */
    const CACHE_KEY_LONG_VARIABLE_TTL = 'key_long_variable_ttl';

    /**
     * Fixed time-to-live (long keys) cache class alias.
     *
     * @var string
     */
    const CACHE_KEY_LONG_FIXED_TTL = 'key_long_fixed_ttl';

    /**
     * Persistent cache (long keys) class alias.
     *
     * @var string
     */
    const CACHE_KEY_LONG_PERSISTENT = 'key_long_persistent';

    /**
     * @var array
     */
    protected $stores = [];

    /**
     * Get or create cache store.
     *
     * @param string $name
     * @param string $classNameArAlias
     *      Use alias CACHE_VARIABLE_TTL|CACHE_FIXED_TTL|CACHE_PERSISTENT
     *      (recommended, allows search for usage) or a concrete class name.
     *      Empty or 'default' resolves to CACHE_CLASSES[default]
     * @param mixed ...$storeConstructorArgs
     *      Arguments to the store type class' constructor or make().
     *      If empty, this method will provide fitting arguments.
     *
     * @return \Psr\SimpleCache\CacheInterface|ManageableCacheInterface
     *
     * @throws InvalidArgumentException
     *      Arg name is empty or contains illegal char(s).
     *      Propagated; see instantiateStore().
     */
    public function getStore(string $name, string $classNameArAlias, ...$storeConstructorArgs) : CacheInterface
    {
        if (!CacheKey::validate($name)) {
            throw new InvalidArgumentException('Arg name is not valid, name[' . $name . '].');
        }
        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }
        $this->stores[$name] = $store = $this->instantiateStore($name, $classNameArAlias, $storeConstructorArgs);
        $this->explorableIndex[] = $name;
        return $store;
    }

    /**
     * Allows extending class to determine which and how to instantiate
     * a CacheInterface implementation.
     *
     * @param string $name
     * @param string $classNameArAlias
     * @param array $storeConstructorArgs
     *
     * @return \Psr\SimpleCache\CacheInterface|ManageableCacheInterface
     *
     * @throws InvalidArgumentException
     *      Arg classNameArAlias seem to be a class name (not an alias),
     *      and that class doesn't exist or it doesn't implement CacheInterface.
     */
    protected function instantiateStore(string $name, string $classNameArAlias, array $storeConstructorArgs) : CacheInterface
    {
        // NB: First cache implementation's constructor param must be $name,
        // and this $storeConstructorArgs must not contain that argument.
        // Actually not sure if that is such a great idea.
        array_unshift($storeConstructorArgs, $name);

        if (!$classNameArAlias || $classNameArAlias == 'default') {
            $class = static::CACHE_CLASSES['default'];
        } elseif (isset(static::CACHE_CLASSES[$classNameArAlias])) {
            $class = static::CACHE_CLASSES[$classNameArAlias];
        } else {
            if (!class_exists($classNameArAlias)) {
                throw new InvalidArgumentException(
                    'Class doesn\'t exist, arg classNameArAlias[' . $classNameArAlias . '].'
                );
            }
            if (!is_subclass_of($classNameArAlias, CacheInterface::class)) {
                throw new InvalidArgumentException(
                    'Class doesn\'t implement Psr\SimpleCache\CacheInterface, arg classNameArAlias[' . $classNameArAlias . '].'
                );
            }
            $class = $classNameArAlias;
        }
        return new $class(...$storeConstructorArgs);
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
        if (!CacheKey::validate($name)) {
            throw new InvalidArgumentException('Arg name is not valid, name[' . $name . '].');
        }
        return isset($this->stores[$name]);
    }

    /**
     * @throws InvalidArgumentException
     *      Arg name is empty or contains illegal char(s).
     *
     * @param string $name
     * @param CacheInterface|ManageableCacheInterface $store
     *
     * @return bool
     */
    public function registerStore(string $name, CacheInterface $store) : bool
    {
        if (!CacheKey::validate($name)) {
            throw new InvalidArgumentException('Arg name is not valid, name[' . $name . '].');
        }
        $this->stores[$name] = $store;
        $this->explorableIndex[] = $name;
        return true;
    }
}
