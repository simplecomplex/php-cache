<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2014-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-cache/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

use SimpleComplex\Utils\Interfaces\CliCommandInterface;
use SimpleComplex\Utils\CliEnvironment;
use SimpleComplex\Utils\CliCommand;
use SimpleComplex\Utils\Dependency;
use SimpleComplex\Cache\Interfaces\KeyLongCacheInterface;
use SimpleComplex\Cache\Interfaces\ManageableCacheInterface;
use SimpleComplex\Cache\Interfaces\BackupCacheInterface;

/**
 * CLI only.
 *
 * Expose/execute cache commands.
 *
 * @see simplecomplex_cache_cli()
 *
 * @see FileCache::delete()
 * @see FileCache::clear()
 * @see FileCache::clearExpired()
 * @see FileCache::destroy()
 *
 * @code
 * # CLI
 * cd vendor/simplecomplex/cache/src/cli
 * php cli.phpsh cache -h
 * @endcode
 *
 * @package SimpleComplex\Cache
 */
class CliCache implements CliCommandInterface
{
    /**
     * @var string
     */
    const COMMAND_PROVIDER_ALIAS = 'cache';

    /**
     * Registers CacheBroker CliCommands at CliEnvironment.
     *
     * @throws \LogicException
     *      If executed in non-CLI mode.
     */
    public function __construct()
    {
        if (!CliEnvironment::cli()) {
            throw new \LogicException('Cli mode only.');
        }

        $this->environment = CliEnvironment::getInstance();
        // Declare supported commands.
        $this->environment->registerCommands(
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-list-stores',
                'List all cache stores.',
                [
                    'match' => 'Regex to match against cache store names. Optional.',
                ],
                [
                    'get' => 'Return comma-separated list, don\'t print.',
                ],
                [
                    'g' => 'get',
                ]
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-get',
                'Get a cache item.',
                [
                    'store' => 'Cache store name.',
                    'key' => 'Cache item key.',
                ],
                [
                    'get' => 'Return value, don\'t print it.',
                    'inspect' => 'Print Inspect\'ed value instead of JSON-encoded.',
                ],
                [
                    'g' => 'get',
                    'i' => 'inspect',
                ]
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-delete',
                'Delete a cache item.',
                [
                    'store' => 'Cache store name.',
                    'key' => 'Cache item key.',
                ],
                [],
                []
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-clear-expired',
                'Delete all expired items of one or all cache stores.',
                [
                    'store' => 'Cache store name. Skip if --all.',
                ],
                [
                    'all' => 'All stores.'
                ],
                [
                    'a' => 'all',
                ]
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-clear',
                'Delete all items of one or all cache stores.',
                [
                    'store' => 'Cache store name. Skip if --all.',
                ],
                [
                    'all' => 'All stores.'
                ],
                [
                    'a' => 'all',
                ]
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-backup',
                'Backup a cache store.',
                [
                    'store' => 'Cache store name.',
                    'backup' =>
                        'Without store name, perhaps a timestamp. Optional, defaults to YYYY-MM-DD_HHiiss.',
                ],
                [],
                []
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-restore',
                'Restore a cache store from backup.' . "\n" . 'Ignores --yes/-y pre-confirmation option.',
                [
                    'store' => 'Cache store name.',
                    'backup' => 'Name of the backup, without store name. Perhaps a timestamp.',
                ],
                [],
                []
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-destroy',
                'Destroy one or all cache stores.' . "\n" . 'Ignores --yes/-y pre-confirmation option.',
                [
                    'store' => 'Cache store name. Skip if --all.',
                ],
                [
                    'all' => 'All stores.'
                ],
                [
                    'a' => 'all',
                ]
            )
        );
    }

    /**
     * @var string
     */
    const CLASS_CACHE_BROKER = CacheBroker::class;

    /**
     * @var string
     */
    const CLASS_INSPECT = '\\SimpleComplex\\Inspect\\Inspect';

    /**
     * @var CliCommand
     */
    protected $command;

    /**
     * @var CliEnvironment
     */
    protected $environment;

    /**
     * List all cache stores.
     *
     * @return mixed
     *      Exits if no/falsy option 'get'.
     */
    protected function cmdListStores() /*: void*/
    {
        /**
         * @see simplecomplex_cache_cli()
         */
        $container = Dependency::container();
        // Validate input. ---------------------------------------------
        $match = '';
        if (
            !empty($this->command->arguments['match'])
            && ($match = trim($this->command->arguments['match'])) !== ''
            && !preg_match('/^\/.+\/[a-zA-Z]*$/', $match)
        ) {
            $this->command->inputErrors[] = '\'match\' argument must be slash delimited regular expression.';
        }

        $get = !empty($this->command->options['get']);

        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        // No arg values to list.
        // Check if the command is doable.------------------------------
        // Does that/these store(s) exist?
        if ($container->has('cache-broker')) {
            /** @var CacheBroker $cache_broker */
            $cache_broker_class = get_class($container->get('cache-broker'));
        } else {
            $cache_broker_class = static::CLASS_CACHE_BROKER;
        }
        $cache_class = constant($cache_broker_class . '::CACHE_CLASSES')[CacheBroker::CACHE_BASE];
        if (!method_exists($cache_class, 'listInstances')) {
            $this->environment->echoMessage('Cannot retrieve list of cache store instances via class['
                . $cache_class . '], has no static method listInstances().', 'error');
            exit;
        }
        $stores = forward_static_call($cache_class . '::listInstances');

        // Do it.
        $names = [];
        foreach ($stores as $instance) {
            if (!$match || preg_match($match, $instance->name)) {
                $names[] = $instance->name;
            }
        }
        sort($names);
        if ($get) {
            return join(',', $names);
        }
        $this->environment->echoMessage(join("\n", $names));
        exit;
    }

    /**
     * @return mixed
     *      Exits if no/falsy option 'get'.
     */
    protected function cmdGet()
    {
        /**
         * @see simplecomplex_cache_cli()
         */
        $container = Dependency::container();
        // Validate input. ---------------------------------------------
        $store = '';
        if (empty($this->command->arguments['store'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['store']) ? 'Missing \'store\' argument.' :
                'Empty \'store\' argument.';
        } else {
            $store = $this->command->arguments['store'];
            if (!CacheKey::validate($store)) {
                $this->command->inputErrors[] = 'Invalid \'store\' argument.';
            }
        }
        $key = '';
        if (empty($this->command->arguments['key'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['store']) ? 'Missing \'key\' argument.' :
                'Empty \'key\' argument.';
        } else {
            $key = $this->command->arguments['key'];
            // Use long key checker here, and then check again if store isn't
            // KeyLongCacheInterface.
            if (!CacheKeyLong::validate($key)) {
                $this->command->inputErrors[] = 'Invalid \'key\' argument.';
            }
        }

        $get = !empty($this->command->options['get']);
        $use_inspect = !$get && !empty($this->command->options['inspect']);

        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        if (!$get) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . 'store: ' . $store
                    . "\n" . 'key: ' . $key
                    . (!$this->command->options ? '' : ("\n--" . join(' --', array_keys($this->command->options)))),
                    'hangingIndent'
                )
            );
        }
        // Check if the command is doable.------------------------------
        // Does that store exist?
        if ($container->has('cache-broker')) {
            /** @var CacheBroker $cache_broker */
            $cache_broker_class = get_class($container->get('cache-broker'));
        } else {
            $cache_broker_class = static::CLASS_CACHE_BROKER;
        }
        $cache_class = constant($cache_broker_class . '::CACHE_CLASSES')[CacheBroker::CACHE_BASE];
        if (!method_exists($cache_class, 'listInstances')) {
            $this->environment->echoMessage('Cannot retrieve list of cache store instances via class['
                . $cache_class . '], has no static method listInstances().', 'error');
            exit;
        }
        $stores = forward_static_call($cache_class . '::listInstances');
        $cache_store = null;
        foreach ($stores as $instance) {
            if ($instance->name == $store) {
                /** @var ManageableCacheInterface $cache_store */
                $cache_store = $instance;
                break;
            }
        }
        if (!$cache_store) {
            $this->environment->echoMessage('');
            $this->environment->echoMessage('That cache store doesn\'t exist, store[' . $store . '].', 'warning');
            exit;
        }
        // Check key again, now that we know whether the store allows long keys.
        if (!($cache_store instanceof KeyLongCacheInterface) && !CacheKey::validate($key)) {
            $this->environment->echoMessage('');
            $this->environment->echoMessage(
                'Invalid \'key\' argument, length ' . strlen($key) . ' exceeds max ' . CacheKey::VALID_LENGTH_MAX . '.',
                'notice'
            );
            exit;
        }
        // Do it.
        if (!$cache_store->has($key)) {
            $this->environment->echoMessage('');
            $this->environment->echoMessage('Cache store[' . $store . '] key[' . $key . '] doesn\'t exist.', 'notice');
            exit;
        }
        $value = $cache_store->get($key);
        if ($get) {
            return $value;
        }
        $this->environment->echoMessage('');
        if ($use_inspect) {
            $inspect = null;
            if ($container->has('inspect')) {
                $inspect = $container->get('inspect');
            } elseif (class_exists(static::CLASS_INSPECT)) {
                $class_inspect = static::CLASS_INSPECT;
                $inspect = new $class_inspect($container->has('config') ? $container->get('config') : null);
            }
            if ($inspect) {
                $this->environment->echoMessage($inspect->inspect($value)->toString(true));
                exit;
            }
        }
        $this->environment->echoMessage(
            json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
        exit;
    }

    /**
     * @return void
     *      Exits.
     */
    protected function cmdDelete() /*: void*/
    {
        /**
         * @see simplecomplex_cache_cli()
         */
        $container = Dependency::container();
        // Validate input. ---------------------------------------------
        $store = '';
        if (empty($this->command->arguments['store'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['store']) ? 'Missing \'store\' argument.' :
                'Empty \'store\' argument.';
        } else {
            $store = $this->command->arguments['store'];
            if (!CacheKey::validate($store)) {
                $this->command->inputErrors[] = 'Invalid \'store\' argument.';
            }
        }
        $key = '';
        if (empty($this->command->arguments['key'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['store']) ? 'Missing \'key\' argument.' :
                'Empty \'key\' argument.';
        } else {
            $key = $this->command->arguments['key'];
            // Use long key checker here, and then check again if store isn't
            // KeyLongCacheInterface.
            if (!CacheKeyLong::validate($key)) {
                $this->command->inputErrors[] = 'Invalid \'key\' argument.';
            }
        }
        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        if (!$this->command->preConfirmed) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . 'store: ' . $store
                    . "\n" . 'key: ' . $key,
                    'hangingIndent'
                )
            );
        }
        // Check if the command is doable.------------------------------
        // Does that store exist?
        if ($container->has('cache-broker')) {
            /** @var CacheBroker $cache_broker */
            $cache_broker_class = get_class($container->get('cache-broker'));
        } else {
            $cache_broker_class = static::CLASS_CACHE_BROKER;
        }
        $cache_class = constant($cache_broker_class . '::CACHE_CLASSES')[CacheBroker::CACHE_BASE];
        if (!method_exists($cache_class, 'listInstances')) {
            $this->environment->echoMessage('Cannot retrieve list of cache store instances via class['
                . $cache_class . '], has no static method listInstances().', 'error');
            exit;
        }
        $stores = forward_static_call($cache_class . '::listInstances');
        $cache_store = null;
        foreach ($stores as $instance) {
            if ($instance->name == $store) {
                /** @var ManageableCacheInterface $cache_store */
                $cache_store = $instance;
                break;
            }
        }
        if (!$cache_store) {
            $this->environment->echoMessage('');
            $this->environment->echoMessage('That cache store doesn\'t exist, store[' . $store . '].', 'warning');
            exit;
        }
        // Check key again, now that we know whether the store allows long keys.
        if (!($cache_store instanceof KeyLongCacheInterface) && !CacheKey::validate($key)) {
            $this->environment->echoMessage('');
            $this->environment->echoMessage(
                'Invalid \'key\' argument, length ' . strlen($key) . ' exceeds max ' . CacheKey::VALID_LENGTH_MAX . '.',
                'notice'
            );
            exit;
        }
        // Request confirmation, unless user used the --yes/-y option.
        if (
            !$this->command->preConfirmed
            && !$this->environment->confirm(
                'Delete that cache item? Type \'yes\' or \'y\' to continue:',
                ['yes', 'y'],
                '',
                'Aborted deleting cache item.'
            )
        ) {
            exit;
        }
        // Do it.
        if (!$cache_store->delete($key)) {
            $this->environment->echoMessage('Failed to delete cache store[' . $store . '] key[' . $key . '].', 'error');
        } elseif (!$this->command->silent) {
            $this->environment->echoMessage('Deleted cache store[' . $store . '] key[' . $key . '].', 'success');
        }
        exit;
    }

    /**
     * @param bool $expired
     *
     * @return void
     *      Exits.
     */
    protected function cmdClear($expired = false) /*: void*/
    {
        /**
         * @see simplecomplex_cache_cli()
         */
        $container = Dependency::container();
        // Validate input. ---------------------------------------------
        $store = '';
        $all_stores = !empty($this->command->options['all']);
        if (empty($this->command->arguments['store'])) {
            if (!$all_stores) {
                $this->command->inputErrors[] = !isset($this->command->arguments['store']) ?
                    'Missing \'store\' argument, and option \'all\' not set.' :
                    'Empty \'store\' argument, and option \'all\' not set.';
            }
        } else {
            $store = $this->command->arguments['store'];
            if (!CacheKey::validate($store)) {
                $this->command->inputErrors[] = 'Invalid \'store\' argument.';
            }
            if ($all_stores) {
                $this->command->inputErrors[] = 'Ambiguous input, saw argument \'store\' plus options \'all\'.';
            }
        }
        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        if (!$this->command->preConfirmed) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . (!$all_stores ? ('store: ' . $store) : 'all stores'),
                    'hangingIndent'
                )
            );
        }
        // Check if the command is doable.------------------------------
        // Does that/these store(s) exist?
        if ($container->has('cache-broker')) {
            /** @var CacheBroker $cache_broker */
            $cache_broker_class = get_class($container->get('cache-broker'));
        } else {
            $cache_broker_class = static::CLASS_CACHE_BROKER;
        }
        $cache_class = constant($cache_broker_class . '::CACHE_CLASSES')[CacheBroker::CACHE_BASE];
        if (!method_exists($cache_class, 'listInstances')) {
            $this->environment->echoMessage('Cannot retrieve list of cache store instances via class['
                . $cache_class . '], has no static method listInstances().', 'error');
            exit;
        }
        $stores = forward_static_call($cache_class . '::listInstances');
        // Single store.
        if (!$all_stores) {
            $cache_store = null;
            foreach ($stores as $instance) {
                if ($instance->name == $store) {
                    /** @var ManageableCacheInterface $cache_store */
                    $cache_store = $instance;
                    break;
                }
            }
            if (!$cache_store) {
                $this->environment->echoMessage('That cache store doesn\'t exist, store[' . $store . '].', 'warning');
                exit;
            }
            // Request confirmation, unless user used the --yes/-y option.
            if (
                !$this->command->preConfirmed
                && !$this->environment->confirm(
                    'Delete all' . (!$expired ? '' : ' expired')
                    . ' items of a single cache store? Type \'yes\' or \'y\' to continue:',
                    ['yes', 'y'],
                    '',
                    'Aborted deleting all' . (!$expired ? '' : ' expired') . ' items of a single cache store.'
                )
            ) {
                exit;
            }
            // Do it.
            $success = !$expired ? $cache_store->clear() : $cache_store->clearExpired();
            if ($success === false) {
                $this->environment->echoMessage(
                    'Failed to delete all' . (!$expired ? '' : ' expired') . ' items of cache store[' . $store . '].',
                    'error'
                );
            } elseif (!$this->command->silent) {
                if ($success === true) {
                    $this->environment->echoMessage(
                        'Deleted all' . (!$expired ? '' : ' expired') . ' items of cache store[' . $store . '].',
                        'success'
                    );
                }
                else {
                    $this->environment->echoMessage(
                        'Deleted ' . (!$expired ? 'all' : $success) . (!$expired ? '' : ' expired')
                        . ' items of cache store[' . $store . '].',
                        'success'
                    );
                }
            }
            exit;
        } else {
            // Request confirmation, unless user used the --yes/-y option.
            if (
                !$this->command->preConfirmed
                && !$this->environment->confirm(
                    'Delete all' . (!$expired ? '' : ' expired')
                    . ' items of all cache stores? Type \'yes\' or \'y\' to continue:',
                    ['yes', 'y'],
                    '',
                    'Aborted deleting all' . (!$expired ? '' : ' expired') . ' items of all cache stores.'
                )
            ) {
                exit;
            }
            // Do it.
            $n_stores = $errors = 0;
            foreach ($stores as $instance) {
                ++$n_stores;
                /** @var ManageableCacheInterface $cache_store */
                $cache_store = $instance;
                $success = $cache_store->clearExpired();
                if ($success === false) {
                    ++$errors;
                    $this->environment->echoMessage(
                        'Failed to delete all' . (!$expired ? '' : ' expired') . ' items of cache store['
                        . $instance->name . '].',
                        'error'
                    );
                } elseif (!$this->command->silent) {
                    if ($success === true) {
                        $this->environment->echoMessage(
                            'Deleted all' . (!$expired ? '' : ' expired') . ' items of cache store['
                            . $instance->name . '].',
                            'notice'
                        );
                    }
                    else {
                        $this->environment->echoMessage(
                            'Deleted ' . (!$expired ? 'all' : $success) . (!$expired ? '' : ' expired')
                            . ' items of cache store[' . $instance->name . '].',
                            'notice'
                        );
                    }
                }
            }
            if ($errors) {
                $this->environment->echoMessage(
                    'Deleted all' . (!$expired ? '' : ' expired') . ' items of ' . $n_stores
                    . ' cache stores, encountering ' . $errors . ' errors.',
                    'warning'
                );
            } elseif (!$this->command->silent) {
                $this->environment->echoMessage(
                    'Deleted all' . (!$expired ? '' : ' expired') . ' items of ' . $n_stores . ' cache stores.',
                    'success'
                );
            }
        }
        exit;
    }

    /**
     * @return void
     *      Exits.
     */
    protected function cmdBackup()
    {
        /**
         * @see simplecomplex_cache_cli()
         */
        $container = Dependency::container();
        // Validate input. ---------------------------------------------
        $store = '';
        if (empty($this->command->arguments['store'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['store']) ? 'Missing \'store\' argument.' :
                'Empty \'store\' argument.';
        } else {
            $store = $this->command->arguments['store'];
            if (!CacheKey::validate($store)) {
                $this->command->inputErrors[] = 'Invalid \'store\' argument.';
            }
        }
        if (!empty($this->command->arguments['backup'])) {
            $backup = $this->command->arguments['backup'];
            if (strpos($backup, '/') !== false || (DIRECTORY_SEPARATOR == '\\' && strpos($backup, '\\') !== false)) {
                $this->command->inputErrors[] = 'The \'backup\' argument must be a name, not a path.';
            }
        } else {
            $backup = date('Y-m-d_His');
        }
        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        if (!$this->command->preConfirmed) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . 'store: ' . $store
                    . "\n" . 'backup: ' . $backup
                    . (!$this->command->options ? '' : ("\n--" . join(' --', array_keys($this->command->options)))),
                    'hangingIndent'
                )
            );
        }
        // Check if the command is doable.------------------------------
        // Does that store exist?
        if ($container->has('cache-broker')) {
            /** @var CacheBroker $cache_broker */
            $cache_broker_class = get_class($container->get('cache-broker'));
        } else {
            $cache_broker_class = static::CLASS_CACHE_BROKER;
        }
        $cache_class = constant($cache_broker_class . '::CACHE_CLASSES')[CacheBroker::CACHE_BASE];
        if (!method_exists($cache_class, 'listInstances')) {
            $this->environment->echoMessage('Cannot retrieve list of cache store instances via class['
                . $cache_class . '], has no static method listInstances().', 'error');
            exit;
        }
        $stores = forward_static_call($cache_class . '::listInstances');
        $cache_store = null;
        foreach ($stores as $instance) {
            if ($instance->name == $store) {
                /** @var BackupCacheInterface $cache_store */
                $cache_store = $instance;
                break;
            }
        }
        if (!$cache_store) {
            $this->environment->echoMessage('');
            $this->environment->echoMessage('That cache store doesn\'t exist, store[' . $store . '].', 'warning');
            exit;
        }
        // Request confirmation, unless user used the --yes/-y option.
        if (
            !$this->command->preConfirmed
            && !$this->environment->confirm(
                'Backup cache store?  Type \'yes\' or \'y\' to continue:',
                    ['yes', 'y'],
                '',
                'Aborted backing up cache store.'
            )
        ) {
            exit;
        }
        // Do it.
        $success = $cache_store->backup($backup);
        if ($success === false) {
            $this->environment->echoMessage('Failed to backup store[' . $store . '].', 'error');
        } elseif (!$this->command->silent) {
            if (is_int($success)) {
                $this->environment->echoMessage(
                    'Backed up store[' . $store . '] to backup[' . $backup . '], copying ' . $success . ' items.',
                    'success'
                );
            } else {
                $this->environment->echoMessage(
                    'Backed up store[' . $store . '] to backup[' . $backup . '].',
                    'success'
                );
            }
        }
        exit;
    }

    /**
     * Ignores pre-confirmation --yes/-y option,
     * unless .risky_command_skip_confirm file placed in document root.
     *
     * @return void
     *      Exits.
     */
    protected function cmdRestore()
    {
        /**
         * @see simplecomplex_cache_cli()
         */
        $container = Dependency::container();
        // Validate input. ---------------------------------------------
        $store = '';
        if (empty($this->command->arguments['store'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['store']) ? 'Missing \'store\' argument.' :
                'Empty \'store\' argument.';
        } else {
            $store = $this->command->arguments['store'];
            if (!CacheKey::validate($store)) {
                $this->command->inputErrors[] = 'Invalid \'store\' argument.';
            }
        }
        $backup = '';
        if (empty($this->command->arguments['backup'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['store']) ?
                'Missing \'backup\' argument.' : 'Empty \'backup\' argument.';
        } else {
            $backup = $this->command->arguments['backup'];
            if (strpos($backup, '/') !== false || (DIRECTORY_SEPARATOR == '\\' && strpos($backup, '\\') !== false)) {
                $this->command->inputErrors[] = 'The \'backup\' argument must be a name, not a path.';
            }
        }
        // Pre-confirmation --yes/-y ignored for this command.
        if ($this->environment->riskyCommandRequireConfirm && $this->command->preConfirmed) {
            $this->command->inputErrors[] = 'Pre-confirmation \'yes\'/-y option not supported for this command,'
                . "\n" . 'unless env var PHP_LIB_SIMPLECOMPLEX_UTILS_CLI_SKIP_CONFIRM'
                . "\n" . 'or .risky_command_skip_confirm file in document root.';
        }
        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        if ($this->environment->riskyCommandRequireConfirm || !$this->command->preConfirmed) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . 'store: ' . $store
                    . "\n" . 'backup: ' . $backup
                    . (!$this->command->options ? '' : ("\n--" . join(' --', array_keys($this->command->options)))),
                    'hangingIndent'
                )
            );
        }
        // Check if the command is doable.------------------------------
        // Does that store exist?
        if ($container->has('cache-broker')) {
            /** @var CacheBroker $cache_broker */
            $cache_broker_class = get_class($container->get('cache-broker'));
        } else {
            $cache_broker_class = static::CLASS_CACHE_BROKER;
        }
        $cache_class = constant($cache_broker_class . '::CACHE_CLASSES')[CacheBroker::CACHE_BASE];
        if (!method_exists($cache_class, 'listInstances')) {
            $this->environment->echoMessage('Cannot retrieve list of cache store instances via class['
                . $cache_class . '], has no static method listInstances().', 'error');
            exit;
        }
        $stores = forward_static_call($cache_class . '::listInstances');
        $cache_store = null;
        foreach ($stores as $instance) {
            if ($instance->name == $store) {
                /** @var BackupCacheInterface $cache_store */
                $cache_store = $instance;
                break;
            }
        }
        if (!$cache_store) {
            $this->environment->echoMessage('');
            $this->environment->echoMessage('That cache store doesn\'t exist, store[' . $store . '].', 'warning');
            exit;
        }
        // Request confirmation, ignore --yes/-y pre-confirmation option;
        // unless .risky_command_skip_confirm file placed in document root.
        if ($this->environment->riskyCommandRequireConfirm) {
            if (!$this->environment->confirm(
                'Restore cache store from backup? Type \'yes\' to continue:',
                ['yes'],
                '',
                'Aborted restoring cache store from backup.'
            )) {
                exit;
            }
        } elseif (!$this->command->preConfirmed && !$this->environment->confirm(
                'Restore cache store from backup? Type \'yes\' or \'y\' to continue:',
                ['yes', 'y'],
                '',
                'Aborted restoring cache store from backup.'
            )) {
            exit;
        }
        // Do it.
        $success = $cache_store->restore($backup);
        if ($success === false) {
            $this->environment->echoMessage('Failed to restore store[' . $store . '] from backup.', 'error');
        } elseif (!$this->command->silent) {
            if (is_int($success)) {
                $this->environment->echoMessage(
                    'Restored store[' . $store . '] from backup[' . $backup . '], copying ' . $success . ' items.',
                    'success'
                );
            } else {
                $this->environment->echoMessage(
                    'Restored store[' . $store . '] from backup[' . $backup . '].',
                    'success'
                );
            }
        }
        exit;
    }

    /**
     * Ignores pre-confirmation --yes/-y option,
     * unless .risky_command_skip_confirm file placed in document root.
     *
     * @return void
     *      Exits.
     */
    protected function cmdDestroy() /*: void*/
    {
        /**
         * @see simplecomplex_cache_cli()
         */
        $container = Dependency::container();
        // Validate input. ---------------------------------------------
        $store = '';
        $all_stores = !empty($this->command->options['all']);
        if (empty($this->command->arguments['store'])) {
            if (!$all_stores) {
                $this->command->inputErrors[] = !isset($this->command->arguments['store']) ?
                    'Missing \'store\' argument, and option \'all\' not set.' :
                    'Empty \'store\' argument, and option \'all\' not set.';
            }
        } else {
            $store = $this->command->arguments['store'];
            if (!CacheKey::validate($store)) {
                $this->command->inputErrors[] = 'Invalid \'store\' argument.';
            }
            if ($all_stores) {
                $this->command->inputErrors[] = 'Ambiguous input, saw argument \'store\' plus options \'all\'.';
            }
        }
        // Pre-confirmation --yes/-y ignored for this command.
        if ($this->environment->riskyCommandRequireConfirm && $this->command->preConfirmed) {
            $this->command->inputErrors[] = 'Pre-confirmation \'yes\'/-y option not supported for this command,'
                . "\n" . 'unless env var PHP_LIB_SIMPLECOMPLEX_UTILS_CLI_SKIP_CONFIRM'
                . "\n" . 'or .risky_command_skip_confirm file in document root.';
        }
        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        if ($this->environment->riskyCommandRequireConfirm || !$this->command->preConfirmed) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . (!$all_stores ? ('store: ' . $store) : 'all stores'),
                    'hangingIndent'
                )
            );
        }
        // Check if the command is doable.------------------------------
        // Does that/these store(s) exist?
        if ($container->has('cache-broker')) {
            /** @var CacheBroker $cache_broker */
            $cache_broker_class = get_class($container->get('cache-broker'));
        } else {
            $cache_broker_class = static::CLASS_CACHE_BROKER;
        }
        $cache_class = constant($cache_broker_class . '::CACHE_CLASSES')[CacheBroker::CACHE_BASE];
        if (!method_exists($cache_class, 'listInstances')) {
            $this->environment->echoMessage('Cannot retrieve list of cache store instances via class['
                . $cache_class . '], has no static method listInstances().', 'error');
            exit;
        }
        $stores = forward_static_call($cache_class . '::listInstances');
        // Single store.
        if (!$all_stores) {
            $cache_store = null;
            foreach ($stores as $instance) {
                if ($instance->name == $store) {
                    /** @var ManageableCacheInterface $cache_store */
                    $cache_store = $instance;
                    break;
                }
            }
            if (!$cache_store) {
                $this->environment->echoMessage('That cache store doesn\'t exist, store[' . $store . '].', 'warning');
                exit;
            }
            // Request confirmation, ignore --yes/-y pre-confirmation option,
            // unless .risky_command_skip_confirm file placed in document root.
            if ($this->environment->riskyCommandRequireConfirm) {
                if (!$this->environment->confirm(
                    'Destroy a single cache store? Type \'yes\' to continue:',
                    ['yes'],
                    '',
                    'Aborted destroying a single cache store.'
                )) {
                    exit;
                }
            } elseif (!$this->command->preConfirmed && !$this->environment->confirm(
                    'Destroy a single cache store? Type \'yes\' or \'y\' to continue:',
                    ['yes', 'y'],
                    '',
                    'Aborted destroying a single cache store.'
                )) {
                exit;
            }
            // Do it.
            if (!$cache_store->destroy()) {
                $this->environment->echoMessage('Failed to destroy cache store[' . $store . '].', 'error');
            } elseif (!$this->command->silent) {
                $this->environment->echoMessage('Destroyed cache store[' . $store . '].', 'success');
            }
            exit;
        } else {
            // Request confirmation, ignore --yes/-y pre-confirmation option;
            // unless .risky_command_skip_confirm file placed in document root.
            if ($this->environment->riskyCommandRequireConfirm) {
                if (!$this->environment->confirm(
                    'Destroy all cache stores? Type \'yes\' to continue:',
                    ['yes'],
                    '',
                    'Aborted destroying all cache stores.'
                )) {
                    exit;
                }
            } elseif (!$this->command->preConfirmed && !$this->environment->confirm(
                    'Destroy all cache stores? Type \'yes\' or \'y\' to continue:',
                    ['yes', 'y'],
                    '',
                    'Aborted destroying all cache stores.'
                )) {
                exit;
            }
            // Do it.
            $n_stores = $errors = 0;
            foreach ($stores as $instance) {
                ++$n_stores;
                /** @var ManageableCacheInterface $cache_store */
                $cache_store = $instance;
                if (!$cache_store->destroy()) {
                    ++$errors;
                    $this->environment->echoMessage('Failed to destroy cache store[' . $instance->name . '].', 'error');
                } elseif (!$this->command->silent) {
                    $this->environment->echoMessage('Destroyed cache store[' . $instance->name . '].', 'notice');
                }
            }
            if ($errors) {
                $this->environment->echoMessage(
                    'Destroyed all cache stores, encountering ' . $errors . ' errors.',
                    'warning'
                );
            } elseif (!$this->command->silent) {
                $this->environment->echoMessage('Destroyed ' . $n_stores . ' cache stores.', 'success');
            }
        }
        exit;
    }


    // CliCommandInterface.-----------------------------------------------------

    /**
     * @return string
     */
    public function commandProviderAlias(): string
    {
        return static::COMMAND_PROVIDER_ALIAS;
    }

    /**
     * @param CliCommand $command
     *
     * @return mixed
     *      Return value of the executed command, if any.
     *      May well exit.
     *
     * @throws \LogicException
     *      If the command mapped by CliEnvironment
     *      isn't this provider's command.
     */
    public function executeCommand(CliCommand $command)
    {
        $this->command = $command;
        $this->environment = CliEnvironment::getInstance();

        switch ($command->name) {
            case static::COMMAND_PROVIDER_ALIAS . '-list-stores':
                return $this->cmdListStores();
            case static::COMMAND_PROVIDER_ALIAS . '-get':
                return $this->cmdGet();
            case static::COMMAND_PROVIDER_ALIAS . '-delete':
                $this->cmdDelete();
                exit;
            case static::COMMAND_PROVIDER_ALIAS . '-clear-expired':
                $this->cmdClear(true);
                exit;
            case static::COMMAND_PROVIDER_ALIAS . '-clear':
                $this->cmdClear();
                exit;
            case static::COMMAND_PROVIDER_ALIAS . '-backup':
                $this->cmdBackup();
                exit;
            case static::COMMAND_PROVIDER_ALIAS . '-restore':
                $this->cmdRestore();
                exit;
            case static::COMMAND_PROVIDER_ALIAS . '-destroy':
                $this->cmdDestroy();
                exit;
            default:
                throw new \LogicException(
                    'Command named[' . $command->name . '] is not provided by class[' . get_class($this) . '].'
                );
        }
    }
}
