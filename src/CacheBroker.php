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
use SimpleComplex\Utils\Explorable;
use SimpleComplex\Utils\Exception\RuntimeException;
use SimpleComplex\Utils\Exception\OutOfBoundsException;
use SimpleComplex\Cache\Exception\InvalidArgumentException;

/**
 * Cache broker is an abstraction of actual CacheInterface classes/instances,
 * allowing change of cache implementation and medium without consequences
 * to code using the CacheBroker.
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
     * @return CacheInterface
     *
     * @throws OutOfBoundsException
     *      If no such instance property.
     */
    public function __get(string $name)
    {
        if (isset($this->stores[$name])) {
            return $this->stores[$name];
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
    public function __set(string $name, $value) /*: void*/
    {
        switch ($name) {
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
     * @var string[]
     */
    const CLASS_BY_TYPE = [
        'file' => FileCache::class,
    ];

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
     * @param mixed ...$storeConstructorArgs
     *      Arguments to the store type class' constructor or make().
     *      If empty, this method will provide fitting arguments.
     *
     * @return \Psr\SimpleCache\CacheInterface
     */
    public function getStore(string $name, ...$storeConstructorArgs) : CacheInterface
    {
        if (!$this->nameValidate($name)) {
            throw new InvalidArgumentException('Arg name is not valid, name[' . $name . '].');
        }
        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        // @todo: problem - first cache implementation's constructor param must be $name, and this $storeConstructorArgs must not contain that argument.
        array_unshift($storeConstructorArgs, $name);

        $class = static::CLASS_BY_TYPE['file'];
        $this->stores[$name] = $store = new $class(...$storeConstructorArgs);
        $this->explorableIndex[] = $name;
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
            throw new InvalidArgumentException('Arg name is not valid, name[' . $name . '].');
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
            throw new InvalidArgumentException('Arg name is not valid, name[' . $name . '].');
        }
        $this->stores[$name] = $store;
        $this->explorableIndex[] = $name;
        return true;
    }

    /**
     * Legal non-alphanumeric characters of a store name.
     *
     * Rules like PSR-16 minimum requirements for cache key:
     * - chars: a-zA-Z\d_.
     * - length: >=2 <=64
     */
    const NAME_VALID_NON_ALPHANUM = [
        '_',
        '.',
    ];

    /**
     * Checks that key is non-empty and only contains legal chars.
     *
     * @param string $name
     *      Allows alphanum, underscore and hyphen.
     *      Min. length 2, max. length 64.
     *
     * @return bool
     */
    public function nameValidate(string $name) : bool
    {
        $le = strlen($name);
        if ($le < 2 || $le > 64) {
            return false;
        }
        // Faster than a regular expression.
        return !!ctype_alnum('A' . str_replace(static::NAME_VALID_NON_ALPHANUM, '', $name));
    }
}
