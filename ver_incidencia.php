<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/conexion.php';

$id_incidencia = $_GET['id'] ?? null;
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$mensaje_exito = '';
$error = '';
$seguimientos = [];

if (!$id_incidencia) {
    header("Location: index.php");
    exit();
}

// Procesar actualización de estado o bitácora si se envía POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['nuevo_estado'])) {
        $nuevo_estado = $_POST['nuevo_estado'];
        try {
            $stmt_upd = $conexion->prepare("UPDATE incidencias SET estado = :estado WHERE id_incidencia = :id");
            $stmt_upd->execute([':estado' => $nuevo_estado, ':id' => $id_incidencia]);
            $mensaje_exito = "Estado actualizado correctamente.";
        } catch (PDOException $e) {
            $error = "Error al actualizar el estado: " . $e->getMessage();
        }
    }

    if (isset($_POST['nota_bitacora'])) {
        $nota = trim($_POST['nota_bitacora']);
        if (!empty($nota)) {
            try {
                // Inserción adaptada a la estructura real de tu tabla seguimiento
                $stmt_bit = $conexion->prepare("INSERT INTO seguimiento (id_incidencia, id_tecnico, comentario, fecha_comentario) VALUES (:id_inc, :id_usr, :comentario, NOW())");
                $stmt_bit->execute([
                    ':id_inc' => $id_incidencia,
                    ':id_usr' => $_SESSION['id_usuario'],
                    ':comentario' => $nota
                ]);
                $mensaje_exito = "Nota registrada en la bitácora con éxito.";
            } catch (PDOException $e) {
                $error = "Error al registrar en la bitácora: " . $e->getMessage();
            }
        }
    }
}

