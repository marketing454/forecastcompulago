<?php
namespace App\Models;

class Oportunidad
{
    public static function create(int $ejecutivoId, string $cuenta, string $nit, string $tipo, string $fechaCreacion, float $monto, string $estado): int
    {
        $stmt = db()->prepare(
            'INSERT INTO oportunidades (ejecutivo_id, cuenta, nit, tipo, fecha_creacion, monto, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$ejecutivoId, $cuenta, $nit, $tipo, $fechaCreacion, $monto, $estado]);
        return (int) db()->lastInsertId();
    }

    public static function activasByEjecutivo(int $ejecutivoId): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM oportunidades WHERE ejecutivo_id = ? AND activa = 1 ORDER BY fecha_creacion DESC'
        );
        $stmt->execute([$ejecutivoId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM oportunidades WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function update(int $id, string $cuenta, string $nit, string $tipo, float $monto, string $estado, int $ejecutivoId): void
    {
        $stmt = db()->prepare(
            'UPDATE oportunidades SET cuenta = ?, nit = ?, tipo = ?, monto = ?, estado = ? WHERE id = ? AND ejecutivo_id = ?'
        );
        $stmt->execute([$cuenta, $nit, $tipo, $monto, $estado, $id, $ejecutivoId]);
    }

    public static function setActiva(int $id, bool $activa, int $ejecutivoId): void
    {
        $stmt = db()->prepare('UPDATE oportunidades SET activa = ? WHERE id = ? AND ejecutivo_id = ?');
        $stmt->execute([$activa ? 1 : 0, $id, $ejecutivoId]);
    }
}
