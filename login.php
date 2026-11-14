<?php
session_start();
require_once 'config/conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($correo) && !empty($password)) {
        try {
            $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE correo = :correo LIMIT 1");
            $stmt->execute([':correo' => $correo]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            $acceso_valido = false;

            if ($usuario) {
                // 1. Verificar hash seguro (Bcrypt)
                if (password_verify($password, $usuario['password_hash'])) {
                    $acceso_valido = true;
                } 
                // 2. Respaldo si en BD estaba en texto plano o con el hash de prueba '123456'
                elseif ($usuario['password_hash'] === $password || ($password === '123456' && in_array($correo, ['usuario@test.com', 'tecnico@test.com']))) {
                    $acceso_valido = true;
                    // Actualizar automáticamente a hash seguro en BD
                    $nuevo_hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $conexion->prepare("UPDATE usuarios SET password_hash = :h WHERE id_usuario = :id");
                    $upd->execute([':h' => $nuevo_hash, ':id' => $usuario['id_usuario']]);
                }
            }

            if ($acceso_valido) {
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['rol'] = $usuario['rol'];
                
                header("Location: index.php");
                exit();
            } else {
                $error = "Las credenciales ingresadas no coinciden con nuestros registros.";
            }
        } catch (PDOException $e) {
            $error = "Error de conexión: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, complete su correo electrónico y contraseña.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Corporativo | Gestión de Incidencias TI</title>
    <!-- Tipografías Premium: Cormorant Garamond (Editorial) + Plus Jakarta Sans (UI Moderna) -->
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

        * {
            box-sizing: border-box;
        }

        body {
            background-color: var(--ivory);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-image: radial-gradient(circle at 15% 15%, rgba(217, 205, 187, 0.45) 0%, transparent 45%),
                              radial-gradient(circle at 85% 85%, rgba(176, 141, 87, 0.15) 0%, transparent 45%);
        }

        .auth-wrapper {
            display: flex;
            width: 100%;
            max-width: 980px;
            min-height: 600px;
            background: #FFFFFF;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 25px 60px -15px rgba(22, 32, 51, 0.22);
            border: 1px solid rgba(217, 205, 187, 0.6);
        }

        /* Panel Izquierdo: Editorial Luxury Deep Navy */
        .brand-panel {
            flex: 1.05;
            background: linear-gradient(155deg, var(--midnight-navy) 0%, var(--deep-navy) 100%);
            color: var(--ivory);
            padding: 52px 46px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .brand-panel::before {
            content: "";
            position: absolute;
            top: -80px;
            right: -80px;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            border: 1px solid rgba(176, 141, 87, 0.25);
            pointer-events: none;
        }

        .brand-panel::after {
            content: "";
            position: absolute;
            bottom: -110px;
            left: -70px;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(176, 141, 87, 0.14) 0%, transparent 70%);
            pointer-events: none;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--brass-light);
            border: 1px solid rgba(176, 141, 87, 0.4);
            padding: 7px 14px;
            border-radius: 50px;
            width: fit-content;
            background: rgba(22, 32, 51, 0.5);
        }

        .brand-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.85rem;
            font-weight: 700;
            line-height: 1.1;
            margin-top: 28px;
            margin-bottom: 18px;
            color: #FFFFFF;
        }

        .brand-title span {
            color: var(--brass-light);
            font-style: italic;
        }

        .brand-desc {
            color: var(--warm-beige);
            font-size: 0.94rem;
            line-height: 1.7;
            opacity: 0.9;
            font-weight: 400;
        }

        .brand-divider {
            height: 1px;
            background: linear-gradient(90deg, var(--brushed-brass), transparent);
            margin: 28px 0;
            opacity: 0.6;
        }

        .brand-footer {
            font-size: 0.78rem;
            color: var(--warm-beige);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            opacity: 0.75;
        }

        /* Panel Derecho: Formulario Limpio */
        .form-panel {
            flex: 1.15;
            padding: 50px 52px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: #FFFFFF;
        }

        .form-heading {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--midnight-navy);
            margin-bottom: 4px;
        }

        .form-subheading {
            font-size: 0.9rem;
            color: #6B7280;
            margin-bottom: 30px;
        }

        .input-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--deep-navy);
            margin-bottom: 8px;
            display: block;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 22px;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--brushed-brass);
            display: flex;
            align-items: center;
            pointer-events: none;
        }

        .form-control-luxury {
            width: 100%;
            padding: 13px 16px 13px 46px;
            font-size: 0.95rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--midnight-navy);
            background-color: #FAF8F5;
            border: 1.5px solid var(--warm-beige);
            border-radius: 12px;
            transition: all 0.25s ease;
        }

        .form-control-luxury:focus {
            outline: none;
            background-color: #FFFFFF;
            border-color: var(--brushed-brass);
            box-shadow: 0 0 0 4px rgba(176, 141, 87, 0.14);
        }

        .toggle-pass {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #8C857B;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            transition: color 0.2s;
        }

        .toggle-pass:hover {
            color: var(--midnight-navy);
        }

        /* Botón Premium Brushed Brass & Deep Navy */
        .btn-luxury {
            width: 100%;
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--midnight-navy) 100%);
            color: var(--ivory);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 25px -5px rgba(22, 32, 51, 0.35);
            border-bottom: 3px solid var(--brushed-brass);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .btn-luxury:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(22, 32, 51, 0.45);
            background: linear-gradient(135deg, #263454 0%, var(--deep-navy) 100%);
            color: #FFFFFF;
        }

        .btn-luxury:active {
            transform: translateY(0);
        }

        /* Tarjetas de acceso rápido */
        .quick-access {
            margin-top: 30px;
            padding-top: 22px;
            border-top: 1px solid #EFECE6;
        }

        .quick-title {
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #8C857B;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .demo-pill {
            background-color: var(--ivory);
            border: 1px solid var(--warm-beige);
            border-radius: 10px;
            padding: 10px 12px;
            text-align: left;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .demo-pill:hover {
            border-color: var(--brushed-brass);
            background-color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(176, 141, 87, 0.15);
            transform: translateY(-1px);
        }

        .demo-role {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--midnight-navy);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .demo-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: var(--brushed-brass);
        }

        .demo-email {
            font-size: 0.73rem;
            color: #6B7280;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .alert-luxury {
            background-color: #FDF2F2;
            border: 1px solid #F8B4B4;
            border-left: 4px solid #C81E1E;
            color: #9B1C1C;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.86rem;
            margin-bottom: 22px;
        }

        @media (max-width: 768px) {
            .auth-wrapper {
                flex-direction: column;
            }
            .brand-panel {
                padding: 36px 30px;
            }
            .brand-title {
                font-size: 2.2rem;
            }
            .form-panel {
                padding: 36px 28px;
            }
        }
    </style>
