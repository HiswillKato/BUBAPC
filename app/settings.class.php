<?php
/**
 * BUBAPC - Settings management
 *
 * Key-value settings stored in database, with caching.
 */

require_once __DIR__ . '/db.class.php';

class Settings
{
    /** @var array<string, string> In-memory cache */
    private static array $cache = [];

    /**
     * Get a setting value.
     */
    public static function get(string $key, string $fallback = ''): string
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $conn = DB::conn();
        $stmt = $conn->prepare('SELECT value FROM settings WHERE name = ? LIMIT 1');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();

        $value = $row ? (string) $row[0] : $fallback;
        self::$cache[$key] = $value;
        return $value;
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, string $value): bool
    {
        $conn = DB::conn();

        // Check if exists
        $stmt = $conn->prepare('SELECT name FROM settings WHERE name = ? LIMIT 1');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $stmt = $conn->prepare('UPDATE settings SET value = ? WHERE name = ?');
        } else {
            $stmt = $conn->prepare('INSERT INTO settings (name, value) VALUES (?, ?)');
        }
        $stmt->bind_param('ss', $value, $key);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            self::$cache[$key] = $value;
            log_action($conn, 'settings_changed', "Changed setting: $key");
        }
        return $result;
    }

    /**
     * Check if maintenance mode is on.
     */
    public static function isMaintenanceMode(): bool
    {
        return self::get('maintenance_mode', '0') === '1';
    }

    /**
     * Get maintenance message.
     */
    public static function maintenanceMessage(): string
    {
        $msg = self::get('maintenance_message', '');
        return $msg !== '' ? $msg : 'We are carrying out some work on the website. Please check back shortly.';
    }

    /**
     * Get all settings as array.
     */
    public static function all(): array
    {
        $conn = DB::conn();
        $result = $conn->query('SELECT name, value FROM settings');
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['name']] = $row['value'];
        }
        return $settings;
    }
}