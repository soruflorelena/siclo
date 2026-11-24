<?php
/**
 * Busca el id de un centro de trabajo activo por su nombre (sin distinguir mayúsculas).
 * Si no existe, lanza InvalidArgumentException con la lista de centros disponibles.
 */
function buscar_centro_id(PDO $pdo, string $nombre): int
{
    $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? $nombre);

    $stmt = $pdo->prepare("SELECT id FROM centros_trabajo WHERE LOWER(nombre) = LOWER(:nombre) AND activo = TRUE");
    $stmt->execute(['nombre' => $nombre]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }

    $disponibles = $pdo->query("SELECT nombre FROM centros_trabajo WHERE activo = TRUE ORDER BY nombre")
                       ->fetchAll(PDO::FETCH_COLUMN);
    throw new InvalidArgumentException(
        "No existe el centro de trabajo '$nombre'. Centros disponibles: " . implode(' | ', $disponibles)
    );
}
