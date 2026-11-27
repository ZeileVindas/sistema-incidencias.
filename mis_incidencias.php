<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/conexion.php';

$id_usuario = (int) $_SESSION['id_usuario'];
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$exito = isset($_GET['exito']) && $_GET['exito'] === '1';

try {
    $stmt = $conexion->prepare("
        SELECT i.*, c.nombre AS categoria_nombre
        FROM incidencias i
        JOIN categorias c ON i.id_categoria = c.id_categoria
        WHERE i.id_usuario = :id_usuario
        ORDER BY i.fecha_creacion DESC
    ");
    $stmt->execute([':id_usuario' => $id_usuario]);
    $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error en mis_incidencias.php: " . $e->getMessage());
    $incidencias = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Mis Incidencias | Sistema TI</title>
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
            max-width: 1150px;
            width: 100%;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .card-history {
            background: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(22, 32, 51, 0.15);
            border: 1px solid rgba(217, 205, 187, 0.6);
            overflow: hidden;
            position: relative;
        }

        .card-history::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--deep-navy), var(--brushed-brass));
        }

        .history-header {
            padding: 35px 40px 25px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #EFECE6;
            flex-wrap: wrap;
            gap: 15px;
        }

        .history-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--midnight-navy);
            margin-bottom: 2px;
        }

        .history-subtitle {
            font-size: 0.9rem;
            color: #6B7280;
            margin: 0;
        }

        .btn-new-ticket {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--midnight-navy) 100%);
            color: var(--ivory);
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(22, 32, 51, 0.2);
            border-bottom: 2px solid var(--brushed-brass);
            transition: all 0.2s ease;
        }

        .btn-new-ticket:hover {
            transform: translateY(-1px);
            color: #FFFFFF;
            background: linear-gradient(135deg, #263454 0%, var(--deep-navy) 100%);
        }

        .alert-success-luxury {
            background-color: #ECFDF5;
            border: 1px solid #A7F3D0;
            border-left: 4px solid #059669;
            color: #065F46;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.88rem;
            margin: 20px 40px 0 40px;
        }

        /* Tabla de Lujo */
        .table-responsive {
            padding: 0 10px 10px 10px;
        }

        .table-luxury {
            margin-bottom: 0;
            vertical-align: middle;
        }

        .table-luxury th {
            background-color: #FAF8F5;
            color: var(--deep-navy);
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            padding: 16px 20px;
            border-bottom: 1.5px solid var(--warm-beige);
        }

        .table-luxury td {
            padding: 18px 20px;
            font-size: 0.92rem;
            color: var(--midnight-navy);
            border-bottom: 1px solid #F3F0EC;
            background-color: #FFFFFF;
        }

        .table-luxury tbody tr {
            transition: all 0.15s ease;
        }

        .table-luxury tbody tr:hover td {
            background-color: #FAF8F5;
        }

        /* Insignias (Badges) */
        .badge-prio {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .prio-alta { background-color: #FDF2F2; color: #DC2626; border: 1px solid #FCA5A5; }
        .prio-media { background-color: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }
        .prio-baja { background-color: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }

        .badge-estado {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            background-color: #F0F4F8;
            color: var(--deep-navy);
            border: 1px solid #D1D9E0;
        }

        .ticket-title-link {
            color: var(--midnight-navy);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.15s;
        }

        .ticket-title-link:hover {
            color: var(--brushed-brass);
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6B7280;
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
                <a href="index.php" class="btn-nav-action">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                    Volver al Panel
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

    <!-- Contenido Principal -->
    <div class="main-container">
        <div class="card-history">
            <div class="history-header">
                <div>
                    <h1 class="history-title">Historial de Mis Incidencias</h1>
                    <p class="history-subtitle">Consulta el estado actual y el seguimiento de tus requerimientos de soporte.</p>
                </div>
                <a href="crear_incidencia.php" class="btn-new-ticket">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/>
                    </svg>
                    Nueva Incidencia
                </a>
            </div>

            <?php if ($exito): ?>
                <div class="alert-success-luxury" role="alert">
                    La incidencia fue registrada exitosamente con estado inicial <strong>Abierto</strong>.
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <?php if (count($incidencias) > 0): ?>
                    <table class="table table-luxury">
                        <thead>
                            <tr>
                                <th style="width: 80px;"># ID</th>
                                <th>Título del Problema</th>
                                <th style="width: 170px;">Categoría</th>
                                <th style="width: 130px;">Prioridad</th>
                                <th style="width: 140px;">Estado</th>
                                <th style="width: 180px;">Fecha de Creación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidencias as $inc): ?>
                                <tr>
                                    <td><strong>#<?php echo (int) $inc['id_incidencia']; ?></strong></td>
                                    <td>
                                        <a href="ver_incidencia.php?id=<?php echo (int) $inc['id_incidencia']; ?>" class="ticket-title-link">
                                            <?php echo htmlspecialchars($inc['titulo']); ?>
                                        </a>
                                    </td>
                                    <td><span style="font-weight: 500; color: #4B5563;"><?php echo htmlspecialchars($inc['categoria_nombre']); ?></span></td>
                                    <td>
                                        <?php
                                            $prio = $inc['prioridad'];
                                            $prioClass = ($prio === 'Alta') ? 'prio-alta' : (($prio === 'Baja') ? 'prio-baja' : 'prio-media');
                                        ?>
                                        <span class="badge-prio <?php echo $prioClass; ?>">
                                            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: currentColor;"></span>
                                            <?php echo htmlspecialchars($prio); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-estado"><?php echo htmlspecialchars($inc['estado']); ?></span>
                                    </td>
                                    <td style="font-size: 0.85rem; color: #6B7280;"><?php echo htmlspecialchars($inc['fecha_creacion']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: var(--warm-beige); margin-bottom: 12px;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/>
                        </svg>
                        <p style="font-weight: 600; color: var(--midnight-navy); margin-bottom: 4px;">No tienes incidencias registradas</p>
                        <p style="font-size: 0.85rem; margin-bottom: 20px;">Cuando registres un ticket de soporte, aparecerá listado aquí.</p>
                        <a href="crear_incidencia.php" class="btn-new-ticket">Registrar mi primer ticket</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>