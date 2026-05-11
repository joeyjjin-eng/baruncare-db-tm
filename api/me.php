<?php
require __DIR__ . '/_bootstrap.php';
require_method('GET');
$me = require_login();
ok(['user' => $me]);
