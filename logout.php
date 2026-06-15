<?php

session_start();
session_unset();
session_destroy();

header('Location: home-01.php');
 exit();

?> 