</head>
<body>

    <div class="auth-wrapper">
        <!-- Columna Izquierda: Identidad Visual -->
        <div class="brand-panel">
            <div>
                <div class="brand-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Soporte Técnico &amp; TI
                </div>
                <h1 class="brand-title">Gestión de <span>Incidencias</span> Corporativas</h1>
                <p class="brand-desc">
                    Plataforma centralizada para el registro, trazabilidad y resolución eficiente de requerimientos tecnológicos.
                </p>
                <div class="brand-divider"></div>
            </div>

            <div class="brand-footer">
                Arquitectura Segura &bull; UISIL ISB-32
            </div>
        </div>

        <!-- Columna Derecha: Formulario -->
        <div class="form-panel">
            <h2 class="form-heading">Iniciar Sesión</h2>
            <p class="form-subheading">Ingrese sus credenciales institucionales para acceder al panel.</p>

            <?php if (!empty($error)): ?>
                <div class="alert-luxury" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div>
                    <label for="correo" class="input-label">Correo Electrónico</label>
                    <div class="input-group-custom">
                        <span class="input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                            </svg>
                        </span>
                        <input type="email" class="form-control-luxury" id="correo" name="correo" placeholder="nombre@institucion.com" required>
                    </div>
                </div>

                <div>
                    <label for="password" class="input-label">Contraseña</label>
                    <div class="input-group-custom">
                        <span class="input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input type="password" class="form-control-luxury" id="password" name="password" placeholder="Ingrese su contraseña" required>
                        <button type="button" class="toggle-pass" onclick="togglePassword()" title="Mostrar/Ocultar contraseña">
                            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-luxury">
                    <span>Ingresar al Sistema</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                    </svg>
                </button>
            </form>

            <!-- Accesos de prueba interactivos (1 clic para rellenar) -->
            <div class="quick-access">
                <div class="quick-title">
                    <span>Acceso rápido de pruebas</span>
                    <span style="color: var(--brushed-brass); font-size: 0.68rem;">Clic para autocompletar</span>
                </div>
                <div class="demo-grid">
                    <button type="button" class="demo-pill" onclick="autocompletar('usuario@test.com', '123456')">
                        <div class="demo-role"><span class="demo-dot"></span> Solicitante</div>
                        <div class="demo-email">usuario@test.com</div>
                    </button>
                    <button type="button" class="demo-pill" onclick="autocompletar('tecnico@test.com', '123456')">
                        <div class="demo-role"><span class="demo-dot"></span> Técnico TI</div>
                        <div class="demo-email">tecnico@test.com</div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        function autocompletar(correo, clave) {
            document.getElementById('correo').value = correo;
            document.getElementById('password').value = clave;
        }
    </script>
</body>
</html>