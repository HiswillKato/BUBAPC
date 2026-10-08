<?php
/**
 * BUBAPC - Admin: the list of everybody who can sign in
 *
 * Only an admin can see this page. There are three roles: an admin can do
 * anything, an editor can add and change the content but cannot reach this
 * page, the settings or the people, and a church member can only edit their own
 * details and is turned away from the whole admin panel by require_staff().
 *
 * Members are here because they signed themselves up on register.php, so this
 * is where the church switches somebody off without deleting what they typed,
 * and where an admin can add a member by hand for somebody who cannot use the
 * form.
 */

require_once __DIR__ . '/../../app/init.php';
/* The shared buttons, fields and checks are needed before the saving below. */
require_once __DIR__ . '/../../app/includes/admin/parts.php';
require_admin();

$active_module = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = post_text('action');
    $user_id = (int) ($_POST['id'] ?? 0);
    $other = admin_row($conn, 'users', $user_id);

    if (!$other) {
        flash('error', 'That account is no longer here.');
        redirect(admin_url('users'));
    }

    $me = current_user();

    if ($action === 'delete') {
        /* Signing yourself out of your own account would leave nobody to
           restore it, so that one button is not offered. */
        if ($user_id === (int) $me['id']) {
            flash('error', 'You cannot delete your own account.');
            redirect(admin_url('users'));
        }

        $statement = $conn->prepare("DELETE FROM users WHERE id = ?");
        $statement->bind_param('i', $user_id);
        $statement->execute();
        $statement->close();

        log_action($conn, 'user_deleted', 'The account for ' . $other['full_name']);
        flash('success', 'The account for ' . $other['full_name'] . ' has been deleted.');
    } elseif ($action === 'toggle') {
        if ($user_id === (int) $me['id']) {
            flash('error', 'You cannot switch off your own account.');
            redirect(admin_url('users'));
        }

        $new_status = (int) $other['status'] === 1 ? 0 : 1;

        $statement = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $statement->bind_param('ii', $new_status, $user_id);
        $statement->execute();
        $statement->close();

        log_action($conn, 'user_switched', $new_status === 1
            ? $other['full_name'] . ' can sign in again'
            : $other['full_name'] . ' can no longer sign in. The account is kept.');

        flash('success', $new_status === 1
            ? $other['full_name'] . ' can sign in again.'
            : $other['full_name'] . ' can no longer sign in. The account is kept.');
    }

    redirect(admin_url('users'));
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$role_filter = isset($_GET['role']) ? (string) $_GET['role'] : '';
/* The four roles, spelled out so an address that asks for a role nobody has
   treats it as no filter at all rather than as a search term. */
if (!in_array($role_filter, ['admin', 'editor', 'church_admin', 'member'], true)) {
    $role_filter = '';
}

$where = [];
$bind_types = '';
$bind_values = [];

