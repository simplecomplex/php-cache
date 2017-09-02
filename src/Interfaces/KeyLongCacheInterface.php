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
 * Extension - and transgression - of PSR-16 Simple Cache interface,
 * which allows key length 128 instead of 64.
 *
 * @see \Psr\SimpleCache\CacheInterface
 *
 * @package SimpleComplex\Cache
 */
interface KeyLongCacheInterface extends CacheInterface
{
}
