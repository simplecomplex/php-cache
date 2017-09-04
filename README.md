## Cache ##

- [Installation](#installation)
- [Requirements](#requirements)

### Scope ###

Caching of complex variables, and variables which are expensive to generate.  
Like configuration, localization and service responses.

Not page caching, no [stampede protection](https://en.wikipedia.org/wiki/Cache_stampede).

### Cache abstraction ###

**``` CacheBroker ```** decouples code using cache from the actual PSR-16 cache implementation.

Defines three cache class aliases:

- _variable time-to-live_ (default ttl and set() arg ttl)
- _fixed time-to-live_ (default ttl, set() arg ttl ignored)
- _persistent_ (default ttl 'forever' and set() arg ttl ignored)

Plus three like the above which allow long keys; length 128 instead of the PSR-16 compliant 64.

#### How to use ####

Ask ``` CacheBroker ``` for an aliased type of cache instance - do _not_ instantiate a particular cache class.

Extend ``` CacheBroker ```, if you later want to switch from say file-based to database-based caching.

#### Dependency injection container ID: cache-broker ####

Recommendation: access (and thus instantiate) the cache broker via DI container ID 'cache-broker'.  
See [SimpleComplex Utils](https://github.com/simplecomplex/php-utils) ``` Dependency ```.

### File-based caching ###

**``` FileCache ```** is a thorough and cautious PSR-16 Simple Cache implementation; file-based.  
Coded defensively - key (and other argument) validation. 

Addresses:

- default time-to-live; ignore set() arg ttl option; no time-to-live
- garbage collection
- clearing all items or expired items only
- exporting all items, to JSON
- building/replacing a cache store during production (using a 'candidate' store)
- CLI interface for clearing items, e.g. via cron
- concurrency issues (storage-wise only)


### Cache management, replacement and backup ###

Defines two extensions to the PSR-16 CacheInterface, implemented by ``` FileCache ```.

**``` ManageableCacheInterface ```**  

- is the cache store new or empty?
- setting default time-to-live; setting 'ignore' set() arg ttl
- clearing and exporting
- listing all cache stores

**``` BackupCacheInterface ```**

- backup/restore
- replacing a store, by building a 'candidate' and switching to that when it's complete

### Example ###

```php
// Bootstrap.
Dependency::genericSet('cache-broker', function () {
    return new \SimpleComplex\Cache\CacheBroker();
});
// ...
// Use.
/** @var \Psr\Container\ContainerInterface $container */
$container = Dependency::container();
/** @var \SimpleComplex\Cache\CacheBroker $cache_broker */
$cache_broker = $container->get('cache-broker');
/**
 * Create or re-initialize a cache store.
 *
 * @var \SimpleComplex\Cache\FileCache $cache_store
 */
$cache_store = $cache_broker->getStore(
    'some-cache-store',
    CacheBroker::CACHE_VARIABLE_TTL
);
unset($cache_broker);
/** @var mixed $whatever */
$whatever = $cache_store->get('some-key', 'the default value');
```

### CLI commands ###

```bash
# List all cache commands and their help.
php cli.phpsh cache -h
# One command's help.
php cli.phpsh cache-xxx -h

# List existing cache stores.
php cli.phpsh cache-list-stores

# Display/get value of a cache item.
php cli.phpsh cache-get store key

# Delete a cache item.
php cli.phpsh cache-delete store key

# Delete all expired items of one or all cache stores.
php cli.phpsh cache-clear-expired

# Delete all items of one or all cache stores.
php cli.phpsh cache-clear

# Backup a cache store.
php cli.phpsh cache-backup store

# Restore a cache store from backup.
php cli.phpsh cache-restore store

# Destroy one or all cache stores.
php cli.phpsh cache-destroy
```

### Installation ###

Create a 'private' files directory alongside the document root dir  
and make it writable for the webserver user (www-data or apache).

Like:  
```/var/www/my-host/```**```http```**  
```/var/www/my-host/```**```private```**

On first cache store instantiation, **```FileCache```** will create directory  
```private/```**```lib/simplecomplex/file-cache```**

If that directory structure isn't suitable, do either:
- supply **```CacheBroker```** (or **```FileCache```** constructor directly) with a 'path' argument
- extend **```FileCache```** and override it's class constant **```PATH_DEFAULT```**

### Requirements ###

- PHP >=7.0
- 64-bit PHP
- [PSR-16 Simple Cache](https://github.com/php-fig/simple-cache)
- [SimpleComplex Utils](https://github.com/simplecomplex/php-utils)

##### Suggestions #####

- [SimpleComplex Inspect](https://github.com/simplecomplex/inspect) (for CLI)
