<?php
// tests/browser.php
// Runs the same automated tests and shows the result in the browser.
// Open:  http://localhost/todolist/tests/browser.php

define('RUN_FROM_BROWSER', true);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>ClariTask tests</title></head>';
echo '<body style="font-family:Consolas,monospace; padding:20px;">';
echo '<h2>ClariTask automated tests</h2><pre style="font-size:14px; line-height:1.6;">';

ob_start();
require __DIR__ . '/run_tests.php';
$out = htmlspecialchars(ob_get_clean());

// Colour the results: PASS green, FAIL red.
$out = str_replace('  PASS  ', '  <span style="color:#0a7d33; font-weight:bold;">PASS</span>  ', $out);
$out = str_replace('  FAIL  ', '  <span style="color:#c00000; font-weight:bold;">FAIL</span>  ', $out);

echo $out;
echo '</pre></body></html>';