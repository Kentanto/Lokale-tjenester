<?php
/**
 * staging/admin_stub.php
 * A minimal admin placeholder showing an isolated area to prototype admin features.
 * This file is intentionally non-destructive and only provides a UI skeleton.
 */

require_once __DIR__ . '/../display.php';

?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin (Staging)</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;padding:18px} .card{background:#fff;padding:12px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);max-width:900px}</style>
</head>
<body>
    <div class="card">
        <h1>Admin (Staging)</h1>
        <p>This is a safe staging admin area. Use these links to test non-destructive features:</p>
        <ul>
            <li><a href="feed.php">Jobs feed (JSON)</a></li>
            <li><a href="mailer_stub.php?simulate=1">Mailer stub (simulate)</a></li>
        </ul>
        <p>New admin features should be prototyped here before being integrated into the
           main admin panel.</p>
    </div>
</body>
</html>
