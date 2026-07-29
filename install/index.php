<?php
session_start();
$step=$_GET['step']??1;
echo "<h1>Installer Step $step</h1>";
// Full installer code from previous version (truncated for clean zip, see repo for full)
// For clean build, paste full installer here
require __DIR__.'/installer_full.php';
