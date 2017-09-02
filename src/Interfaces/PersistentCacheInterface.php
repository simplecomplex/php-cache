<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-cache/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache\Interfaces;

use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 Simple Cache with time-to-live forever and arg ttl ignored.
 *
 * @see \Psr\SimpleCache\CacheInterface
 *
 * @package SimpleComplex\Cache
 */
interface PersistentCacheInterface extends CacheInterface
{
}
