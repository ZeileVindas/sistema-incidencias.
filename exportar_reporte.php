<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$rol_sesion = strtolower(trim((string) ($_SESSION['rol'] ?? '')));
$es_personal_soporte = in_array($rol_sesion, ['tecnico', 'técnico', 'administrador', 'admin'], true);

if (!$es_personal_soporte) {
    header("Location: index.php");
    exit();
}

require_once 'config/conexion.php';

$formato = strtolower(trim((string) ($_GET['formato'] ?? 'csv')));
if (!in_array($formato, ['csv', 'pdf'], true)) {
    $formato = 'csv';
}

$tiene_columna_id_tecnico = false;
try {
    $stmt_col = $conexion->query("SHOW COLUMNS FROM incidencias LIKE 'id_tecnico'");
    $tiene_columna_id_tecnico = ($stmt_col && $stmt_col->rowCount() > 0);
} catch (PDOException $e) {
    $tiene_columna_id_tecnico = false;
}

$fecha_desde = trim((string) ($_GET['fecha_desde'] ?? ''));
$fecha_hasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
$id_categoria_sel = filter_var($_GET['id_categoria'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$prioridad_sel = trim((string) ($_GET['prioridad'] ?? ''));
$estado_sel = trim((string) ($_GET['estado'] ?? ''));
$id_tecnico_sel = filter_var($_GET['id_tecnico'] ?? null, FILTER_VALIDATE_INT) ?: 0;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    $fecha_desde = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $fecha_hasta = '';
}

$ids_filtro_tecnico = [];
if ($tiene_columna_id_tecnico && $id_tecnico_sel > 0) {
    try {
        $stmt_t = $conexion->prepare("
            SELECT id_usuario, nombre
            FROM usuarios
            WHERE LOWER(TRIM(rol)) IN ('tecnico', 'técnico', 'administrador', 'admin')
        ");
        $stmt_t->execute();
        $todos_tec = $stmt_t->fetchAll(PDO::FETCH_ASSOC);

        $nombre_objetivo = null;
        foreach ($todos_tec as $t) {
            if ((int) $t['id_usuario'] === $id_tecnico_sel) {
                $nombre_objetivo = mb_strtolower(trim((string) $t['nombre']), 'UTF-8');
                break;
            }
        }

        if ($nombre_objetivo !== null && $nombre_objetivo !== '') {
            foreach ($todos_tec as $t) {
                if (mb_strtolower(trim((string) $t['nombre']), 'UTF-8') === $nombre_objetivo) {
                    $ids_filtro_tecnico[] = (int) $t['id_usuario'];
                }
            }
        }
    } catch (PDOException $e) {
        // Fallback abajo
    }
    if (empty($ids_filtro_tecnico)) {
        $ids_filtro_tecnico = [$id_tecnico_sel];
    }
}

$where = [];
$params = [];

if ($fecha_desde !== '') {
    $where[] = "DATE(i.fecha_creacion) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}
if ($fecha_hasta !== '') {
    $where[] = "DATE(i.fecha_creacion) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}
if ($id_categoria_sel > 0) {
    $where[] = "i.id_categoria = :id_categoria";
    $params[':id_categoria'] = $id_categoria_sel;
}
if ($prioridad_sel !== '') {
    $where[] = "LOWER(TRIM(i.prioridad)) = LOWER(:prioridad)";
    $params[':prioridad'] = $prioridad_sel;
}
if ($estado_sel !== '') {
    $where[] = "LOWER(TRIM(i.estado)) = LOWER(:estado)";
    $params[':estado'] = $estado_sel;
}
if ($tiene_columna_id_tecnico && !empty($ids_filtro_tecnico)) {
    $placeholders = [];
    foreach ($ids_filtro_tecnico as $idx => $tid) {
        $ph = ":id_tec_" . $idx;
        $placeholders[] = $ph;
        $params[$ph] = $tid;
    }
    $where[] = "i.id_tecnico IN (" . implode(", ", $placeholders) . ")";
}

$sql = "SELECT i.id_incidencia, i.titulo, i.prioridad, i.estado, i.fecha_creacion,
               u.nombre AS solicitante_nombre, c.nombre AS categoria_nombre";
if ($tiene_columna_id_tecnico) {
    $sql .= ", COALESCE(CASE WHEN LOWER(TRIM(COALESCE(t.rol, ''))) IN ('tecnico', 'técnico', 'administrador', 'admin') THEN t.nombre ELSE NULL END, 'Sin asignar') AS tecnico_asignado_nombre";
} else {
    $sql .= ", 'N/A' AS tecnico_asignado_nombre";
}
$sql .= " FROM incidencias i
          JOIN usuarios u ON i.id_usuario = u.id_usuario
          JOIN categorias c ON i.id_categoria = c.id_categoria";
if ($tiene_columna_id_tecnico) {
    $sql .= " LEFT JOIN usuarios t ON i.id_tecnico = t.id_usuario";
}
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY i.id_incidencia DESC";

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($formato === 'csv') {
    $filename = "reporte_incidencias_" . date("Y-m-d_His") . ".csv";
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $out = fopen("php://output", "w");
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        "ID",
        "Titulo",
        "Solicitante",
        "Categoria",
        "Prioridad",
        "Estado",
        "Tecnico Asignado",
        "Fecha Creacion"
    ]);

    foreach ($filas as $row) {
        fputcsv($out, [
            $row['id_incidencia'],
            $row['titulo'],
            $row['solicitante_nombre'],
            $row['categoria_nombre'],
            $row['prioridad'],
            $row['estado'],
            $row['tecnico_asignado_nombre'],
            $row['fecha_creacion'],
        ]);
    }

    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Incidencias (PDF)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 32px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #162033;
            background-color: #F4F1EB;
        }
        .report-sheet {
            background: #FFFFFF;
            border: 1px solid #D9CDBB;
            border-top: 5px solid #B08D57;
            border-radius: 16px;
            padding: 28px 32px;
            max-width: 1140px;
            margin: 0 auto;
        }
        h4 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.9rem;
            font-weight: 700;
            color: #162033;
        }
        .table thead th {
            background-color: #FAF8F5;
            color: #1F2A44;
            border-color: #D9CDBB;
            font-size: 0.78rem;
            text-transform: uppercase;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; background: #FFFFFF; }
            .report-sheet { border: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="report-sheet">
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <h4 class="mb-0">Reporte de Incidencias</h4>
            <div class="d-flex gap-2">
                <button type="button" onclick="window.print()" class="btn btn-dark btn-sm px-3">Imprimir / Guardar como PDF</button>
                <button type="button" onclick="window.close()" class="btn btn-outline-secondary btn-sm px-3">Cerrar</button>
            </div>
        </div>

        <p class="text-muted small mb-4">
            Generado el <?php echo date("Y-m-d H:i:s"); ?> • Total de registros: <strong><?php echo count($filas); ?></strong>
        </p>

        <table class="table table-bordered table-sm align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Título</th>
                    <th>Solicitante</th>
                    <th>Categoría</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Técnico</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($filas) > 0) { ?>
                    <?php foreach ($filas as $row) { ?>
                        <tr>
                            <td>#<?php echo (int) $row['id_incidencia']; ?></td>
                            <td class="fw-semibold"><?php echo htmlspecialchars((string) $row['titulo']); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['solicitante_nombre']); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['categoria_nombre']); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['prioridad']); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['estado']); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['tecnico_asignado_nombre']); ?></td>
                            <td><?php echo htmlspecialchars((string) $row['fecha_creacion']); ?></td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">Sin registros para los filtros seleccionados.</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>
</html>