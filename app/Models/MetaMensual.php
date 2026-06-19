<?php
namespace App\Models;

class MetaMensual
{
    public static function forEjecutivoMes(int $ejecutivoId, int $anio, int $mes): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM metas_mensuales WHERE ejecutivo_id = ? AND anio = ? AND mes = ? LIMIT 1'
        );
        $stmt->execute([$ejecutivoId, $anio, $mes]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function upsert(int $ejecutivoId, int $anio, int $mes, float $montoMeta): void
    {
        $stmt = db()->prepare(
            'INSERT INTO metas_mensuales (ejecutivo_id, anio, mes, monto_meta)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE monto_meta = VALUES(monto_meta)'
        );
        $stmt->execute([$ejecutivoId, $anio, $mes, $montoMeta]);
    }
}
