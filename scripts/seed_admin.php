<?php
require_once __DIR__ . '/../includes/config.php';

use App\Models\Usuario;

$email = 'admin@compulago.com';
$existing = Usuario::findByEmail($email);
if ($existing !== null) {
    echo "El admin ya existe ({$email}).\n";
    exit;
}

Usuario::create('Administrador', $email, 'Cambiar123!', 'admin');
echo "Admin creado: {$email} / Cambiar123! (cámbiala después de iniciar sesión).\n";
