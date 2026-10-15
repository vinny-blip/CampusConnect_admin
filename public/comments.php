<?php
require dirname(__DIR__) . '/app/Controllers/PageController.php';
(new PageController('comments'))->run();
