<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$rol_raw = $_SESSION['rol'] ?? 'solicitante';
$rol_normalizado = strtolower(trim((string) $rol_raw));
$es_personal_soporte = in_array($rol_normalizado, ['tecnico', 'técnico', 'administrador', 'admin'], true);
$es_admin = in_array($rol_normalizado, ['administrador', 'admin'], true);
$etiqueta_rol = $es_personal_soporte ? 'Técnico / Admin' : 'Solicitante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal | Sistema de Incidencias TI</title>
    <!-- Tipografías Premium: Cormorant Garamond + Plus Jakarta Sans -->
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

        /* Barra de Navegación Estilo Luxury */
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

        .btn-logout {
            background: transparent;
            border: 1px solid rgba(248, 113, 113, 0.4);
            color: #F87171;
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

        .btn-logout:hover {
            background-color: #F87171;
            color: #FFFFFF;
            border-color: #F87171;
        }

        /* Contenedor Principal */
        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .dashboard-card {
            background: #FFFFFF;
            width: 100%;
            max-width: 620px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(22, 32, 51, 0.15);
            border: 1px solid rgba(217, 205, 187, 0.6);
            padding: 50px 45px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--deep-navy), var(--brushed-brass));
        }

        .welcome-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.6rem;
            font-weight: 700;
            color: var(--midnight-navy);
            margin-bottom: 10px;
        }

        .welcome-title span {
            color: var(--brushed-brass);
            font-style: italic;
        }

        .welcome-subtitle {
            font-size: 0.95rem;
            color: #6B7280;
            margin-bottom: 36px;
        }

        /* Botones de Opciones */
        .action-stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .btn-action {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1.5px solid transparent;
        }

        .btn-primary-action {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--midnight-navy) 100%);
            color: var(--ivory);
            box-shadow: 0 8px 20px rgba(22, 32, 51, 0.2);
            border-bottom: 3px solid var(--brushed-brass);
        }

        .btn-primary-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(22, 32, 51, 0.3);
            color: #FFFFFF;
        }

        .btn-secondary-action {
            background-color: #FAF8F5;
            color: var(--midnight-navy);
            border-color: var(--warm-beige);
        }

        .btn-secondary-action:hover {
            background-color: #FFFFFF;
            border-color: var(--brushed-brass);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(176, 141, 87, 0.12);
            color: var(--midnight-navy);
        }

        .btn-tech-action {
            background-color: #1F2A44;
            color: var(--brass-light);
            border-color: rgba(176, 141, 87, 0.4);
        }

        .btn-tech-action:hover {
            background-color: #162033;
            color: #FFFFFF;
            border-color: var(--brushed-brass);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(22, 32, 51, 0.25);
        }

        .action-content {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .action-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(176, 141, 87, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brushed-brass);
        }

        .section-divider {
            margin: 28px 0;
            border: 0;
            height: 1px;
            background-color: #EFECE6;
        }

        .footer-note {
            margin-top: 30px;
            font-size: 0.78rem;
            color: #9CA3AF;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    <!-- Barra Superior -->
    <nav class="navbar navbar-luxury">
        <div class="container-fluid px-4">
            <span class="navbar-brand-custom">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Sistema de <span>Incidencias</span>
            </span>
            <div class="d-flex align-items-center gap-3">
                <div class="user-badge">
                    <span><?php echo htmlspecialchars($etiqueta_rol); ?>:</span>
                    <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong>
                </div>
                <a href="logout.php" class="btn-logout">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>
                    </svg>
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <!-- Contenido Central -->
    <div class="main-container">
        <div class="dashboard-card">
            <h1 class="welcome-title">¡Bienvenido, <span><?php echo htmlspecialchars(explode(' ', trim($nombre_usuario))[0]); ?></span>!</h1>
            <p class="welcome-subtitle">Seleccione una de las opciones disponibles para gestionar sus incidencias en el sistema.</p>

            <div class="action-stack">
                <!-- Opción 1: Crear Incidencia -->
                <a href="crear_incidencia.php" class="btn-action btn-primary-action">
                    <div class="action-content">
                        <div class="action-icon" style="background: rgba(244, 241, 235, 0.15); color: #FFFFFF;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/>
                            </svg>
                        </div>
                        <span style="font-weight: 600; font-size: 1rem;">Registrar Nueva Incidencia</span>
                    </div>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m9 18 6-6-6-6"/>
                    </svg>
                </a>

                <!-- Opción 2: Ver Historial -->
                <a href="mis_incidencias.php" class="btn-action btn-secondary-action">
                    <div class="action-content">
                        <div class="action-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/>
                            </svg>
                        </div>
                        <span>Ver Mis Incidencias Registradas</span>
                    </div>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--brushed-brass);">
                        <path d="m9 18 6-6-6-6"/>
                    </svg>
                </a>

                <?php if ($es_personal_soporte): ?>
                    <hr class="section-divider">

                    <!-- Panel de Soporte / Técnico -->
                    <div class="text-start" style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #8C857B; margin-bottom: 8px;">
                        Panel de Operaciones Técnicas
                    </div>

                    <a href="admin_incidencias.php" class="btn-action btn-tech-action">
                        <div class="action-content">
                            <div class="action-icon" style="background: rgba(176, 141, 87, 0.2); color: var(--brass-light);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                </svg>
                            </div>
                            <span>Ir a la Cola General de Tickets (Técnico)</span>
                        </div>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--brass-light);">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                    </a>

                    <?php if ($es_admin): ?>
                        <a href="admin_usuarios.php" class="btn-action btn-secondary-action">
                            <div class="action-content">
                                <div class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                                <span>Administrar Cuentas de Usuario</span>
                            </div>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--brushed-brass);">
                                <path d="m9 18 6-6-6-6"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="footer-note">
                Universidad Internacional San Isidro Labrador &bull; ISB-32
            </div>
        </div>
    </div>

</body>
</html>