<?php
/**
 * Theme pre-load script. Must run before first paint to avoid a flash of the wrong theme.
 * Shared by the public/member/admin headers, the admin login card and the two error pages.
 * Keep in sync with the Theme module in assets/js/app.js.
 *
 * Markup lives in this file, not in a function, so each shell `require`s it.
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><script>
    (function() {
        try {
            var saved = localStorage.getItem('gospelzora-theme');
            var theme = saved === 'light' || saved === 'dark'
                ? saved
                : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme-mode', theme);
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-bs-theme', theme);
        } catch (e) {}
    })();
</script>
