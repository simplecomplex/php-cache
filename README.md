## Cache ##

### Scope ###

Caching of complex variables, and variables which are expensive to retrieve or generate.  
Like configuration, localization and service responses.

Not page caching, no [stampede protection](https://en.wikipedia.org/wiki/Cache_stampede).

### Cache abstraction ###

**``` CacheBroker ```** decouples code using cache from the actual PSR-16 cache implementation.

Defines the three cache class aliases:

- _variable time-to-live_ (default ttl and set() arg ttl)
- _fixed time-to-live_ (default ttl, set() arg ttl ignored)
- _persistent_ (default ttl 'forever' and set() arg ttl ignored)

#### How to use ####

Ask ``` CacheBroker ``` for an aliased type of cache instance - do _not_ instantiate a particular cache class.

Extend ``` CacheBroker ```, when you want to switch from say file-based to database-based caching.

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

- is the cache new or empty?
- setting default time-to-live; setting 'ignore' set() arg ttl
- clearing and exporting
- listing all cache stores

**``` BackupCacheInterface ```**

- backup/restore
- replacing a store, by building a 'candidate' and switching to that when it's complete

### Requirements ###

- PHP >=7.0
- 64-bit PHP
- [PSR-16 Simple Cache](https://github.com/php-fig/simple-cache)
- [SimpleComplex Utils](https://github.com/simplecomplex/php-utils)

##### Suggestions #####

- [SimpleComplex Inspect](https://github.com/simplecomplex/inspect) (for CLI)
