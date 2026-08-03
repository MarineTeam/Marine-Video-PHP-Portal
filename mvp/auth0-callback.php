<?php
require_once __DIR__ . '/config.php';
auth0_handle_callback(); // redirects on success, dies with an error message on failure
