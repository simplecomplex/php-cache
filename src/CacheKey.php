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
 * Validate cache key.
 *
 * Stricter than PSR-16 Simple Cache to be compatible with the most basic cache
 * storage type; file.
 *
 * PSR-16 requirements:
 * - at least: a-zA-Z\d_.
 * - not: {}()/\@:
 * - length: >=2 <=64
 *
 * @code
 * use SimpleComplex\Cache\CacheKey;
 *
 * if (!CacheKey::validate($key)) {
 *    throw new \InvalidArgumentException('Arg key is not valid, key[' . $key . '].');
 * }
 * @endcode
 *
 * @package SimpleComplex\Cache
 */
class CacheKey
{
    /**
     * @var int
     */
    const VALID_LENGTH_MIN = 2;

    /**
     * PSR-16 Simple Cache requirement.
     *
     * @var int
     */
    const VALID_LENGTH_MAX = 64;

    /**
     * Legal non-alphanumeric characters of a cache key.
     *
     * PSR-16 requirements:
     * - at least: a-zA-Z\d_.
     * - not: {}()/\@:
     * - length: >=2 <=64
     *
     * First char cannot be hyphen, because that could break CLI interaction.
     *
     * These keys are selected because they would work in the most basic cache
     * implementation; that is: file (dir names and filenames).
     * Parentheses and colon would have worked too, but forbidden by PSR-16.
     */
    const VALID_NON_ALPHANUM = [
        '-',
        '.',
        '[',
        ']',
        '_'
    ];

    /**
     * Checks that length and content is legal.
     *
     * First char cannot be hyphen, because that could break CLI interaction.
     *
     * @param string $key
     *
     * @return bool
     */
    public static function validate(string $key) : bool
    {
        $le = strlen($key);
        if ($le < static::VALID_LENGTH_MIN || $le > static::VALID_LENGTH_MAX) {
            return false;
        }
        if ($key{0} === '-') {
            return false;
        }
        // Faster than a regular expression.
        // Prefix letter to make key without any alphanum pass.
        return !!ctype_alnum('A' . str_replace(static::VALID_NON_ALPHANUM, '', $key));
    }
}
