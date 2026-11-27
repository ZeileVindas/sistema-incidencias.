<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/conexion.php';

$id_usuario_sesion = (int) $_SESSION['id_usuario'];
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$rol_sesion = strtolower(trim((string) ($_SESSION['rol'] ?? 'solicitante')));
$es_personal_soporte = in_array($rol_sesion, ['tecnico', 'técnico', 'administrador', 'admin'], true);
$etiqueta_rol = in_array($rol_sesion, ['administrador', 'admin'], true) ? 'Administrador' : ($es_personal_soporte ? 'Técnico' : 'Solicitante');

$id_incidencia = filter_var($_GET['id'] ?? ($_POST['id_incidencia'] ?? null), FILTER_VALIDATE_INT);
if (!$id_incidencia || $id_incidencia <= 0) {
    header("Location: " . ($es_personal_soporte ? "admin_incidencias.php" : "mis_incidencias.php"));
    exit();
}

$mensaje_exito = '';
$error = '';

// Detectar columnas opcionales de incidencias y tablas auxiliares
$columnas_inc = [];
try {
    $stmt_c = $conexion->query("SHOW COLUMNS FROM incidencias");
    foreach ($stmt_c ? $stmt_c->fetchAll(PDO::FETCH_ASSOC) : [] as $col) {
        $columnas_inc[strtolower((string) $col['Field'])] = true;
    }
} catch (PDOException $e) {
    $columnas_inc = [];
}

$tiene_id_tecnico = !empty($columnas_inc['id_tecnico']);
$tiene_fecha_cierre = !empty($columnas_inc['fecha_cierre']) ? 'fecha_cierre' : (!empty($columnas_inc['closed_at']) ? 'closed_at' : null);

$tabla_bitacora = null;
foreach (['seguimiento', 'comentarios', 'notas_incidencia', 'bitacora'] as $cand) {
    try {
        $stmt_t = $conexion->query("SHOW TABLES LIKE " . $conexion->quote($cand));
        if ($stmt_t && $stmt_t->rowCount() > 0) {
            $tabla_bitacora = $cand;
            break;
        }
    } catch (PDOException $e) {
        // Continuar
    }
}

$cols_bitacora = [];
if ($tabla_bitacora !== null) {
    try {
        $stmt_bc = $conexion->query("SHOW COLUMNS FROM `{$tabla_bitacora}`");
        foreach ($stmt_bc ? $stmt_bc->fetchAll(PDO::FETCH_ASSOC) : [] as $col) {
            $cols_bitacora[strtolower((string) $col['Field'])] = true;
        }
    } catch (PDOException $e) {
        $cols_bitacora = [];
    }
}

$col_autor_bit = !empty($cols_bitacora['id_tecnico']) ? 'id_tecnico' : (!empty($cols_bitacora['id_usuario']) ? 'id_usuario' : null);
$col_texto_bit = !empty($cols_bitacora['comentario']) ? 'comentario' : (!empty($cols_bitacora['contenido']) ? 'contenido' : (!empty($cols_bitacora['nota']) ? 'nota' : 'observacion'));
$col_fecha_bit = !empty($cols_bitacora['fecha_comentario']) ? 'fecha_comentario' : (!empty($cols_bitacora['fecha_creacion']) ? 'fecha_creacion' : (!empty($cols_bitacora['fecha']) ? 'fecha' : null));

// Función para cargar incidencia y verificar permisos
$cargar_incidencia = function () use ($conexion, $id_incidencia, $tiene_id_tecnico) {
    $sql = "SELECT i.*, u.nombre AS solicitante_nombre, u.correo AS solicitante_correo, c.nombre AS categoria_nombre";
    if ($tiene_id_tecnico) {
        $sql .= ", CASE WHEN LOWER(TRIM(COALESCE(t.rol, ''))) NOT IN ('solicitante', 'usuario', 'cliente') AND TRIM(COALESCE(t.nombre, '')) <> '' THEN t.nombre ELSE NULL END AS tecnico_asignado_nombre";
    }
    $sql .= " FROM incidencias i
              JOIN usuarios u ON i.id_usuario = u.id_usuario
              JOIN categorias c ON i.id_categoria = c.id_categoria";
    if ($tiene_id_tecnico) {
        $sql .= " LEFT JOIN usuarios t ON i.id_tecnico = t.id_usuario";
    }
    $sql .= " WHERE i.id_incidencia = :id LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $id_incidencia]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
};

try {
    $incidencia = $cargar_incidencia();
} catch (PDOException $e) {
    error_log("Error al cargar incidencia en ver_incidencia.php: " . $e->getMessage());
    die("Error al consultar la incidencia.");
}

