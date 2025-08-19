<?php
session_start();
session_unset();
session_destroy();
// DO NOT clear nemi_device cookie here if you want PIN next time
header('Location: /auth/login.html');
exit;
