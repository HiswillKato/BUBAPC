<?php
/**
 * BUBAPC - Visitor analytics
 *
 * Tracks visits without cookies or persistent identifiers.
 * Address + User-Agent hashed to identify unique browsers.
 */

require_once __DIR__ . '/db.class.php';

class Analytics
{
    /**
     * Generate visitor key from IP + User-Agent.
     */
    public static function visitorKey(): string
    {
        $address = ip();
        $browser = agent();

        if ($address === '') {
            $address = 'unknown';
        }

        return hash('sha256', $address . '|' . $browser);
    }

    /**
     * Record a page visit.
     */
    public static function recordVisit(string $path = ''): void
    {
        if (defined('BUBAPC_INSTALLER') || defined('BUBAPC_SKIP_VISITS')) {
            return;
        }

        $conn = DB::conn();
        $key = self::visitorKey();
        $browser = agent();
        $referer = referer();
        $path = substr((string) $path, 0, 190);

        try {
            $stmt = $conn->prepare('SELECT id, seen, visits FROM visitors WHERE ip = ? LIMIT 1');
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row === null) {
                $stmt = $conn->prepare(
                    'INSERT INTO visitors (ip, agent, path, referer, visits, seen)
                     VALUES (?, ?, ?, ?, 1, NOW())'
                );
                $stmt->bind_param('ssss', $key, $browser, $path, $referer);
                $stmt->execute();
                $stmt->close();
                return;
            }

            $stmt = $conn->prepare(
                'UPDATE visitors
                 SET visits = visits + IF(DATE(seen) = CURDATE(), 0, 1),
                     seen = NOW(),
                     path = ?,
                     agent = ?
                 WHERE id = ?'
            );
            $id = (int) $row['id'];
            $stmt->bind_param('ssi', $path, $browser, $id);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable) {
            // Ignore - analytics must never break the page
        }
    }

    /**
     * Delete old visitor records.
     */
    public static function sweep(): int
    {
        $conn = DB::conn();
        $stmt = $conn->prepare(
            'DELETE FROM visitors WHERE seen < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $days = (int) VISITOR_KEEP_DAYS;
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $removed = $stmt->affected_rows;
        $stmt->close();

        return (int) $removed;
    }

    /**
     * Get visitor counts (today and last 7 days).
     */
    public static function counts(): array
    {
        $conn = DB::conn();

        $today = $conn->query(
            "SELECT COUNT(*) AS total FROM visitors
             WHERE seen >= DATE_SUB(CURDATE(), INTERVAL 0 DAY)
               AND seen <  DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
        );
        $week = $conn->query(
            "SELECT COUNT(*) AS total FROM visitors
             WHERE seen >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               AND seen <  DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
        );

        $today_row = $today->fetch_assoc();
        $week_row = $week->fetch_assoc();

        return [
            'today' => (int) ($today_row['total'] ?? 0),
            'week'  => (int) ($week_row['total'] ?? 0),
        ];
    }

    /**
     * Get recent visitors for dashboard.
     */
    public static function recent(int $limit = 10): array
    {
        $conn = DB::conn();
        $stmt = $conn->prepare('SELECT * FROM visitors ORDER BY seen DESC LIMIT ?');
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $visitors = [];
        while ($row = $result->fetch_assoc()) {
            $visitors[] = $row;
        }
        $stmt->close();
        return $visitors;
    }
}