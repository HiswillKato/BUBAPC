<?php
/**
 * BUBAPC - User authentication and management
 *
 * Mirrors zora's User class pattern. Handles login, registration, sessions,
 * CSRF protection, password resets, and login throttling.
 */

require_once __DIR__ . '/db.class.php';

class User
{
    /** @var array|null Cached current user */
    private static ?array $current = null;

    /** @var string Session name */
    public const SESSION_NAME = 'BUBAPC';

    /**
     * Get current logged-in user, or null if not logged in.
     * Reads from database on first call per request to verify status.
     */
    public static function current(): ?array
    {
        if (self::$current !== null) {
            return self::$current;
        }

        if (empty($_SESSION['user_id'])) {
            return self::$current = null;
        }

        $id = (int) $_SESSION['user_id'];
        $conn = DB::conn();
        $stmt = $conn->prepare('SELECT id, username, full_name, email, phone, role, can_manage_users, status, login, created FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || (int) $user['status'] !== 1) {
            self::logout();
            return self::$current = null;
        }

        $_SESSION['user_data'] = $user;
        return self::$current = $user;
    }

    /**
     * Check if user is logged in.
     */
    public static function isLoggedIn(): bool
    {
        return self::current() !== null;
    }

    /**
     * Get user roles that can access admin panel.
     */
    public static function staffRoles(): array
    {
        return ['admin', 'editor', 'church_admin'];
    }

    /**
     * Check if current user is admin (website admin or church_admin with can_manage_users).
     */
    public static function isAdmin(): bool
    {
        $user = self::current();
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return $user['role'] === 'church_admin' && !empty($user['can_manage_users']);
    }

    /**
     * Check if current user is staff (can access admin panel).
     */
    public static function isStaff(): bool
    {
        $user = self::current();
        return $user && in_array($user['role'], self::staffRoles(), true);
    }

    /**
     * Require login - redirect to login if not authenticated.
     */
    public static function requireLogin(): void
    {
        if (self::isLoggedIn()) {
            return;
        }
        $_SESSION['login_redirect'] = current_page_path();
        flash('info', 'Please log in to continue.');
        redirect('login');
    }

