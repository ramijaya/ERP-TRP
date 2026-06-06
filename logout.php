<?php
session_start();
session_destroy();
header('Location: /ERP-TRP/login.php');
exit;
