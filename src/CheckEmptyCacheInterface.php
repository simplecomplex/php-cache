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

/**
 * Extension of PSR-16 Simple Cache interface
 *
 * @see \Psr\SimpleCache\CacheInterface
 *
 * @package SimpleComplex\Cache
 */
interface CheckEmptyCacheInterface extends CacheInterface
{
    /**
     * Check if the cache store has any items at all.
     *
     * @return bool
     */
    public function empty() : bool;
}