    /**
     * Require admin - redirect if not admin.
     */
    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            flash('error', 'Only somebody with website administrator access can open that page.');
            redirect('admin');
        }
    }

    /**
     * Require staff - redirect if not staff.
     */
    public static function requireStaff(): void
    {
        self::requireLogin();
        if (!self::isStaff()) {
            flash('error', 'The admin panel is for website staff, so that page is not for you.');
            redirect(member_url());
        }
    }

    /**
     * Sign user in after password verification.
     */
    public static function signIn(array $user): void
    {
        $conn = DB::conn();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_data'] = $user;
        $_SESSION['logged_in_at'] = time();

        $stmt = $conn->prepare('UPDATE users SET login = NOW() WHERE id = ?');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $stmt->close();

        log_action($conn, 'user_signed_in', $user['full_name'] . ' signed in');
    }

    /**
     * Log user out and destroy session.
     */
    public static function logout(): void
    {
        $conn = DB::conn();
        $user = self::current();
        if ($user) {
            log_action($conn, 'user_signed_out', $user['full_name'] . ' signed out');
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        self::$current = null;
    }

    /**
     * Get account link for navigation.
     */
    public static function accountLink(): array
    {
        if (!self::isLoggedIn()) {
            return [
                'url'   => url('login'),
                'label' => 'Login',
                'icon'  => 'fa-right-to-bracket',
            ];
        }

        if (self::isStaff()) {
            return [
                'url'   => admin_url(),
                'label' => 'Admin',
                'icon'  => 'fa-shield-halved',
            ];
        }

        return [
            'url'   => member_url(),
            'label' => 'My account',
            'icon'  => 'fa-user',
        ];
    }

    /**
     * Find user by username or email.
     */
    public static function findByLogin(string $login): ?array
    {
        $conn = DB::conn();
        $stmt = $conn->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1');
        $stmt->bind_param('ss', $login, $login);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    /**
     * Find user by ID.
     */
    public static function findById(int $id): ?array
    {
        $conn = DB::conn();
        $stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    /**
     * Create a new user (registration).
     */
    public static function create(array $data): int|false
    {
        $conn = DB::conn();
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $conn->prepare('INSERT INTO users (username, full_name, email, phone, password, role, status, created) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $role = $data['role'] ?? 'member';
        $status = 1;
        $stmt->bind_param('ssssssi', $data['username'], $data['full_name'], $data['email'], $data['phone'] ?? '', $password_hash, $role, $status);
        $result = $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();

        if ($result) {
            log_action($conn, 'user_created', 'Added user ' . $data['full_name']);
        }
        return $result ? $id : false;
    }

    /**
     * Update user details.
     */
    public static function update(int $id, array $data): bool
    {
        $conn = DB::conn();
        $fields = [];
        $types = '';
        $values = [];

        $allowed = ['username', 'full_name', 'email', 'phone', 'role', 'status', 'can_manage_users'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $types .= 's';
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $types .= 'i';
        $values[] = $id;

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            log_action($conn, 'user_updated', 'Changed user ' . ($data['full_name'] ?? $id));
            // Clear cached current user if updating self
            if (self::$current && self::$current['id'] === $id) {
                self::$current = null;
            }
        }
        return $result;
    }

    /**
     * Change password.
     */
    public static function changePassword(int $id, string $new_password): bool
    {
        $conn = DB::conn();
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->bind_param('si', $hash, $id);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            log_action($conn, 'password_changed', 'Changed password for user ' . $id);
        }
        return $result;
    }

    /**
     * Delete user.
     */
    public static function delete(int $id): bool
    {
        $conn = DB::conn();
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            log_action($conn, 'user_deleted', 'Deleted user ' . $id);
        }
        return $result;
    }

    /**
     * List users with optional filters.
     */
    public static function list(array $filters = [], int $limit = 0, int $offset = 0): array
    {
        $conn = DB::conn();
        $where = [];
        $types = '';
        $values = [];

        if (!empty($filters['role'])) {
            $where[] = 'role = ?';
            $types .= 's';
            $values[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $types .= 'i';
            $values[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(username LIKE ? OR full_name LIKE ? OR email LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $types .= 'sss';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit_sql = $limit > 0 ? ' LIMIT ?' : '';
        $offset_sql = $offset > 0 ? ' OFFSET ?' : '';

        if ($limit > 0) {
            $types .= 'i';
            $values[] = $limit;
        }
        if ($offset > 0) {
            $types .= 'i';
            $values[] = $offset;
        }

        $sql = "SELECT * FROM users $where_sql ORDER BY created DESC$limit_sql$offset_sql";
        $stmt = $conn->prepare($sql);
        if ($values) {
            $stmt->bind_param($types, ...$values);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
        return $users;
    }

    /**
     * Count users with filters.
     */
    public static function count(array $filters = []): int
    {
        $conn = DB::conn();
        $where = [];
        $types = '';
        $values = [];

        if (!empty($filters['role'])) {
            $where[] = 'role = ?';
            $types .= 's';
            $values[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $types .= 'i';
            $values[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(username LIKE ? OR full_name LIKE ? OR email LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $types .= 'sss';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) FROM users $where_sql";

        if ($values) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_row();
            $stmt->close();
            return (int) $row[0];
        }

        $result = $conn->query($sql);
        $row = $result->fetch_row();
        return (int) $row[0];
    }

    /* =========================================================================
       LOGIN THROTTLING
       ========================================================================= */

    /**
     * Minutes remaining in lockout for username/IP.
     */
    public static function lockoutMinutes(string $username): int
    {
        $conn = DB::conn();
        $minutes_ago = date('Y-m-d H:i:s', time() - (LOGIN_LOCK_MINUTES * 60));

        $stmt = $conn->prepare('SELECT COUNT(*) AS wrong_count FROM login_attempts WHERE success = 0 AND attempted > ? AND (username = ? OR ip = ?)');
        $name = (string) $username;
        $ip = ip();
        $stmt->bind_param('sss', $minutes_ago, $name, $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return ((int) $row['wrong_count'] < LOGIN_MAX_ATTEMPTS) ? 0 : LOGIN_LOCK_MINUTES;
    }

    /**
     * Record a login attempt.
     */
    public static function recordAttempt(string $username, bool $success): void
    {
        $conn = DB::conn();
        $now = date('Y-m-d H:i:s');
        $name = substr((string) $username, 0, 191);
        $ip = ip();
        $flag = $success ? 1 : 0;

        $stmt = $conn->prepare('INSERT INTO login_attempts (username, ip, success, attempted) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssis', $name, $ip, $flag, $now);
        $stmt->execute();
        $stmt->close();

        if (!$success) {
            log_action($conn, 'login_refused', 'Wrong password for "' . $name . '"');
        }
    }

    /**
     * Clear failed attempts after successful login.
     */
    public static function clearAttempts(string $username): void
    {
        $conn = DB::conn();
        $stmt = $conn->prepare('DELETE FROM login_attempts WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->close();
    }

    /* =========================================================================
       CSRF PROTECTION
       ========================================================================= */

    /**
     * Generate CSRF hidden field.
     */
    public static function csrfField(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
    }

    /**
     * Verify CSRF token.
     */
    public static function verifyCsrf(): void
    {
        $token_form = $_POST['csrf_token'] ?? '';
        $token_session = $_SESSION['csrf_token'] ?? '';

        if ($token_session === '' || !hash_equals($token_session, $token_form)) {
            http_response_code(403);
            exit('<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Request blocked</title></head><body><h1>403 - Request blocked</h1><p>This form has expired. Please go back, reload the page and try again.</p></body></html>');
        }
    }

    /* =========================================================================
       PASSWORD RESET TOKENS
       ========================================================================= */

    /**
     * Issue a password reset token.
     */
    public static function issueResetToken(int $user_id): string|false
    {
        $conn = DB::conn();
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        $stmt = $conn->prepare('INSERT INTO tokens (user, type, hash, expires) VALUES (?, "password_reset", ?, DATE_ADD(NOW(), INTERVAL ? HOUR))');
        $user_id = (int) $user_id;
        $hours = (int) TOKEN_RESET_HOURS;
        $stmt->bind_param('ssi', $user_id, $hash, $hours);
        $result = $stmt->execute();
        $stmt->close();

        return $result ? $raw : false;
    }

    /**
     * Validate a reset token.
     */
    public static function validateResetToken(string $raw_token): ?array
    {
        $conn = DB::conn();
        $hash = hash('sha256', $raw_token);

        $stmt = $conn->prepare('SELECT * FROM tokens WHERE hash = ? AND type = "password_reset" AND used IS NULL AND expires > NOW() LIMIT 1');
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Mark reset token as used.
     */
    public static function useResetToken(int $token_id): void
    {
        $conn = DB::conn();
        $stmt = $conn->prepare('UPDATE tokens SET used = NOW() WHERE id = ? AND used IS NULL');
        $stmt->bind_param('i', $token_id);
        $stmt->execute();
        $stmt->close();
    }
}