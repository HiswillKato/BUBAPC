<?php
/**
 * BUBAPC - The login form.
 *
 * login.php fills in $login_errors (anything that went wrong) and then includes
 * this file. The wording lives here so there is only one copy of it.
 *
 * $login_next is the page the visitor was refused and is sent back to after a
 * successful sign in, so it has to survive the trip. It is read from the query
 * string on the first visit and from the posted form on a second one, because
 * a failed attempt comes straight back to this page as a POST.
 */

$login_errors = $login_errors ?? [];
$login_next = $login_next ?? ($_POST['next'] ?? ($_GET['next'] ?? ''));
?>
<div class="card border-0 shadow rounded-4">
    <div class="card-body p-4 p-md-5">

        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="brand-mark bg-brand-soft text-brand rounded-3 d-inline-flex align-items-center justify-content-center flex-shrink-0"
                  aria-hidden="true">
                <svg viewBox="0 0 48 48" focusable="false">
                    <path d="M24 8c2.4 3.6.6 6-1.2 7.8-1.8 1.8-3.6 3-3.6 6a4.8 4.8 0 0 0 9.6 0c0-1.8-.6-3-1.2-4.2 3.6 1.8 6 4.8 6 9A9.6 9.6 0 0 1 24 36a9.6 9.6 0 0 1-9.6-9.6c0-6.6 4.8-11.4 9.6-18.4z"
                          fill="currentColor"/>
                </svg>
            </span>
            <div>
                <p class="h5 mb-0"><?php echo e(SITE_NAME); ?></p>
                <p class="small text-body-secondary mb-0"><?php echo e(SITE_TAGLINE); ?></p>
            </div>
        </div>

        <h1 class="h4 mb-3">Sign in</h1>

        <?php if ($login_errors): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($login_errors as $login_error): ?>
                    <p class="mb-0"><?php echo e($login_error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php show_flashes(); ?>

        <form method="post" action="<?php echo url('login'); ?>" id="login-form" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="next" value="<?php echo e($login_next); ?>">

            <div class="mb-3">
                <label class="form-label" for="username">Username or email</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?php echo e($login_username ?? ''); ?>"
                       required maxlength="190" autocomplete="username"
                       autocapitalize="none" spellcheck="false" autofocus>
            </div>

            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control border-end-0" id="password" name="password"
                           required autocomplete="current-password">
                    <button type="button" class="btn btn-outline-secondary px-3" data-toggle-password="#password"
                            aria-label="Show the password">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="fa-solid fa-right-to-bracket me-1" aria-hidden="true"></i>
                Sign in
            </button>
        </form>

        <hr class="my-4">

        <p class="small text-body-secondary mb-0">
            <i class="fa-solid fa-key me-1" aria-hidden="true"></i>
            <a href="<?php echo url('forgot'); ?>">Forgot your password?</a>
        </p>
    </div>
</div>
