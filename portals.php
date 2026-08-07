<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/config.php';
if(empty($_SESSION['user'])){header('Location:login.php');exit;}
header('Location:index.php');exit;
