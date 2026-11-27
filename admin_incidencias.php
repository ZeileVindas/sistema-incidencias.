
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

$nombre_usuario = $_SESSION['nombre'] ?? 'Soporte';
$etiqueta_rol = in_array($rol_sesion, ['administrador', 'admin'], true) ? 'Administrador' : 'Técnico';
$error = '';

// Detectar si existe la columna id_tecnico en incidencias
$tiene_columna_id_tecnico = false;
try {
    $stmt_col = $conexion->query("SHOW COLUMNS FROM incidencias LIKE 'id_tecnico'");
    $tiene_columna_id_tecnico = ($stmt_col && $stmt_col->rowCount() > 0);
} catch (PDOException $e) {
    $tiene_columna_id_tecnico = false;
}

// Leer filtros GET
$fecha_desde = trim((string) ($_GET['fecha_desde'] ?? ''));
$fecha_hasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
$id_categoria_sel = filter_var($_GET['id_categoria'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$prioridad_sel = trim((string) ($_GET['prioridad'] ?? ''));
$estado_sel = trim((string) ($_GET['estado'] ?? ''));
$id_tecnico_sel = filter_var($_GET['id_tecnico'] ?? null, FILTER_VALIDATE_INT) ?: 0;

$estados_validos = ['Abierto', 'En Proceso', 'Resuelto', 'Cerrado'];
$prioridades_validas = ['Baja', 'Media', 'Alta', 'Crítica', 'Critica'];

if (!in_array($estado_sel, $estados_validos, true)) {
    $estado_sel = '';
}
if ($prioridad_sel !== '' && !in_array($prioridad_sel, $prioridades_validas, true)) {
    $prioridad_sel = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    $fecha_desde = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $fecha_hasta = '';
}

// Cargar categorías y técnicos sin vacíos ni duplicados visuales
$categorias = [];
$tecnicos = [];
$ids_categoria_por_nombre = [];
$ids_tecnico_por_nombre = [];

try {
    $stmt_cat = $conexion->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre ASC, id_categoria ASC");
    $categorias_raw = $stmt_cat ? $stmt_cat->fetchAll(PDO::FETCH_ASSOC) : [];

    $vistos_cat = [];
    foreach ($categorias_raw as $cat) {
        $nombre_cat_limpio = trim((string) ($cat['nombre'] ?? ''));
        if ($nombre_cat_limpio === '') {
            continue;
        }
        $clave_cat = mb_strtolower($nombre_cat_limpio, 'UTF-8');
        $cat['nombre'] = $nombre_cat_limpio;
        if (!isset($vistos_cat[$clave_cat])) {
            $vistos_cat[$clave_cat] = (int) $cat['id_categoria'];
            $categorias[] = $cat;
        }
        $ids_categoria_por_nombre[$clave_cat][] = (int) $cat['id_categoria'];
    }

    if ($tiene_columna_id_tecnico) {
        $stmt_tec = $conexion->query("
            SELECT id_usuario, nombre, rol
            FROM usuarios
            WHERE LOWER(TRIM(rol)) NOT IN ('solicitante', 'usuario', 'cliente')
            ORDER BY nombre ASC, id_usuario ASC
        ");
        $tecnicos_raw = $stmt_tec ? $stmt_tec->fetchAll(PDO::FETCH_ASSOC) : [];

        $vistos = [];
        foreach ($tecnicos_raw as $tec) {
            $nombre_tec_limpio = trim((string) ($tec['nombre'] ?? ''));
            $clave_nombre = mb_strtolower($nombre_tec_limpio, 'UTF-8');
            if ($clave_nombre === '') {
                continue;
            }
            $tec['nombre'] = $nombre_tec_limpio;
            if (!isset($vistos[$clave_nombre])) {
                $vistos[$clave_nombre] = (int) $tec['id_usuario'];
                $tecnicos[] = $tec;
            }
            $ids_tecnico_por_nombre[$clave_nombre][] = (int) $tec['id_usuario'];
        }
    }
} catch (PDOException $e) {
    error_log("Error al cargar catálogos en admin_incidencias.php: " . $e->getMessage());
}

$ids_filtro_categoria = [];
if ($id_categoria_sel > 0) {
    foreach ($ids_categoria_por_nombre as $lista_ids) {
        if (in_array($id_categoria_sel, $lista_ids, true)) {
            $ids_filtro_categoria = $lista_ids;
            break;
        }
    }
    if (empty($ids_filtro_categoria)) {
        $ids_filtro_categoria = [$id_categoria_sel];
    }
}

$ids_filtro_tecnico = [];
if ($tiene_columna_id_tecnico && $id_tecnico_sel > 0) {
    foreach ($ids_tecnico_por_nombre as $lista_ids) {
        if (in_array($id_tecnico_sel, $lista_ids, true)) {
            $ids_filtro_tecnico = $lista_ids;
            break;
        }
    }
    if (empty($ids_filtro_tecnico)) {
        $ids_filtro_tecnico = [$id_tecnico_sel];
    }
}

// Construir consulta filtrada con PDO
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
if (!empty($ids_filtro_categoria)) {
    $placeholders_cat = [];
    foreach ($ids_filtro_categoria as $idx => $cid) {
        $ph = ":id_cat_" . $idx;
        $placeholders_cat[] = $ph;
        $params[$ph] = $cid;
    }
    $where[] = "i.id_categoria IN (" . implode(", ", $placeholders_cat) . ")";
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

$sql = "SELECT i.*, u.nombre AS solicitante_nombre, c.nombre AS categoria_nombre";
if ($tiene_columna_id_tecnico) {
    $sql .= ", t.nombre AS tecnico_asignado_nombre";
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

$incidencias = [];
try {
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al consultar incidencias en admin_incidencias.php: " . $e->getMessage());
    $error = "Ocurrió un error al filtrar las incidencias.";
}

// Query string para conservar filtros en CSV y PDF
$query_filtros = http_build_query(array_filter([
    'fecha_desde'  => $fecha_desde,
    'fecha_hasta'  => $fecha_hasta,
    'id_categoria' => $id_categoria_sel > 0 ? $id_categoria_sel : '',
    'prioridad'    => $prioridad_sel,
    'estado'       => $estado_sel,
    'id_tecnico'   => ($tiene_columna_id_tecnico && $id_tecnico_sel > 0) ? $id_tecnico_sel : '',
], fn($v) => $v !== ''));

$url_csv = "exportar_reporte.php?formato=csv" . ($query_filtros !== '' ? "&" . $query_filtros : "");
$url_pdf = "exportar_reporte.php?formato=pdf" . ($query_filtros !== '' ? "&" . $query_filtros : "");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cola de Incidencias y Reportes | Sistema TI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --midnight-navy: #162033;
            --deep-navy: #1F2A44;
            --warm-beige: #D9CDBB;
            --ivory: #F4F1EB;
            --brushed-brass: #B08D57;
            --brass-light: #C8A670;
        }
        * { writing-mode: horizontal-tb !important; text-orientation: mixed !important; }
        body {
            background-color: var(--ivory);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--midnight-navy);
            background-image:
                radial-gradient(circle at 8% 12%, rgba(217, 205, 187, 0.45) 0%, transparent 42%),
                radial-gradient(circle at 92% 88%, rgba(176, 141, 87, 0.14) 0%, transparent 42%);
        }
        .navbar-luxury {
            background-color: var(--midnight-navy);
            border-bottom: 1px solid rgba(176, 141, 87, 0.35);
            padding: 16px 30px;
            box-shadow: 0 4px 20px rgba(22, 32, 51, 0.16);
        }
        .navbar-brand-custom {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.55rem;
            font-weight: 700;
            color: #FFFFFF !important;
            text-decoration: none;
        }
        .navbar-brand-custom span { color: var(--brushed-brass); }
        .user-badge {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(217, 205, 187, 0.28);
            padding: 8px 16px;
            border-radius: 50px;
            color: var(--ivory);
            font-size: 0.85rem;
            white-space: nowrap;
        }
        .user-badge strong { color: var(--brass-light); }
        .btn-nav-action {
            background: transparent;
            border: 1px solid var(--warm-beige);
            color: var(--ivory);
            padding: 7px 16px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-nav-action:hover { background-color: var(--ivory); color: var(--midnight-navy); }
        .btn-logout { border-color: rgba(248, 113, 113, 0.45); color: #F87171; }
        .btn-logout:hover { background-color: #F87171; color: #FFFFFF; border-color: #F87171; }

        .main-container { flex: 1; max-width: 1360px; width: 100%; margin: 0 auto; padding: 36px 20px; }
        .card-panel {
            background: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(22, 32, 51, 0.15);
            border: 1px solid rgba(217, 205, 187, 0.7);
            padding: 36px 40px;
            position: relative;
        }
        .card-panel::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; height: 6px;
            border-radius: 24px 24px 0 0;
            background: linear-gradient(90deg, var(--midnight-navy), var(--deep-navy), var(--brushed-brass));
        }
        .panel-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--midnight-navy);
            margin-bottom: 4px;
        }
        .panel-subtitle { color: #6B7280; font-size: 0.92rem; margin-bottom: 0; }

        .btn-export-brass {
            background: linear-gradient(135deg, var(--brushed-brass) 0%, #967441 100%);
            color: #FFFFFF;
            border: none;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .btn-export-brass:hover { color: #FFFFFF; filter: brightness(1.05); }

        .btn-export-outline {
            background-color: #FAF8F5;
            color: var(--deep-navy);
            border: 1.5px solid var(--warm-beige);
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .btn-export-outline:hover { border-color: var(--brushed-brass); background-color: #FFFFFF; color: var(--midnight-navy); }

        .filter-box {
            background-color: #FAF8F5;
            border: 1.5px solid var(--warm-beige);
            border-radius: 18px;
            padding: 20px 24px;
            margin-bottom: 22px;
        }
        .filter-caption {
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--brushed-brass);
            margin-bottom: 12px;
        }
        .form-label-luxury {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--deep-navy);
            margin-bottom: 5px;
            display: block;
        }
        .form-control-luxury, .form-select-luxury {
            width: 100%;
            padding: 9px 12px;
            font-size: 0.88rem;
            color: var(--midnight-navy);
            background-color: #FFFFFF;
            border: 1.5px solid var(--warm-beige);
            border-radius: 10px;
        }
        .btn-luxury {
            padding: 9px 20px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--midnight-navy) 100%);
            color: var(--ivory);
            font-size: 0.86rem;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid var(--brushed-brass);
            white-space: nowrap;
        }
        .btn-clear-luxury {
            padding: 9px 16px;
            border-radius: 10px;
            background-color: #FFFFFF;
            color: var(--deep-navy);
            border: 1.5px solid var(--warm-beige);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }

        .summary-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #FAF8F5;
            border: 1px solid var(--warm-beige);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.82rem;
            color: var(--deep-navy);
            margin-bottom: 16px;
        }
        .summary-pill strong { color: var(--brushed-brass); }

        .table-luxury { width: 100%; min-width: 920px; }
        .table-luxury th {
            background-color: #FAF8F5;
            color: var(--deep-navy);
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 13px 12px;
            border-bottom: 1.5px solid var(--warm-beige);
            white-space: nowrap;
        }
        .table-luxury td {
            padding: 13px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #EFECE6;
            font-size: 0.88rem;
        }
        .nowrap-col { white-space: nowrap; }

        .badge-status {
            padding: 5px 11px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        .st-abierto { background-color: #FAF8F5; color: var(--deep-navy); border: 1px solid var(--brushed-brass); }
        .st-proceso { background-color: #1F2A44; color: var(--brass-light); border: 1px solid var(--deep-navy); }
        .st-resuelto { background-color: #EFECE6; color: var(--midnight-navy); border: 1px solid var(--warm-beige); }
        .st-cerrado { background-color: #162033; color: var(--warm-beige); border: 1px solid #162033; }

        .badge-prio {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            background-color: #FAF8F5;
            color: var(--midnight-navy);
            border: 1px solid var(--warm-beige);
            white-space: nowrap;
            display: inline-block;
        }
        .prio-alta, .prio-critica, .prio-crítica {
            border-color: var(--brushed-brass);
            color: #7C5824;
            background-color: rgba(176, 141, 87, 0.12);
        }

        .btn-manage {
            padding: 6px 14px;
            border-radius: 9px;
            border: 1.5px solid var(--warm-beige);
            background-color: #FAF8F5;
            color: var(--midnight-navy);
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            display: inline-block;
        }
        .btn-manage:hover {
            background-color: var(--midnight-navy);
            color: var(--ivory);
            border-color: var(--midnight-navy);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-luxury">
        <div class="container-fluid px-4">
            <a href="index.php" class="navbar-brand-custom">Sistema de <span>Incidencias</span></a>
            <div class="d-flex align-items-center gap-3">
                <div class="user-badge"><span><?php echo htmlspecialchars($etiqueta_rol); ?>:</span> <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></div>
                <a href="index.php" class="btn-nav-action">Panel Principal</a>
                <a href="logout.php" class="btn-nav-action btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="card-panel">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <h1 class="panel-title">Cola General de Incidencias y Reportes</h1>
                    <p class="panel-subtitle">Filtre registros y exporte reportes en CSV o PDF con los criterios activos.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?php echo htmlspecialchars($url_csv); ?>" class="btn-export-brass">Exportar CSV</a>
                    <a href="<?php echo htmlspecialchars($url_pdf); ?>" target="_blank" class="btn-export-outline">Vista PDF / Imprimir</a>
                </div>
            </div>

            <?php if (!empty($error)) { ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>

            <form method="GET" class="filter-box">
                <div class="filter-caption">Criterios de Filtrado Avanzado</div>
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label-luxury">Desde</label>
                        <input type="date" name="fecha_desde" class="form-control-luxury" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label-luxury">Hasta</label>
                        <input type="date" name="fecha_hasta" class="form-control-luxury" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label-luxury">Categoría</label>
                        <select name="id_categoria" class="form-select-luxury">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $cat) { ?>
                                <option value="<?php echo (int) $cat['id_categoria']; ?>" <?php echo (in_array((int) $cat['id_categoria'], $ids_filtro_categoria, true)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $cat['nombre']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label-luxury">Prioridad</label>
                        <select name="prioridad" class="form-select-luxury">
                            <option value="">Todas</option>
                            <?php foreach (['Baja', 'Media', 'Alta', 'Crítica'] as $prio) { ?>
                                <option value="<?php echo htmlspecialchars($prio); ?>" <?php echo (strtolower($prioridad_sel) === strtolower($prio)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prio); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label-luxury">Estado</label>
                        <select name="estado" class="form-select-luxury">
                            <option value="">Todos</option>
                            <?php foreach ($estados_validos as $est) { ?>
                                <option value="<?php echo htmlspecialchars($est); ?>" <?php echo (strtolower($estado_sel) === strtolower($est)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($est); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <?php if ($tiene_columna_id_tecnico) { ?>
                        <div class="col-md-2">
                            <label class="form-label-luxury">Técnico</label>
                            <select name="id_tecnico" class="form-select-luxury">
                                <option value="">Todos</option>
                                <?php foreach ($tecnicos as $tec) { ?>
                                    <option value="<?php echo (int) $tec['id_usuario']; ?>" <?php echo (in_array((int) $tec['id_usuario'], $ids_filtro_tecnico, true)) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string) $tec['nombre']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    <?php } ?>

                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <a href="admin_incidencias.php" class="btn-clear-luxury">Limpiar</a>
                        <button type="submit" class="btn-luxury">Aplicar Filtros</button>
                    </div>
                </div>
            </form>

            <div class="summary-pill">
                <span>Resultados encontrados:</span>
                <strong><?php echo count($incidencias); ?> incidencia(s)</strong>
            </div>

            <div class="table-responsive">
                <table class="table table-luxury mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Título</th>
                            <th>Solicitante</th>
                            <th>Categoría</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                            <?php if ($tiene_columna_id_tecnico) { ?>
                                <th>Técnico</th>
                            <?php } ?>
                            <th>Fecha</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incidencias)) { ?>
                            <tr>
                                <td colspan="<?php echo $tiene_columna_id_tecnico ? '9' : '8'; ?>" class="text-center text-muted py-4">
                                    No se encontraron incidencias con los filtros seleccionados.
                                </td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($incidencias as $inc) { ?>
                                <?php
                                    $id_inc = (int) $inc['id_incidencia'];
                                    $estado_raw = trim((string) ($inc['estado'] ?? 'Abierto'));
                                    $estado_key = strtolower($estado_raw);
                                    $clase_estado = 'st-abierto';
                                    if (str_contains($estado_key, 'proceso')) {
                                        $clase_estado = 'st-proceso';
                                    } elseif (str_contains($estado_key, 'resuelto')) {
                                        $clase_estado = 'st-resuelto';
                                    } elseif (str_contains($estado_key, 'cerrado')) {
                                        $clase_estado = 'st-cerrado';
                                    }

                                    $prio_raw = trim((string) ($inc['prioridad'] ?? 'Media'));
                                    $prio_key = strtolower($prio_raw);
                                    $clase_prio = in_array($prio_key, ['alta', 'critica', 'crítica'], true) ? 'prio-alta' : '';
                                ?>
                                <tr>
                                    <td class="nowrap-col">#<?php echo $id_inc; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($inc['titulo'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($inc['solicitante_nombre'] ?? '')); ?></td>
                                    <td class="nowrap-col"><?php echo htmlspecialchars((string) ($inc['categoria_nombre'] ?? '')); ?></td>
                                    <td class="nowrap-col">
                                        <span class="badge-prio <?php echo $clase_prio; ?>">
                                            <?php echo htmlspecialchars($prio_raw); ?>
                                        </span>
                                    </td>
                                    <td class="nowrap-col">
                                        <span class="badge-status <?php echo $clase_estado; ?>">
                                            <?php echo htmlspecialchars($estado_raw); ?>
                                        </span>
                                    </td>
                                    <?php if ($tiene_columna_id_tecnico) { ?>
                                        <td><?php echo htmlspecialchars((string) ($inc['tecnico_asignado_nombre'] ?? 'Sin asignar')); ?></td>
                                    <?php } ?>
                                    <td class="nowrap-col"><?php echo htmlspecialchars((string) ($inc['fecha_creacion'] ?? '')); ?></td>
                                    <td class="text-end nowrap-col">
                                        <a href="ver_incidencia.php?id=<?php echo $id_inc; ?>" class="btn-manage">Gestionar</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>