<?php
namespace App\Models;

class Usuario
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allEjecutivos(): array
    {
        $stmt = db()->query("SELECT * FROM usuarios WHERE rol = 'ejecutivo' ORDER BY nombre");
        return $stmt->fetchAll();
    }

    public static function create(string $nombre, string $email, string $password, string $rol): int
    {
        $stmt = db()->prepare(
            'INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_BCRYPT), $rol]);
        return (int) db()->lastInsertId();
    }

    public static function setActivo(int $id, bool $activo): void
    {
        $stmt = db()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?');
        $stmt->execute([$activo ? 1 : 0, $id]);
    }

    public static function actualizar(int $id, string $nombre, string $email): void
    {
        $stmt = db()->prepare('UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?');
        $stmt->execute([$nombre, $email, $id]);
    }
}
