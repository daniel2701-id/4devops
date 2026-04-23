<?php
// ============================================================
//  CareConnect – PDO Database Connection (Singleton)
// ============================================================

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone()    {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Never expose DB errors to user
                error_log('DB Connection Error: ' . $e->getMessage());
                http_response_code(503);
                die('Service temporarily unavailable. Please try again later.');
            }
        }
        return self::$instance;
    }
}

/**
 * Convenience helper: returns the shared PDO instance.
 */
function db(): PDO
{
    return Database::getInstance();
}
