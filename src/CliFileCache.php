<?php
/**
 * SimpleComplex PHP Cache
 * @link      https://github.com/simplecomplex/php-cache
 * @copyright Copyright (c) 2014-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-cache/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Cache;

use SimpleComplex\Utils\CliEnvironment;
use SimpleComplex\Utils\CliCommand;

/**
 * CLI only.
 *
 * Expose/execute JsonLog 'committable' command.
 *
 * Example:
 * @code
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
class CliFileCache
{
    /**
     * Uses CliEnvironment/CliCommand to detect and execute commands.
     *
     * @throws \LogicException
     *      If executed in non-CLI mode.
     */
    public function __construct()
    {
        if (!CliEnvironment::cli()) {
            throw new \LogicException('Cli mode only.');
        }

        $environment = CliEnvironment::getInstance();
        // Declare supported commands.
        $environment->addCommandsAvailable(
            new CliCommand(
                'clear',
                'Delete all cache items of one or all cache stores.',
                [
                    'name' => 'Cache store name. Optional if arg \'all\'.',
                ],
                [
                    'all' => 'All cache stores.',
                ],
                [
                    'a' => 'all',
                ]
            ),
            new CliCommand(
                'clear_expired',
                'Delete all expired cache items of one or all cache stores.',
                [
                    'name' => 'Cache store name.',
                ],
                [
                    'all' => 'All cache stores.',
                ],
                [
                    'a' => 'all',
                ]
            )
        );

        // Let environment map command; if first (non-option) console argument
        // one of our commands.
        $command = $environment->command;
        if (!$command) {
            $environment->echoMessage($environment->commandHelp('none'));
        } else {
            /*switch ($command->name) {
                case 'committable':
                    $verbose = !empty($command->options['verbose']);
                    $logger = empty($command->options['pretty']) ?
                        JsonLog::getInstance() :
                        JsonLogPretty::getInstance();
                    $response = $logger->committable(
                        !empty($command->options['enable']),
                        !empty($command->options['commit']),
                        $verbose
                    );
                    if (!$verbose) {
                        $success = $response;
                    } else {
                        $success = $response['success'];
                    }
                    if (!$verbose) {
                        $msg = !$success ? 'JsonLog is NOT committable.' : 'JsonLog is committable.';
                    } else {
                        $msg = $response['message'];
                        if (!$success) {
                            $msg .= "\n" . 'Code: ' . $response['code'];
                        }
                    }
                    $environment->echoMessage($msg, !$success ? 'warning' : 'success', true);
                    break;
                default:
                    throw new \LogicException(
                        CliEnvironment::class . ' mapped unknown command ' . $command->name . '.'
                    );
            }*/
        }
    }
}
