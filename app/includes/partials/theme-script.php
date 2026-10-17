<?php
/**
 * Theme pre-load script. Must run before first paint.
 */
if (!defined('APP_INITIALIZED')) { http_response_code(404); exit; } ?><script>
  (function(){
    try {
      var saved = localStorage.getItem('bubapc-theme');
      var theme = saved === 'light' || saved === 'dark' ? saved : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', theme);
      document.documentElement.setAttribute('data-bs-theme', theme);
    } catch(e){}
  })();
</script>
