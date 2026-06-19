<?php
require_once __DIR__ . '/../includes/auth.php';

if (currentUserId() === null) {
    header('Location: /login.php');
} elseif (currentUserRol() === 'admin') {
    header('Location: /admin/dashboard.php');
} else {
    header('Location: /dashboard.php');
}
exit;
