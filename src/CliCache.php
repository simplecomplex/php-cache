<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2014-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-cache/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

use SimpleComplex\Utils\CliCommandInterface;
use SimpleComplex\Utils\CliEnvironment;
use SimpleComplex\Utils\CliCommand;

/**
 * CLI only.
 *
 * Expose/execute JsonLog 'committable' command.
 *
 * Example:
 * @code
 * @todo: cache not jsonlog...
 * cd vendor/simplecomplex/json-log/src/cli
 * # Execute 'committable' command.
 * php JsonLogCli.phpsh committable --enable --commit --verbose
 * @endcode
 *
 * @see FileCache::clear()
 * @see FileCache::clearExpired()
 *
 * Script only class for IDEs to find it. Unknown to Composer autoloader.
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

        $cli_env = CliEnvironment::getInstance();
        // Declare supported commands.
        $cli_env->registerCommands(
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-clear',
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
                'Delete all expired cache items of one or all cache stores.',
                [
                    'store' => 'Cache store name. Optional if option \'all\'.',
                ],
                [
                    'all' => 'All cache stores.',
                ],
                [
                    'a' => 'all',
                ]
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-clear-all',
                'Delete all cache items of one or all cache stores.',
                [
                    'store' => 'Cache store name. Optional if option \'all\'.',
                ],
                [
                    'all' => 'All cache stores.',
                ],
                [
                    'a' => 'all',
                ]
            )
        );
    }

    /**
     * To use class extending CacheBroker, call [ExtendedCacheBroker]::getInstance()
     * before instantiating CliCache.
     *
     * @return \SimpleComplex\Cache\CacheBroker
     */
    protected function getMainInstance()
    {
        // getInstance() returns first CacheBroker or CacheBroker child
        // instantiated via getInstance().
        return CacheBroker::getInstance();
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
     * @return void
     *      Must exit.
     *
     * @throws \LogicException
     *      If the command mapped by CliEnvironment
     *      isn't this provider's command.
     */
    public function executeCommand(CliCommand $command)
    {
        switch ($command->name) {
            case static::COMMAND_PROVIDER_ALIAS . '-clear':
                $store = $key = '';
                // Validate input. ---------------------------------------------
                if (empty($command->arguments['store'])) {
                    $command->inputErrors[] = !isset($command->arguments['store']) ? 'Missing \'store\' argument.' :
                        'Empty \'store\' argument.';
                } else {
                    $store = $command->arguments['store'];
                }
                if (empty($command->arguments['key'])) {
                    $command->inputErrors[] = !isset($command->arguments['store']) ? 'Missing \'key\' argument.' :
                        'Empty \'key\' argument.';
                } else {
                    $key = $command->arguments['key'];
                }
                // Dependency.
                $cli_env = CliEnvironment::getInstance();
                if ($command->inputErrors) {
                    foreach ($command->inputErrors as $msg) {
                        $cli_env->echoMessage(
                            $cli_env->format($msg, 'hangingIndent'),
                            'notice'
                        );
                    }
                    // This command's help text.
                    $cli_env->echoMessage("\n" . $command);
                    exit;
                }
                // Display command and the arg values used.---------------------
                $cli_env->echoMessage(
                    $cli_env->format(
                        $cli_env->format($command->name, 'emphasize') . ': ' . $command->description
                        . "\n" . 'store: ' . $store
                        . "\n" . 'key: ' . $key,
                        'hangingIndent'
                    )
                );
                // Check if the command is doable.------------------------------
                $cache_store = CacheBroker::getInstance()->getStore($store);
                if (!$cache_store) {
                    $cli_env->echoMessage('Failed to get cache store.', 'error');
                    exit;
                }



                if (!$command->preConfirmed && !$cli_env->confirm()) {
                    exit;
                }

                $cli_env->echoMessage('Now do execute...');
                echo \SimpleComplex\Inspect\Inspect::getInstance()->inspect($command)->toString(true) . "\n";

                exit;
            default:
                throw new \LogicException(
                    'Command named[' . $command->name . '] is not provided by class[' . get_class($this) . '].'
                );
        }
    }
}