if ($search !== '') {
    $where[] = '(username LIKE ? OR full_name LIKE ? OR email LIKE ?)';
    $like = '%' . $search . '%';
    $bind_types .= 'sss';
    $bind_values[] = $like;
    $bind_values[] = $like;
    $bind_values[] = $like;
}
if ($role_filter !== '') {
    $where[] = 'role = ?';
    $bind_types .= 's';
    $bind_values[] = $role_filter;
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

if ($bind_values) {
    /* COUNT(*) and not SELECT *, because this one is asking how many rows match
       and then reading the COUNT(*) column out of the answer. */
    $statement = $conn->prepare("SELECT COUNT(*) FROM users" . $where_sql);
    $statement->bind_param($bind_types, ...$bind_values);
    $statement->execute();
    $total_users = (int) $statement->get_result()->fetch_assoc()['COUNT(*)'];
    $statement->close();
} else {
    $total_users = db_count($conn, "SELECT COUNT(*) FROM users" . $where_sql);
}

$per_page = 25;
$current_page = page_number();
$pagination = paginate($total_users, $per_page, $current_page);

$statement = $conn->prepare(
    "SELECT * FROM users" . $where_sql . " ORDER BY role ASC, full_name ASC LIMIT ? OFFSET ?"
);
$offset = $pagination['offset'];
$statement->bind_param($bind_types . 'ii', ...array_merge($bind_values, [$per_page, $offset]));
$statement->execute();
$result = $statement->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$statement->close();

$me = current_user();

$page_title = 'Users';
$page_heading = 'Users';
$page_actions = '<a class="btn btn-primary" href="' . admin_url('users/form.php') . '">'
    . '<i class="fa-solid fa-user-plus me-1" aria-hidden="true"></i>Add a person</a>';

require_once __DIR__ . '/../../app/includes/admin/header.php';
?>

<form class="row g-2 align-items-center mb-3" method="get" action="<?php echo admin_url('users'); ?>" role="search">
    <div class="col-12 col-md">
        <label class="visually-hidden" for="filter-search">Search</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
            <input type="search" class="form-control" id="filter-search" name="q"
                   value="<?php echo e($search); ?>" placeholder="Search by name, username or email" maxlength="100">
        </div>
    </div>
    <div class="col-12 col-md-auto">
        <label class="visually-hidden" for="filter-role">Role</label>
        <?php /* The four names come from user_role_label() so that the filter, the
               badge on each row and the sidebar all call the same account by the
               same words. They are in the order of what can reach the most. */ ?>
        <select class="form-select" id="filter-role" name="role" data-auto-submit>
            <option value="">Every role</option>
            <?php foreach (['admin', 'church_admin', 'editor', 'member'] as $role_name): ?>
                <option value="<?php echo e($role_name); ?>"
                    <?php echo $role_filter === $role_name ? 'selected' : '' ?>>
                    <?php echo e(user_role_label($role_name)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12 col-md-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($search !== '' || $role_filter !== ''): ?>
            <a class="btn btn-outline-secondary" href="<?php echo admin_url('users'); ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php
$filter_query = ['q' => $search];
if ($role_filter !== '') {
    $filter_query['role'] = $role_filter;
}
admin_result_bar($pagination, $filter_query, 'accounts');
?>

<?php if (!$users): ?>
    <?php
    empty_state(
        'fa-user-group',
        $search !== '' || $role_filter !== '' ? 'Nobody matches that' : 'Nobody yet',
        $search !== '' || $role_filter !== ''
            ? 'Try a different word, or clear the filter to see everyone.'
            : 'Add one person at a time. An editor can change all the content but cannot reach the '
                . 'users, the settings or the newsletter.',
        '<a class="btn btn-primary" href="' . admin_url('users/form.php') . '">'
        . '<i class="fa-solid fa-user-plus me-1" aria-hidden="true"></i>Add a person</a>',
        'admin'
    );
    ?>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="ps-3">Person</th>
                        <th scope="col">Username</th>
                        <th scope="col">Role</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Last signed in</th>
                        <th scope="col" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php $is_me = (int) $user['id'] === (int) $me['id']; ?>
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-circle text-bg-brand d-inline-flex align-items-center justify-content-center"
                                          style="width:40px;height:40px">
                                        <?php echo initials($user['full_name']); ?>
                                    </span>
                                    <div>
                                        <a class="fw-semibold d-block text-decoration-none"
                                           href="<?php echo admin_url('users/form.php?id=' . (int) $user['id']); ?>">
                                            <?php echo e($user['full_name']); ?>
                                            <?php if ($is_me): ?>
                                                <span class="badge rounded-pill text-bg-secondary">You</span>
                                            <?php endif; ?>
                                        </a>
                                        <span class="small text-body-secondary">
                                            <?php echo e($user['email']); ?>
                                            <?php if ((int) $user['status'] === 0): ?>
                                                <span class="badge rounded-pill text-bg-danger">Switched off</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="small">
                                <?php if ($user['role'] === 'member'): ?>
                                    <?php /* A member signs in with their email address, so
                                           their username is the same string and printing
                                           it here would just repeat the line above. */ ?>
                                    <span class="text-body-secondary">Email sign in</span>
                                <?php else: ?>
                                    <code><?php echo e($user['username']); ?></code>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                /* One place that turns a role into a badge, so adding
                                   a fifth role later is a change here and nowhere
                                   else. The wording itself comes from
                                   user_role_label() so the sidebar, this list and
                                   the form cannot drift apart. */
                                $role_badges = [
                                    'admin'        => ['text-bg-brand'],
                                    'church_admin' => ['text-bg-warning'],
                                    'editor'       => ['text-bg-secondary'],
                                    'member'       => ['text-bg-info'],
                                ];
                                $badge = $role_badges[$user['role']] ?? ['text-bg-danger'];
                                ?>
                                <span class="badge rounded-pill <?php echo e($badge[0]); ?>">
                                    <?php echo e(user_role_label($user['role'])); ?>
                                </span>
                                <?php if ($user['role'] === 'church_admin' && (int) $user['can_manage_users'] === 1): ?>
                                    <?php /* The extra box, said plainly on the list, because
                                           "Church admin" alone does not tell you whether
                                           this one can also open the users. */ ?>
                                    <span class="badge rounded-pill text-bg-danger"
                                          title="Can also open the users and the settings">+ settings</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-body-secondary text-nowrap">
                                <?php echo $user['phone'] !== '' ? e($user['phone']) : 'Not given'; ?>
                            </td>
                            <td class="small text-body-secondary text-nowrap">
                                <?php echo $user['login'] === null ? 'Never' : e(ago($user['login'])); ?>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <?php
                                $user_actions = admin_edit_button('users', $user['id']);
                                /* Switching yourself off would lock you out
                                   mid-session, so those two buttons are left out
                                   for your own row. */
                                if (!$is_me) {
                                    $user_actions .= admin_toggle_button('users', $user['id'], $user['status'], 'Let in', 'Switch off');
                                    $user_actions .= admin_delete_button('users', $user['id'], 'the account for ' . $user['full_name']);
                                }
                                echo admin_action_group($user_actions);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../app/includes/admin/footer.php'; ?>
