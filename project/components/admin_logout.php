<?php

include 'connect.php';
include 'security_helpers.php';

session_start();
blog_forget_remember_login($conn);
session_unset();
session_destroy();

header('location:../static/login.php');
