<?php
// Load shared application services and route this request to its page controller.
require dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Router.php';

Router::dispatch($_GET['_cc_route'] ?? basename($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