// Obtener detalles de la incidencia
try {
    $stmt = $conexion->prepare("
        SELECT i.*, u.nombre AS solicitante_nombre, c.nombre AS categoria_nombre 
        FROM incidencias i 
        JOIN usuarios u ON i.id_usuario = u.id_usuario 
        JOIN categorias c ON i.id_categoria = c.id_categoria 
        WHERE i.id_incidencia = :id
    ");
    $stmt->execute([':id' => $id_incidencia]);
    $incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incidencia) {
        header("Location: index.php");
        exit();
    }

    // Obtener historial de bitácora adaptado a las columnas reales
    $stmt_seg = $conexion->prepare("
        SELECT s.*, u.nombre AS tecnico_nombre 
        FROM seguimiento s 
        JOIN usuarios u ON s.id_tecnico = u.id_usuario 
        WHERE s.id_incidencia = :id 
        ORDER BY s.id_seguimiento DESC
    ");
    $stmt_seg->execute([':id' => $id_incidencia]);
    $seguimientos = $stmt_seg->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Incidencia #<?php echo $id_incidencia; ?> | Sistema TI</title>
    <!-- Tipografías Premium -->
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
            background-image: radial-gradient(circle at 10% 10%, rgba(217, 205, 187, 0.4) 0%, transparent 40%),
                              radial-gradient(circle at 90% 90%, rgba(176, 141, 87, 0.12) 0%, transparent 40%);
        }

        /* Barra Superior */
        .navbar-luxury {
            background-color: var(--midnight-navy);
            border-bottom: 1px solid rgba(176, 141, 87, 0.3);
            padding: 16px 30px;
            box-shadow: 0 4px 20px rgba(22, 32, 51, 0.15);
        }

        .navbar-brand-custom {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #FFFFFF !important;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .navbar-brand-custom span {
            color: var(--brushed-brass);
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(217, 205, 187, 0.25);
            padding: 8px 16px;
            border-radius: 50px;
            color: var(--ivory);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-badge strong {
            color: var(--brushed-brass);
        }

        .btn-nav-action {
            background: transparent;
            border: 1px solid var(--warm-beige);
            color: var(--ivory);
            padding: 7px 16px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-nav-action:hover {
            background-color: var(--ivory);
            color: var(--midnight-navy);
            border-color: var(--ivory);
        }

        .btn-logout {
            border-color: rgba(248, 113, 113, 0.4);
            color: #F87171;
        }

        .btn-logout:hover {
            background-color: #F87171;
            color: #FFFFFF;
            border-color: #F87171;
        }

        /* Contenedor Principal */
        .main-container {
            flex: 1;
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .card-detail {
            background: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(22, 32, 51, 0.15);
            border: 1px solid rgba(217, 205, 187, 0.6);
            padding: 45px 50px;
            position: relative;
        }

        .card-detail::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            border-radius: 24px 24px 0 0;
            background: linear-gradient(90deg, var(--deep-navy), var(--brushed-brass));
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .detail-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.3rem;
            font-weight: 700;
            color: var(--midnight-navy);
            margin-bottom: 8px;
        }

        .meta-tags {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .meta-badge {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-cat { background-color: #FAF8F5; color: var(--deep-navy); border: 1px solid var(--warm-beige); }
        .badge-prio { background-color: #FDF2F2; color: #DC2626; border: 1px solid #FCA5A5; }
        .badge-estado { background-color: #F0F4F8; color: var(--deep-navy); border: 1px solid #D1D9E0; }

        .description-box {
            background-color: #FAF8F5;
            border: 1.5px solid var(--warm-beige);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
        }

        .description-box h6 {
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--deep-navy);
            margin-bottom: 10px;
        }

        .description-box p {
            font-size: 0.98rem;
            color: var(--midnight-navy);
            line-height: 1.6;
            margin: 0;
        }

        .ticket-info-sub {
            font-size: 0.82rem;
            color: #6B7280;
            margin-bottom: 35px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        /* Sección de Bitácora y Acciones */
        .section-heading {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--midnight-navy);
            margin-bottom: 16px;
            border-bottom: 1px solid #EFECE6;
            padding-bottom: 8px;
        }

        .form-control-luxury, .form-select-luxury {
            width: 100%;
            padding: 12px 16px;
            font-size: 0.93rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--midnight-navy);
            background-color: #FAF8F5;
            border: 1.5px solid var(--warm-beige);
            border-radius: 12px;
            transition: all 0.25s ease;
        }

        .form-control-luxury:focus, .form-select-luxury:focus {
            outline: none;
            background-color: #FFFFFF;
            border-color: var(--brushed-brass);
            box-shadow: 0 0 0 4px rgba(176, 141, 87, 0.14);
        }

        .btn-luxury {
            padding: 11px 22px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--midnight-navy) 100%);
            color: var(--ivory);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 6px 15px rgba(22, 32, 51, 0.2);
            border-bottom: 2px solid var(--brushed-brass);
            transition: all 0.2s ease;
        }

        .btn-luxury:hover {
            transform: translateY(-1px);
            color: #FFFFFF;
        }

        /* Historial de avances (Bitácora) */
        .timeline-item {
            background-color: #FAF8F5;
            border: 1px solid var(--warm-beige);
            border-left: 4px solid var(--brushed-brass);
            border-radius: 0 12px 12px 0;
            padding: 16px 20px;
            margin-bottom: 14px;
        }

        .timeline-meta {
            font-size: 0.78rem;
            color: #6B7280;
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .timeline-text {
            font-size: 0.92rem;
            color: var(--midnight-navy);
            margin: 0;
        }

        .alert-success-luxury {
            background-color: #ECFDF5;
            border: 1px solid #A7F3D0;
            border-left: 4px solid #059669;
            color: #065F46;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.88rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <!-- Barra Superior -->
    <nav class="navbar navbar-luxury">
        <div class="container-fluid px-4">
            <a href="index.php" class="navbar-brand-custom">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Sistema de <span>Incidencias</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="user-badge">
                    <span>Usuario:</span>
                    <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong>
                </div>
                <a href="admin_incidencias.php" class="btn-nav-action">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                    Regresar a la Cola
                </a>
                <a href="logout.php" class="btn-nav-action btn-logout">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>
                    </svg>
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <!-- Contenedor Principal -->
    <div class="main-container">
        <div class="card-detail">

            <?php if (!empty($mensaje_exito)): ?>
                <div class="alert-success-luxury" role="alert">
                    <?php echo htmlspecialchars($mensaje_exito); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="detail-header">
                <div>
                    <h1 class="detail-title"><?php echo htmlspecialchars($incidencia['titulo']); ?></h1>
                    <div class="ticket-info-sub">
                        <span>Solicitado por: <strong><?php echo htmlspecialchars($incidencia['solicitante_nombre']); ?></strong></span>
                        <span>Fecha de creación: <strong><?php echo $incidencia['fecha_creacion']; ?></strong></span>
                    </div>
                </div>

                <div class="meta-tags">
                    <span class="meta-badge badge-cat"><?php echo htmlspecialchars($incidencia['categoria_nombre']); ?></span>
                    <span class="meta-badge badge-prio">Prioridad: <?php echo htmlspecialchars($incidencia['prioridad']); ?></span>
                    <span class="meta-badge badge-estado">Estado: <?php echo htmlspecialchars($incidencia['estado']); ?></span>
                </div>
            </div>

            <div class="description-box">
                <h6>Descripción detallada del problema:</h6>
                <p><?php echo nl2br(htmlspecialchars($incidencia['descripcion'])); ?></p>
            </div>

            <!-- Panel de Acciones Técnicas y Cambio de Estado -->
            <div class="row mb-5">
                <div class="col-md-6">
                    <div class="p-4 rounded-4" style="background-color: #FAF8F5; border: 1.5px solid var(--warm-beige);">
                        <h6 style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--deep-navy); margin-bottom: 12px;">Cambiar Estado del Ticket</h6>
                        <form method="POST" class="d-flex gap-2">
                            <select name="nuevo_estado" class="form-select-luxury" required>
                                <option value="Abierto" <?php echo ($incidencia['estado'] === 'Abierto') ? 'selected' : ''; ?>>Abierto</option>
                                <option value="En Proceso" <?php echo ($incidencia['estado'] === 'En Proceso') ? 'selected' : ''; ?>>En Proceso</option>
                                <option value="Resuelto" <?php echo ($incidencia['estado'] === 'Resuelto') ? 'selected' : ''; ?>>Resuelto</option>
                                <option value="Cerrado" <?php echo ($incidencia['estado'] === 'Cerrado') ? 'selected' : ''; ?>>Cerrado</option>
                            </select>
                            <button type="submit" class="btn-luxury text-nowrap">Actualizar</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Módulo Bitácora de Seguimiento (RF007) -->
            <h3 class="section-heading">Bitácora de Seguimiento</h3>
            
            <form method="POST" class="mb-4">
                <div class="mb-3">
                    <label for="nota_bitacora" class="form-label" style="font-size: 0.85rem; font-weight: 600; color: var(--midnight-navy);">Agregar nueva nota u observación técnica:</label>
                    <textarea class="form-control-luxury" id="nota_bitacora" name="nota_bitacora" rows="3" placeholder="Escriba el diagnóstico o avance técnico..." required></textarea>
                </div>
                <button type="submit" class="btn-luxury">Registrar Nota en Bitácora</button>
            </form>

            <h5 class="mt-4 mb-3" style="font-size: 1rem; font-weight: 700; color: var(--deep-navy);">Historial de avances:</h5>
            <div>
                <?php if (count($seguimientos) > 0): ?>
                    <?php foreach ($seguimientos as $seg): ?>
                        <div class="timeline-item">
                            <div class="timeline-meta">
                                <span>Técnico: <strong><?php echo htmlspecialchars($seg['tecnico_nombre']); ?></strong></span>
                                <span><?php echo $seg['fecha_comentario']; ?></span>
                            </div>
                            <p class="timeline-text"><?php echo nl2br(htmlspecialchars($seg['comentario'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="font-size: 0.9rem; color: #6B7280; font-style: italic;">Aún no hay notas registradas en la bitácora de este ticket.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>