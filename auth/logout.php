<?php

session_start();

$_SESSION = array();
session_unset();

session_destroy();
header('Location: ../projekt1/index.php');
exit;