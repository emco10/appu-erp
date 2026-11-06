<?php

/**
 * Retry only a refused connection caused by Hostinger's new-connection limit.
 * The factory must only construct PDO: queries and transaction work stay outside
 * this boundary so a retry can never repeat a statement or a committed write.
 */
function app_database_connect(callable $factory, ?callable $pause = null): PDO
{
    $pause ??= static function (int $microseconds): void { usleep($microseconds); };
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        try {
            return $factory();
        } catch (PDOException $exception) {
            $hostConnectionLimit = ($exception->errorInfo[1] ?? null) === 2002
                && stripos($exception->getMessage(), 'Operation not permitted') !== false;
            if (!$hostConnectionLimit || $attempt === 3) {
                throw $exception;
            }
            $pause(1000000);
        }
    }
    throw new LogicException('Database connection attempts exhausted.');
}
