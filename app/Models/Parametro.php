<?php
namespace App\Models;

class Parametro
{
    public static function allAsAssoc(): array
    {
        $stmt = db()->query('SELECT clave, valor FROM parametros');
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['clave']] = $row['valor'];
        }
        return $result;
    }

    public static function set(string $clave, string $valor): void
    {
        $stmt = db()->prepare('UPDATE parametros SET valor = ? WHERE clave = ?');
        $stmt->execute([$valor, $clave]);
    }
}
