<?php

declare(strict_types=1);

namespace App\Support;

use App\Helpers\Response;
use Throwable;

final class ErrorHandler
{
    private static string $logFile = '';

    public static function register(string $appEnv, string $logFile): void
    {
        self::$logFile = $logFile;
        $isProduction = $appEnv === 'production';

        error_reporting(E_ALL);
        ini_set('display_errors', $isProduction ? '0' : '1');
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);

        set_exception_handler(
            static function (Throwable $exception) use ($isProduction, $logFile): void {
                self::log($exception, $logFile);

                if (!$isProduction) {
                    http_response_code(500);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo (string) $exception;
                    return;
                }

                Response::json([
                    'success' => false,
                    'message' => 'Server error',
                ], 500);
            }
        );

        register_shutdown_function(
            static function () use ($isProduction, $logFile): void {
                $error = error_get_last();

                if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                    return;
                }

                error_log(sprintf(
                    '[%s] Fatal error: %s in %s:%s',
                    date(DATE_ATOM),
                    $error['message'],
                    $error['file'],
                    $error['line']
                ), 3, $logFile);

                if ($isProduction && !headers_sent()) {
                    Response::json([
                        'success' => false,
                        'message' => 'Server error',
                    ], 500);
                }
            }
        );
    }

    public static function log(Throwable $exception, ?string $logFile = null): void
    {
        $logFile ??= self::$logFile;

        if ($logFile === '') {
            return;
        }

        error_log(sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date(DATE_ATOM),
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        ), 3, $logFile);
    }
}
