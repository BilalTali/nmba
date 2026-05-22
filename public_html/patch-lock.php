<?php
$content = file_get_contents('/Users/computergallery/Desktop/nmbaagent/public_html/nmba-cron.php');
$content = str_replace(
    'touch($lockFile);',
    'touch($lockFile);' . "\n" . 'register_shutdown_function(function() use ($lockFile) { @unlink($lockFile); });',
    $content
);
$content = str_replace('    @unlink($lockFile);', '    // @unlink($lockFile); // Handled by shutdown function', $content);
file_put_contents('/Users/computergallery/Desktop/nmbaagent/public_html/nmba-cron.php', $content);
echo "Patched\n";
