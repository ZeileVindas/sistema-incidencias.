<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$rol_sesion = strtolower(trim((string) ($_SESSION['rol'] ?? '')));
$es_admin = in_array($rol_sesion, ['administrador', 'admin'], true);

if (!$es_admin) {
    header("Location: index.php");
    exit();
}

require_once 'config/conexion.php';

$id_admin_sesion = (int) $_SESSION['id_usuario'];
$nombre_admin = $_SESSION['nombre'] ?? 'Administrador';
$mensaje_exito = '';
$error = '';
$usuario_editar = null;

$roles_permitidos = [
    'Solicitante'   => 'Solicitante',
    'Tecnico'       => 'Técnico',
    'Administrador' => 'Administrador',
];

// Detectar columnas de contraseña (`password_hash` o `password`) y estado (`estado` o `activo`)
$col_estado = null;
$col_pass = 'password_hash';
try {
    $stmt_cols = $conexion->query("SHOW COLUMNS FROM usuarios");
    $cols_raw = $stmt_cols ? $stmt_cols->fetchAll(PDO::FETCH_ASSOC) : [];
    $columnas_disponibles = [];
    foreach ($cols_raw as $col) {
        $field = strtolower((string) ($col['Field'] ?? ''));
        $columnas_disponibles[] = $field;
        if (in_array($field, ['estado', 'activo'], true) && $col_estado === null) {
            $col_estado = $field;
        }
    }
    if (!in_array('password_hash', $columnas_disponibles, true) && in_array('password', $columnas_disponibles, true)) {
        $col_pass = 'password';
    }
} catch (PDOException $e) {
    $col_estado = null;
}

$normalizar_rol_bd = function (string $rol_raw): string {
    $r = strtolower(trim($rol_raw));
    if (in_array($r, ['administrador', 'admin'], true)) {
        return 'Administrador';
    }
    if (in_array($r, ['tecnico', 'técnico'], true)) {
        return 'Tecnico';
    }
    return 'Solicitante';
};

$etiqueta_rol_ui = function (string $rol_raw): string {
    $r = strtolower(trim($rol_raw));
    if (in_array($r, ['administrador', 'admin'], true)) {
        return 'Administrador';
    }
    if (in_array($r, ['tecnico', 'técnico'], true)) {
        return 'Técnico';
    }
    return 'Solicitante';
};

$es_estado_activo = function (array $fila) use ($col_estado): bool {
    if ($col_estado === null) {
        return true;
    }
    $val = $fila[$col_estado] ?? 1;
    if (is_numeric($val)) {
        return ((int) $val) === 1;
    }
    $s = strtolower(trim((string) $val));
    return !in_array($s, ['suspendido', 'inactivo', '0', 'false'], true);
};

