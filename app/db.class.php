<?php
/**
 * BUBAPC - Database connection wrapper
 *
 * Single mysqli connection shared across the request, created on first use.
 * Mirrors zora's db.class.php pattern.
 */

class DB
{
    private static ?mysqli $conn = null;

    public static function conn(): mysqli
    {
        if (self::$conn === null) {
            self::$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (self::$conn->connect_errno) {
                throw new mysqli_sql_exception('Database connection failed: ' . self::$conn->connect_error);
            }
            self::$conn->set_charset('utf8mb4');
        }
        return self::$conn;
    }

    public static function close(): void
    {
        if (self::$conn !== null) {
            self::$conn->close();
            self::$conn = null;
        }
    }
}