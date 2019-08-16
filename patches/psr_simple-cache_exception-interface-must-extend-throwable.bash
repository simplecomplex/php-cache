#
# FIX
# \Psr\SimpleCache\CacheException must extend \Throwable.
#
# REASON
# Any exception interface should extend Throwable;
# not doing so would only be PHP <7 compatible (no Throwable interface).
# The CacheException should not force users of a cache library
# to explicit CacheException in all catches catching Throwable,
# just to make IDEs/code checkers understand that CacheException
# does get caught when Throwable does.
#
# TARGET
# vendor/psr/simple-cache/src/CacheException.php
#
# PATCH
# vendor/simplecomplex/cache/patches/psr_simple-cache_exception-interface-must-extend-throwable.patch
#

# Place yourself in dir of target file.
cd vendor/psr/simple-cache/src

# Apply patch placed in simplecomplex/cache library.
patch -p1 < ../../../simplecomplex/cache/patches/psr_simple-cache_exception-interface-must-extend-throwable.patch