$valor_estado_bd = function (bool $activo) use ($conexion, $col_estado) {
    if ($col_estado === null) {
        return null;
    }
    try {
        $stmt_c = $conexion->query("SHOW COLUMNS FROM usuarios LIKE " . $conexion->quote($col_estado));
        $info = $stmt_c ? $stmt_c->fetch(PDO::FETCH_ASSOC) : null;
        $tipo = strtolower((string) ($info['Type'] ?? ''));
        if (str_contains($tipo, 'int') || str_contains($tipo, 'tinyint') || str_contains($tipo, 'bool')) {
            return $activo ? 1 : 0;
        }
        if (str_contains($tipo, 'enum') || str_contains($tipo, 'varchar') || str_contains($tipo, 'char')) {
            if (str_contains($tipo, 'activo') || str_contains($tipo, 'suspendido')) {
                return $activo ? 'Activo' : 'Suspendido';
            }
            if (str_contains($tipo, 'inactivo')) {
                return $activo ? 'Activo' : 'Inactivo';
            }
        }
    } catch (PDOException $e) {
        // Fallback numérico
    }
    return $activo ? 1 : 0;
};

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = trim((string) ($_POST['accion'] ?? ''));

    if ($accion === 'crear') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $correo = trim((string) ($_POST['correo'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $rol_sel = $normalizar_rol_bd((string) ($_POST['rol'] ?? 'Solicitante'));
        $activo_sel = isset($_POST['activo']) ? ((string) $_POST['activo'] === '1') : true;

        if ($nombre === '' || $correo === '' || $password === '') {
            $error = "Complete nombre, correo y contraseña para crear la cuenta.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = "Ingrese un correo electrónico válido.";
        } elseif (strlen($password) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres.";
        } else {
            try {
                $stmt_dup = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE LOWER(TRIM(correo)) = LOWER(:correo) LIMIT 1");
                $stmt_dup->execute([':correo' => $correo]);
                if ($stmt_dup->fetch()) {
                    $error = "Ya existe un usuario registrado con ese correo electrónico.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $campos = ['nombre', 'correo', $col_pass, 'rol'];
                    $placeholders = [':nombre', ':correo', ':password', ':rol'];
                    $params_ins = [
                        ':nombre'   => $nombre,
                        ':correo'   => $correo,
                        ':password' => $hash,
                        ':rol'      => $rol_sel,
                    ];

                    if ($col_estado !== null) {
                        $campos[] = "`{$col_estado}`";
                        $placeholders[] = ':estado';
                        $params_ins[':estado'] = $valor_estado_bd($activo_sel);
                    }

                    $sql_ins = "INSERT INTO usuarios (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt_ins = $conexion->prepare($sql_ins);
                    $stmt_ins->execute($params_ins);
                    $mensaje_exito = "Usuario creado correctamente.";
                }
            } catch (PDOException $e) {
                error_log("Error al crear usuario en admin_usuarios.php: " . $e->getMessage());
                $error = "No se pudo crear el usuario. Verifique los campos de su tabla usuarios.";
            }
        }
    } elseif ($accion === 'editar') {
        $id_target = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $correo = trim((string) ($_POST['correo'] ?? ''));
        $password_nueva = (string) ($_POST['password'] ?? '');
        $rol_sel = $normalizar_rol_bd((string) ($_POST['rol'] ?? 'Solicitante'));
        $activo_sel = isset($_POST['activo']) ? ((string) $_POST['activo'] === '1') : true;

        if (!$id_target || $id_target <= 0 || $nombre === '' || $correo === '') {
            $error = "Datos inválidos para actualizar el usuario.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = "Ingrese un correo electrónico válido.";
        } elseif ($id_target === $id_admin_sesion && !$activo_sel) {
            $error = "No puede suspender su propia cuenta mientras tiene sesión activa.";
        } else {
            try {
                $stmt_dup = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE LOWER(TRIM(correo)) = LOWER(:correo) AND id_usuario <> :id LIMIT 1");
                $stmt_dup->execute([':correo' => $correo, ':id' => $id_target]);
                if ($stmt_dup->fetch()) {
                    $error = "Ese correo ya pertenece a otra cuenta.";
                } else {
                    $sets = ["nombre = :nombre", "correo = :correo", "rol = :rol"];
                    $params = [
                        ':nombre' => $nombre,
                        ':correo' => $correo,
                        ':rol'    => $rol_sel,
                        ':id'     => $id_target,
                    ];

                    if ($password_nueva !== '') {
                        if (strlen($password_nueva) < 6) {
                            $error = "La nueva contraseña debe tener al menos 6 caracteres.";
                        } else {
                            $sets[] = "`{$col_pass}` = :password";
                            $params[':password'] = password_hash($password_nueva, PASSWORD_DEFAULT);
                        }
                    }

                    if ($error === '' && $col_estado !== null) {
                        $sets[] = "`{$col_estado}` = :estado";
                        $params[':estado'] = $valor_estado_bd($activo_sel);
                    }

                    if ($error === '') {
                        $sql_upd = "UPDATE usuarios SET " . implode(", ", $sets) . " WHERE id_usuario = :id";
                        $stmt_upd = $conexion->prepare($sql_upd);
                        $stmt_upd->execute($params);
                        $mensaje_exito = "Cuenta actualizada correctamente.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Error al editar usuario en admin_usuarios.php: " . $e->getMessage());
                $error = "Ocurrió un error al actualizar la cuenta.";
            }
        }
    } elseif ($accion === 'toggle_estado') {
        $id_target = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT);
        $nuevo_activo = ((string) ($_POST['nuevo_activo'] ?? '0') === '1');

        if (!$id_target || $id_target <= 0) {
            $error = "Usuario no válido.";
        } elseif ($col_estado === null) {
            $error = "La tabla usuarios no tiene columna de estado (estado/activo) configurada.";
        } elseif ($id_target === $id_admin_sesion && !$nuevo_activo) {
            $error = "No puede suspender su propia cuenta mientras tiene sesión activa.";
        } else {
            try {
                $stmt_t = $conexion->prepare("UPDATE usuarios SET `{$col_estado}` = :estado WHERE id_usuario = :id");
                $stmt_t->execute([
                    ':estado' => $valor_estado_bd($nuevo_activo),
                    ':id'     => $id_target,
                ]);
                $mensaje_exito = $nuevo_activo ? "Cuenta reactivada correctamente." : "Cuenta suspendida correctamente.";
            } catch (PDOException $e) {
                error_log("Error al cambiar estado de usuario: " . $e->getMessage());
                $error = "No se pudo cambiar el estado de la cuenta.";
            }
        }
    }
}

// Consultar listado de usuarios
$usuarios = [];
try {
    $stmt_u = $conexion->query("SELECT * FROM usuarios ORDER BY id_usuario ASC");
    $usuarios = $stmt_u ? $stmt_u->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    error_log("Error al listar usuarios en admin_usuarios.php: " . $e->getMessage());
    $error = $error ?: "Error al cargar el listado de usuarios.";
}

// Si se solicita editar un usuario por GET (?editar=ID)
$id_editar_get = filter_var($_GET['editar'] ?? null, FILTER_VALIDATE_INT);
if ($id_editar_get && $id_editar_get > 0) {
    foreach ($usuarios as $u) {
        if ((int) $u['id_usuario'] === (int) $id_editar_get) {
            $usuario_editar = $u;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Usuarios | Sistema TI</title>
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
        }
        body {
            background-color: var(--ivory);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--midnight-navy);
        }
        .navbar-luxury {
            background-color: var(--midnight-navy);
            border-bottom: 1px solid rgba(176, 141, 87, 0.3);
            padding: 16px 30px;
        }
        .navbar-brand-custom {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #FFFFFF !important;
            text-decoration: none;
        }
        .navbar-brand-custom span { color: var(--brushed-brass); }
        .user-badge {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(217, 205, 187, 0.25);
            padding: 8px 16px;
            border-radius: 50px;
            color: var(--ivory);
            font-size: 0.85rem;
        }
        .user-badge strong { color: var(--brushed-brass); }
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
        .btn-logout { border-color: rgba(248, 113, 113, 0.4); color: #F87171; }
        .btn-logout:hover { background-color: #F87171; color: #FFFFFF; border-color: #F87171; }
        .main-container { flex: 1; max-width: 1250px; width: 100%; margin: 0 auto; padding: 40px 20px; }
        .card-panel {
            background: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(22, 32, 51, 0.15);
            border: 1px solid rgba(217, 205, 187, 0.6);
            padding: 35px 40px;
            margin-bottom: 28px;
            position: relative;
        }
        .card-panel::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; height: 6px;
            border-radius: 24px 24px 0 0;
            background: linear-gradient(90deg, var(--deep-navy), var(--brushed-brass));
        }
        .panel-title { font-family: 'Cormorant Garamond', serif; font-size: 2.1rem; font-weight: 700; margin-bottom: 4px; }
        .form-control-luxury, .form-select-luxury {
            width: 100%;
            padding: 10px 14px;
            font-size: 0.9rem;
            color: var(--midnight-navy);
            background-color: #FAF8F5;
            border: 1.5px solid var(--warm-beige);
            border-radius: 10px;
        }
        .btn-luxury {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--midnight-navy) 100%);
            color: var(--ivory);
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid var(--brushed-brass);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .table-luxury th {
            background-color: #FAF8F5;
            color: var(--deep-navy);
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 14px 16px;
            border-bottom: 1.5px solid var(--warm-beige);
        }
        .table-luxury td { padding: 14px 16px; vertical-align: middle; border-bottom: 1px solid #F3F0EC; }
    </style>
</head>
<body>
    <nav class="navbar navbar-luxury">
        <div class="container-fluid px-4">
            <a href="index.php" class="navbar-brand-custom">Sistema de <span>Incidencias</span></a>
            <div class="d-flex align-items-center gap-3">
                <div class="user-badge"><span>Administrador:</span> <strong><?php echo htmlspecialchars($nombre_admin); ?></strong></div>
                <a href="index.php" class="btn-nav-action">Panel Principal</a>
                <a href="admin_incidencias.php" class="btn-nav-action">Cola de Tickets</a>
                <a href="logout.php" class="btn-nav-action btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <?php if (!empty($mensaje_exito)) { ?>
            <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php } ?>
        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>

        <!-- Formulario Alta / Edición -->
        <div class="card-panel">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h1 class="panel-title">
                        <?php echo $usuario_editar ? 'Editar Cuenta #' . (int) $usuario_editar['id_usuario'] : 'Registrar Nueva Cuenta de Usuario'; ?>
                    </h1>
                    <p class="text-muted small mb-0">Gestione accesos, roles y estado de cuentas del sistema.</p>
                </div>
                <?php if ($usuario_editar) { ?>
                    <a href="admin_usuarios.php" class="btn btn-outline-secondary btn-sm">Cancelar edición</a>
                <?php } ?>
            </div>

            <form method="POST" class="row g-3">
                <input type="hidden" name="accion" value="<?php echo $usuario_editar ? 'editar' : 'crear'; ?>">
                <?php if ($usuario_editar) { ?>
                    <input type="hidden" name="id_usuario" value="<?php echo (int) $usuario_editar['id_usuario']; ?>">
                <?php } ?>

                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Nombre completo</label>
                    <input type="text" name="nombre" class="form-control-luxury" required
                           value="<?php echo htmlspecialchars((string) ($usuario_editar['nombre'] ?? '')); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Correo electrónico</label>
                    <input type="email" name="correo" class="form-control-luxury" required
                           value="<?php echo htmlspecialchars((string) ($usuario_editar['correo'] ?? '')); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold small">
                        <?php echo $usuario_editar ? 'Nueva contraseña (opcional)' : 'Contraseña'; ?>
                    </label>
                    <input type="password" name="password" class="form-control-luxury"
                           <?php echo $usuario_editar ? '' : 'required'; ?> minlength="6"
                           placeholder="<?php echo $usuario_editar ? 'Dejar en blanco para mantener actual' : 'Mínimo 6 caracteres'; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Rol</label>
                    <?php $rol_form = $usuario_editar ? $normalizar_rol_bd((string) $usuario_editar['rol']) : 'Solicitante'; ?>
                    <select name="rol" class="form-select-luxury" required>
                        <?php foreach ($roles_permitidos as $val_rol => $etq_rol) { ?>
                            <option value="<?php echo htmlspecialchars($val_rol); ?>" <?php echo ($rol_form === $val_rol) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($etq_rol); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <?php if ($col_estado !== null) { ?>
                    <?php $activo_form = $usuario_editar ? $es_estado_activo($usuario_editar) : true; ?>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Estado de cuenta</label>
                        <select name="activo" class="form-select-luxury">
                            <option value="1" <?php echo $activo_form ? 'selected' : ''; ?>>Activo</option>
                            <option value="0" <?php echo !$activo_form ? 'selected' : ''; ?>>Suspendido</option>
                        </select>
                    </div>
                <?php } ?>

                <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                    <button type="submit" class="btn-luxury">
                        <?php echo $usuario_editar ? 'Guardar Cambios' : 'Crear Usuario'; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Listado de Usuarios -->
        <div class="card-panel">
            <h2 class="panel-title mb-3">Cuentas Registradas</h2>
            <div class="table-responsive">
                <table class="table table-luxury mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <?php if ($col_estado !== null) { ?>
                                <th>Estado</th>
                            <?php } ?>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)) { ?>
                            <tr>
                                <td colspan="<?php echo $col_estado !== null ? '6' : '5'; ?>" class="text-center text-muted py-4">
                                    No hay usuarios registrados.
                                </td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($usuarios as $u) { ?>
                                <?php
                                    $id_u = (int) $u['id_usuario'];
                                    $activo_u = $es_estado_activo($u);
                                ?>
                                <tr>
                                    <td>#<?php echo $id_u; ?></td>
                                    <td><?php echo htmlspecialchars((string) $u['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $u['correo']); ?></td>
                                    <td><?php echo htmlspecialchars($etiqueta_rol_ui((string) $u['rol'])); ?></td>
                                    <?php if ($col_estado !== null) { ?>
                                        <td>
                                            <span class="badge <?php echo $activo_u ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $activo_u ? 'Activo' : 'Suspendido'; ?>
                                            </span>
                                        </td>
                                    <?php } ?>
                                    <td class="text-end">
                                        <a href="admin_usuarios.php?editar=<?php echo $id_u; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                        <?php if ($col_estado !== null && $id_u !== $id_admin_sesion) { ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="accion" value="toggle_estado">
                                                <input type="hidden" name="id_usuario" value="<?php echo $id_u; ?>">
                                                <input type="hidden" name="nuevo_activo" value="<?php echo $activo_u ? '0' : '1'; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $activo_u ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                                    <?php echo $activo_u ? 'Suspender' : 'Activar'; ?>
                                                </button>
                                            </form>
                                        <?php } ?>
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