if (!$incidencia) {
    header("Location: " . ($es_personal_soporte ? "admin_incidencias.php" : "mis_incidencias.php"));
    exit();
}

// Solicitante solo puede ver sus propias incidencias
if (!$es_personal_soporte && (int) $incidencia['id_usuario'] !== $id_usuario_sesion) {
    header("Location: mis_incidencias.php");
    exit();
}

$esta_cerrada = (strtolower(trim((string) $incidencia['estado'])) === 'cerrado');

// Cargar técnicos válidos sin duplicados visuales
$tecnicos = [];
if ($es_personal_soporte && $tiene_id_tecnico) {
    try {
        $stmt_tec = $conexion->query("
            SELECT id_usuario, nombre, rol
            FROM usuarios
            WHERE LOWER(TRIM(rol)) NOT IN ('solicitante', 'usuario', 'cliente')
            ORDER BY nombre ASC, id_usuario ASC
        ");
        $vistos = [];
        foreach ($stmt_tec ? $stmt_tec->fetchAll(PDO::FETCH_ASSOC) : [] as $tec) {
            $clave = mb_strtolower(trim((string) $tec['nombre']), 'UTF-8');
            if ($clave === '' || isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $tecnicos[] = $tec;
        }
    } catch (PDOException $e) {
        error_log("Error al cargar técnicos en ver_incidencia.php: " . $e->getMessage());
    }
}

// Procesar acciones POST (solo soporte y solo si no está Cerrado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $es_personal_soporte) {
    $incidencia = $cargar_incidencia();
    $esta_cerrada = (strtolower(trim((string) $incidencia['estado'])) === 'cerrado');

    if ($esta_cerrada) {
        $error = "Este ticket está Cerrado y no permite modificaciones ni nuevas notas.";
    } else {
        $accion = trim((string) ($_POST['accion'] ?? ''));

        if ($accion === 'asignar_tecnico' && $tiene_id_tecnico) {
            $id_tec_sel = filter_var($_POST['id_tecnico'] ?? null, FILTER_VALIDATE_INT);
            if (!$id_tec_sel || $id_tec_sel <= 0) {
                $error = "Seleccione un técnico o administrador válido.";
            } else {
                $stmt_val = $conexion->prepare("
                    SELECT id_usuario, nombre
                    FROM usuarios
                    WHERE id_usuario = :id_tec
                      AND LOWER(TRIM(rol)) NOT IN ('solicitante', 'usuario', 'cliente')
                    LIMIT 1
                ");
                $stmt_val->execute([':id_tec' => $id_tec_sel]);
                $tec_val = $stmt_val->fetch(PDO::FETCH_ASSOC);

                if (!$tec_val) {
                    $error = "El usuario seleccionado no corresponde a un perfil técnico o administrativo válido.";
                } else {
                    try {
                        $conexion->beginTransaction();
                        $nuevo_estado = (strtolower(trim((string) $incidencia['estado'])) === 'abierto') ? 'En Proceso' : (string) $incidencia['estado'];
                        $stmt_upd = $conexion->prepare("
                            UPDATE incidencias
                            SET id_tecnico = :id_tec, estado = :estado
                            WHERE id_incidencia = :id AND LOWER(TRIM(estado)) <> 'cerrado'
                        ");
                        $stmt_upd->execute([
                            ':id_tec' => (int) $tec_val['id_usuario'],
                            ':estado' => $nuevo_estado,
                            ':id'     => $id_incidencia,
                        ]);

                        if ($tabla_bitacora !== null && $col_texto_bit !== '') {
                            $nota_auto = "Ticket asignado a " . $tec_val['nombre'] . ".";
                            $campos_b = ['id_incidencia', $col_texto_bit];
                            $vals_b = [':id_inc', ':nota'];
                            $params_b = [':id_inc' => $id_incidencia, ':nota' => $nota_auto];
                            if ($col_autor_bit !== null) {
                                $campos_b[] = $col_autor_bit;
                                $vals_b[] = ':autor';
                                $params_b[':autor'] = $id_usuario_sesion;
                            }
                            if ($col_fecha_bit !== null) {
                                $campos_b[] = $col_fecha_bit;
                                $vals_b[] = 'NOW()';
                            }
                            $sql_b = "INSERT INTO `{$tabla_bitacora}` (" . implode(', ', $campos_b) . ") VALUES (" . implode(', ', $vals_b) . ")";
                            $conexion->prepare($sql_b)->execute($params_b);
                        }

                        $conexion->commit();
                        $mensaje_exito = "Técnico asignado correctamente.";
                        $incidencia = $cargar_incidencia();
                        $esta_cerrada = (strtolower(trim((string) $incidencia['estado'])) === 'cerrado');
                    } catch (PDOException $e) {
                        if ($conexion->inTransaction()) {
                            $conexion->rollBack();
                        }
                        error_log("Error al asignar técnico: " . $e->getMessage());
                        $error = "No se pudo asignar el técnico.";
                    }
                }
            }
        } elseif ($accion === 'cambiar_estado') {
            $nuevo_estado = trim((string) ($_POST['estado'] ?? ''));
            $estados_permitidos = ['Abierto', 'En Proceso', 'Resuelto', 'Cerrado'];
            if (!in_array($nuevo_estado, $estados_permitidos, true)) {
                $error = "Estado no válido.";
            } else {
                try {
                    $conexion->beginTransaction();
                    $sets = ["estado = :estado"];
                    $params_e = [':estado' => $nuevo_estado, ':id' => $id_incidencia];
                    if ($nuevo_estado === 'Cerrado' && $tiene_fecha_cierre !== null) {
                        $sets[] = "`{$tiene_fecha_cierre}` = NOW()";
                    }
                    $sql_e = "UPDATE incidencias SET " . implode(', ', $sets) . " WHERE id_incidencia = :id AND LOWER(TRIM(estado)) <> 'cerrado'";
                    $conexion->prepare($sql_e)->execute($params_e);

                    if ($tabla_bitacora !== null && $col_texto_bit !== '') {
                        $nota_est = "Estado actualizado a: " . $nuevo_estado . ".";
                        $campos_b = ['id_incidencia', $col_texto_bit];
                        $vals_b = [':id_inc', ':nota'];
                        $params_b = [':id_inc' => $id_incidencia, ':nota' => $nota_est];
                        if ($col_autor_bit !== null) {
                            $campos_b[] = $col_autor_bit;
                            $vals_b[] = ':autor';
                            $params_b[':autor'] = $id_usuario_sesion;
                        }
                        if ($col_fecha_bit !== null) {
                            $campos_b[] = $col_fecha_bit;
                            $vals_b[] = 'NOW()';
                        }
                        $conexion->prepare("INSERT INTO `{$tabla_bitacora}` (" . implode(', ', $campos_b) . ") VALUES (" . implode(', ', $vals_b) . ")")->execute($params_b);
                    }

                    $conexion->commit();
                    $mensaje_exito = ($nuevo_estado === 'Cerrado')
                        ? "Ticket cerrado definitivamente. Queda bloqueado para futuros cambios."
                        : "Estado actualizado correctamente.";
                    $incidencia = $cargar_incidencia();
                    $esta_cerrada = (strtolower(trim((string) $incidencia['estado'])) === 'cerrado');
                } catch (PDOException $e) {
                    if ($conexion->inTransaction()) {
                        $conexion->rollBack();
                    }
                    error_log("Error al actualizar estado: " . $e->getMessage());
                    $error = "No se pudo actualizar el estado.";
                }
            }
        } elseif ($accion === 'agregar_nota') {
            $nota_texto = trim((string) ($_POST['comentario'] ?? ''));
            if ($nota_texto === '') {
                $error = "Escriba una observación antes de registrar en la bitácora.";
            } elseif ($tabla_bitacora === null || $col_texto_bit === '') {
                $error = "No se encontró tabla de bitácora configurada.";
            } else {
                try {
                    $campos_b = ['id_incidencia', $col_texto_bit];
                    $vals_b = [':id_inc', ':nota'];
                    $params_b = [':id_inc' => $id_incidencia, ':nota' => $nota_texto];
                    if ($col_autor_bit !== null) {
                        $campos_b[] = $col_autor_bit;
                        $vals_b[] = ':autor';
                        $params_b[':autor'] = $id_usuario_sesion;
                    }
                    if ($col_fecha_bit !== null) {
                        $campos_b[] = $col_fecha_bit;
                        $vals_b[] = 'NOW()';
                    }
                    $sql_b = "INSERT INTO `{$tabla_bitacora}` (" . implode(', ', $campos_b) . ") VALUES (" . implode(', ', $vals_b) . ")";
                    $conexion->prepare($sql_b)->execute($params_b);
                    $mensaje_exito = "Nota registrada en la bitácora.";
                } catch (PDOException $e) {
                    error_log("Error al registrar nota en bitácora: " . $e->getMessage());
                    $error = "No se pudo guardar la nota en la bitácora.";
                }
            }
        }
    }
}

// Consultar historial de bitácora
$notas = [];
if ($tabla_bitacora !== null && $col_texto_bit !== '') {
    try {
        $select_autor = ($col_autor_bit !== null) ? ", COALESCE(u.nombre, 'Soporte TI') AS autor_nombre" : ", 'Soporte TI' AS autor_nombre";
        $select_fecha = ($col_fecha_bit !== null) ? ", b.`{$col_fecha_bit}` AS fecha_nota" : ", '' AS fecha_nota";
        $join_autor = ($col_autor_bit !== null) ? " LEFT JOIN usuarios u ON b.`{$col_autor_bit}` = u.id_usuario" : "";
        $order_bit = ($col_fecha_bit !== null) ? " ORDER BY b.`{$col_fecha_bit}` DESC" : "";

        $sql_notas = "SELECT b.`{$col_texto_bit}` AS texto_nota{$select_autor}{$select_fecha}
                      FROM `{$tabla_bitacora}` b
                      {$join_autor}
                      WHERE b.id_incidencia = :id
                      {$order_bit}";
        $stmt_n = $conexion->prepare($sql_notas);
        $stmt_n->execute([':id' => $id_incidencia]);
        $notas = $stmt_n->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al consultar bitácora: " . $e->getMessage());
    }
}

$id_tec_actual = (int) ($incidencia['id_tecnico'] ?? 0);
$nombre_tec_actual = trim((string) ($incidencia['tecnico_asignado_nombre'] ?? ''));
$texto_tecnico_header = ($id_tec_actual > 0 && $nombre_tec_actual !== '') ? $nombre_tec_actual : 'Sin asignar';
$url_volver = $es_personal_soporte ? 'admin_incidencias.php' : 'mis_incidencias.php';
$texto_volver = $es_personal_soporte ? 'Regresar a la Cola' : 'Mis Incidencias';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Incidencia #<?php echo (int) $incidencia['id_incidencia']; ?> | Sistema TI</title>
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
        }
        .btn-nav-action:hover { background-color: var(--ivory); color: var(--midnight-navy); }
        .btn-logout { border-color: rgba(248, 113, 113, 0.45); color: #F87171; }
        .btn-logout:hover { background-color: #F87171; color: #FFFFFF; border-color: #F87171; }

        .main-container { flex: 1; max-width: 1050px; width: 100%; margin: 0 auto; padding: 36px 20px; }
        .ticket-card {
            background: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(22, 32, 51, 0.15);
            border: 1px solid rgba(217, 205, 187, 0.7);
            padding: 36px 40px;
            position: relative;
        }
        .ticket-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; height: 6px;
            border-radius: 24px 24px 0 0;
            background: linear-gradient(90deg, var(--midnight-navy), var(--deep-navy), var(--brushed-brass));
        }
        .ticket-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.15rem;
            font-weight: 700;
            color: var(--midnight-navy);
            margin-bottom: 6px;
        }
        .meta-line { font-size: 0.84rem; color: #6B7280; margin-bottom: 14px; }
        .pill-meta {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 600;
            background-color: #FAF8F5;
            border: 1px solid var(--warm-beige);
            color: var(--deep-navy);
            margin-right: 6px;
        }
        .box-luxury {
            background-color: #FAF8F5;
            border: 1.5px solid var(--warm-beige);
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 20px;
        }
        .box-label {
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--brushed-brass);
            margin-bottom: 8px;
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
            padding: 9px 18px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--midnight-navy) 100%);
            color: var(--ivory);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid var(--brushed-brass);
            white-space: nowrap;
        }
        .note-item {
            background-color: #FFFFFF;
            border: 1px solid #EAE4D9;
            border-left: 4px solid var(--brushed-brass);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-luxury">
        <div class="container-fluid px-4">
            <a href="index.php" class="navbar-brand-custom">Sistema de <span>Incidencias</span></a>
            <div class="d-flex align-items-center gap-3">
                <div class="user-badge"><span><?php echo htmlspecialchars($etiqueta_rol); ?>:</span> <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></div>
                <a href="<?php echo htmlspecialchars($url_volver); ?>" class="btn-nav-action"><?php echo htmlspecialchars($texto_volver); ?></a>
                <a href="logout.php" class="btn-nav-action btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="ticket-card">
            <?php if (!empty($mensaje_exito)) { ?>
                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($mensaje_exito); ?></div>
            <?php } ?>
            <?php if (!empty($error)) { ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>

            <h1 class="ticket-title">#<?php echo (int) $incidencia['id_incidencia']; ?> — <?php echo htmlspecialchars((string) $incidencia['titulo']); ?></h1>
            <div class="meta-line">
                Solicitado por: <strong><?php echo htmlspecialchars((string) $incidencia['solicitante_nombre']); ?></strong>
                • Fecha de creación: <strong><?php echo htmlspecialchars((string) $incidencia['fecha_creacion']); ?></strong>
                <?php if ($tiene_id_tecnico) { ?>
                    • Técnico asignado: <strong><?php echo htmlspecialchars($texto_tecnico_header); ?></strong>
                <?php } ?>
            </div>

            <div class="mb-4">
                <span class="pill-meta"><?php echo htmlspecialchars((string) $incidencia['categoria_nombre']); ?></span>
                <span class="pill-meta">Prioridad: <?php echo htmlspecialchars((string) $incidencia['prioridad']); ?></span>
                <span class="pill-meta">Estado: <?php echo htmlspecialchars((string) $incidencia['estado']); ?></span>
            </div>

            <div class="box-luxury">
                <div class="box-label">Descripción Detallada del Problema</div>
                <div><?php echo nl2br(htmlspecialchars((string) $incidencia['descripcion'])); ?></div>
            </div>

            <?php if ($esta_cerrada) { ?>
                <div class="box-luxury">
                    Este ticket está en estado <strong>Cerrado</strong>. La asignación de técnico, la edición de estado y el registro de nuevas notas en la bitácora están bloqueados.
                </div>
            <?php } elseif ($es_personal_soporte) { ?>
                <div class="row g-3 mb-4">
                    <?php if ($tiene_id_tecnico) { ?>
                        <div class="col-md-6">
                            <form method="POST" class="box-luxury mb-0 h-100">
                                <input type="hidden" name="accion" value="asignar_tecnico">
                                <input type="hidden" name="id_incidencia" value="<?php echo (int) $incidencia['id_incidencia']; ?>">
                                <div class="box-label">Asignar Técnico Responsable</div>
                                <div class="d-flex gap-2">
                                    <select name="id_tecnico" class="form-select-luxury" required>
                                        <option value="">Seleccione un técnico...</option>
                                        <?php foreach ($tecnicos as $tec) { ?>
                                            <option value="<?php echo (int) $tec['id_usuario']; ?>" <?php echo ($id_tec_actual === (int) $tec['id_usuario']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars((string) $tec['nombre']); ?> (<?php echo htmlspecialchars((string) $tec['rol']); ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit" class="btn-luxury">Asignar</button>
                                </div>
                            </form>
                        </div>
                    <?php } ?>

                    <div class="<?php echo $tiene_id_tecnico ? 'col-md-6' : 'col-12'; ?>">
                        <form method="POST" class="box-luxury mb-0 h-100">
                            <input type="hidden" name="accion" value="cambiar_estado">
                            <input type="hidden" name="id_incidencia" value="<?php echo (int) $incidencia['id_incidencia']; ?>">
                            <div class="box-label">Cambiar Estado del Ticket</div>
                            <div class="d-flex gap-2">
                                <select name="estado" class="form-select-luxury" required>
                                    <?php foreach (['Abierto', 'En Proceso', 'Resuelto', 'Cerrado'] as $est_op) { ?>
                                        <option value="<?php echo htmlspecialchars($est_op); ?>" <?php echo (strcasecmp((string) $incidencia['estado'], $est_op) === 0) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($est_op); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <button type="submit" class="btn-luxury">Actualizar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <form method="POST" class="box-luxury">
                    <input type="hidden" name="accion" value="agregar_nota">
                    <input type="hidden" name="id_incidencia" value="<?php echo (int) $incidencia['id_incidencia']; ?>">
                    <div class="box-label">Bitácora de Seguimiento</div>
                    <label class="form-label small fw-semibold mb-2">Agregar nueva nota u observación técnica:</label>
                    <textarea name="comentario" rows="3" class="form-control-luxury mb-3" placeholder="Escriba el diagnóstico o avance técnico..." required></textarea>
                    <button type="submit" class="btn-luxury">Registrar Nota en Bitácora</button>
                </form>
            <?php } ?>

            <div class="box-luxury mb-0">
                <div class="box-label">Historial de Avances</div>
                <?php if (count($notas) > 0) { ?>
                    <?php foreach ($notas as $n) { ?>
                        <div class="note-item">
                            <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                                <span>Técnico: <strong><?php echo htmlspecialchars((string) $n['autor_nombre']); ?></strong></span>
                                <span><?php echo htmlspecialchars((string) $n['fecha_nota']); ?></span>
                            </div>
                            <div><?php echo nl2br(htmlspecialchars((string) $n['texto_nota'])); ?></div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="text-muted small">Sin observaciones registradas aún.</div>
                <?php } ?>
            </div>
        </div>
    </div>
</body>
</html>