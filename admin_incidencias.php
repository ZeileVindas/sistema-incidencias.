<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/conexion.php';

$nombre_usuario = $_SESSION['nombre'] ?? 'Técnico';
$filtro_estado = $_GET['estado'] ?? 'todos';

try {
    $sql = "
        SELECT i.*, u.nombre AS solicitante_nombre, c.nombre AS categoria_nombre 
        FROM incidencias i 
        JOIN usuarios u ON i.id_usuario = u.id_usuario 
        JOIN categorias c ON i.id_categoria = c.id_categoria 
    ";

    if ($filtro_estado !== 'todos') {
        $sql .= " WHERE i.estado = :estado";
    }

    $sql .= " ORDER BY i.fecha_creacion DESC";

    $stmt = $conexion->prepare($sql);
    if ($filtro_estado !== 'todos') {
        $stmt->execute([':estado' => ucfirst($filtro_estado)]);
    } else {
        $stmt->execute();
    }
    $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $incidencias = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cola General de Tickets | Panel Técnico</title>
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
            max-width: 1250px;
            width: 100%;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .card-admin {
            background: #FFFFFF;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(22, 32, 51, 0.15);
            border: 1px solid rgba(217, 205, 187, 0.6);
            overflow: hidden;
            position: relative;
        }

        .card-admin::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--deep-navy), var(--brushed-brass));
        }

        .admin-header {
            padding: 35px 40px 25px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #EFECE6;
            flex-wrap: wrap;
            gap: 20px;
        }

        .admin-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--midnight-navy);
            margin-bottom: 2px;
        }

        .admin-subtitle {
            font-size: 0.9rem;
            color: #6B7280;
            margin: 0;
        }

        /* Filtros de Estado */
        .filter-group {
            display: flex;
            gap: 8px;
            background-color: #FAF8F5;
            padding: 6px;
            border-radius: 12px;
            border: 1px solid var(--warm-beige);
        }

        .filter-btn {
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--midnight-navy);
            transition: all 0.2s ease;
            border: none;
            background: transparent;
        }

        .filter-btn:hover {
            color: var(--brushed-brass);
        }

        .filter-btn.active {
            background: var(--deep-navy);
            color: var(--ivory);
            box-shadow: 0 4px 12px rgba(22, 32, 51, 0.2);
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

        /* Insignias */
        .badge-prio {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
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
            white-space: nowrap;
            display: inline-block;
        }

        .btn-atender {
            background-color: var(--ivory);
            color: var(--deep-navy);
            border: 1px solid var(--warm-beige);
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-atender:hover {
            background-color: var(--deep-navy);
            color: var(--ivory);
            border-color: var(--deep-navy);
            box-shadow: 0 4px 12px rgba(31, 42, 68, 0.2);
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
                    <span>Técnico/Admin:</span>
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
        <div class="card-admin">
            <div class="admin-header">
                <div>
                    <h1 class="admin-title">Cola General de Tickets</h1>
                    <p class="admin-subtitle">Gestión centralizada de requerimientos técnicos e incidencias reportadas.</p>
                </div>

                <!-- Botones de Filtro -->
                <div class="filter-group">
                    <a href="admin_incidencias.php?estado=todos" class="filter-btn <?php echo ($filtro_estado === 'todos') ? 'active' : ''; ?>">Todos</a>
                    <a href="admin_incidencias.php?estado=abierto" class="filter-btn <?php echo ($filtro_estado === 'abierto') ? 'active' : ''; ?>">Abiertos</a>
                    <a href="admin_incidencias.php?estado=en proceso" class="filter-btn <?php echo ($filtro_estado === 'en proceso') ? 'active' : ''; ?>">En Proceso</a>
                    <a href="admin_incidencias.php?estado=resuelto" class="filter-btn <?php echo ($filtro_estado === 'resuelto') ? 'active' : ''; ?>">Resueltos</a>
                </div>
            </div>

            <div class="table-responsive">
                <?php if (count($incidencias) > 0): ?>
                    <table class="table table-luxury">
                        <thead>
                            <tr>
                                <th style="width: 70px;"># ID</th>
                                <th>Solicitante</th>
                                <th>Título del Problema</th>
                                <th style="width: 150px;">Categoría</th>
                                <th style="width: 120px;">Prioridad</th>
                                <th style="width: 130px;">Estado</th>
                                <th style="width: 160px;">Fecha</th>
                                <th style="width: 130px; text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidencias as $inc): ?>
                                <tr>
                                    <td><strong>#<?php echo $inc['id_incidencia']; ?></strong></td>
                                    <td><span style="font-weight: 600; color: var(--deep-navy);"><?php echo htmlspecialchars($inc['solicitante_nombre']); ?></span></td>
                                    <td>
                                        <a href="ver_incidencia.php?id=<?php echo $inc['id_incidencia']; ?>" style="color: var(--midnight-navy); font-weight: 600; text-decoration: none;">
                                            <?php echo htmlspecialchars($inc['titulo']); ?>
                                        </a>
                                    </td>
                                    <td><span style="color: #4B5563;"><?php echo htmlspecialchars($inc['categoria_nombre']); ?></span></td>
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
                                    <td style="font-size: 0.84rem; color: #6B7280;"><?php echo $inc['fecha_creacion']; ?></td>
                                    <td style="text-align: center;">
                                        <a href="ver_incidencia.php?id=<?php echo $inc['id_incidencia']; ?>" class="btn-atender">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                            </svg>
                                            Atender
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: var(--warm-beige); margin-bottom: 12px;">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>
                        </svg>
                        <p style="font-weight: 600; color: var(--midnight-navy); margin-bottom: 4px;">No hay incidencias en esta sección</p>
                        <p style="font-size: 0.85rem; margin: 0;">Los tickets reportados por los usuarios aparecerán listados aquí en tiempo real.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>