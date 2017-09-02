<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-cache/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

/**
 * Validate cache key for cache class which allow longer keys.
 *
 * @see KeyLongCacheInterface
 *
 * @package SimpleComplex\Cache
 */
class CacheKeyLong extends CacheKey
{
    /**
     * Transgression PSR-16 Simple Cache requirement (64).
     *
     * @var int
     */
    const VALID_LENGTH_MAX = 128;
}
