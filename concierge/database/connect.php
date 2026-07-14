<?php

declare(strict_types=1);

/**
 * Returns one shared connection to the Concierge SQLite database.
 */
function conciergeDatabase(): PDO
{
    static $database = null;

    if ($database instanceof PDO) {
        return $database;
    }

    $databasePath = __DIR__ . '/concierge.db';

    try {
        $database = new PDO('sqlite:' . $databasePath);

        $database->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $database->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );

        $database->setAttribute(
            PDO::ATTR_EMULATE_PREPARES,
            false
        );

        /*
         * Improves reliability when multiple visitors use the application.
         */
        $database->exec('PRAGMA foreign_keys = ON;');
        $database->exec('PRAGMA busy_timeout = 5000;');
        $database->exec('PRAGMA journal_mode = WAL;');

        return $database;
    } catch (PDOException $exception) {
        error_log(
            'Concierge database connection failed: '
            . $exception->getMessage()
        );

        throw new RuntimeException(
            'The Concierge database is temporarily unavailable.'
        );
    }
}
