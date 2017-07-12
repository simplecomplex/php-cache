<?php

namespace SimpleComplex\Cache\Exception;

/**
 * PSR-16 Simple Cache requires that we use their exception type for bad key
 * argument.
 * Extended to be identifiable as belonging to this package.
 *
 * @package SimpleComplex\Cache
 */

class CacheInvalidArgumentException extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException
{

}
