<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/conexion.php';

$error = '';

// Obtener categorías limpias (sin duplicados ni filas vacías)
try {
    $stmt_cat = $conexion->query("SELECT MIN(id_categoria) AS id_categoria, TRIM(nombre) AS nombre 
                                  FROM categorias 
                                  WHERE nombre IS NOT NULL AND TRIM(nombre) != '' 
                                  GROUP BY TRIM(nombre) 
                                  ORDER BY id_categoria ASC");
    $categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categorias = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $id_categoria = trim($_POST['id_categoria'] ?? '');
    $prioridad = trim($_POST['prioridad'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $id_usuario = $_SESSION['id_usuario'];

    if (!empty($titulo) && !empty($id_categoria) && !empty($prioridad) && !empty($descripcion)) {
        try {
            $stmt = $conexion->prepare("INSERT INTO incidencias (id_usuario, id_categoria, titulo, descripcion, prioridad, estado, fecha_creacion) 
                                        VALUES (:id_usuario, :id_categoria, :titulo, :descripcion, :prioridad, 'Abierto', NOW())");
            $stmt->execute([
                ':id_usuario' => $id_usuario,
                ':id_categoria' => $id_categoria,
                ':titulo' => $titulo,
                ':descripcion' => $descripcion,
                ':prioridad' => $prioridad
            ]);
            
            header("Location: mis_incidencias.php?exito=1");
            exit();
        } catch (PDOException $e) {
            $error = "Error al registrar la incidencia: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, complete todos los campos requeridos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Nueva Incidencia | Sistema TI</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .form-card {
            background: #FFFFFF;
            width: 100%;
            max-width: 680px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(22, 32, 51, 0.15);
            border: 1px solid rgba(217, 205, 187, 0.6);
            padding: 45px 50px;
            position: relative;
        }

        .form-card-header-bar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            border-radius: 24px 24px 0 0;
            background: linear-gradient(90deg, var(--deep-navy), var(--brushed-brass));
        }

        .form-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.3rem;
            font-weight: 700;
            color: var(--midnight-navy);
            margin-bottom: 6px;
        }

        .form-subtitle {
            font-size: 0.9rem;
            color: #6B7280;
            margin-bottom: 28px;
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

        .form-control-luxury, .form-select-luxury {
            width: 100%;
            padding: 13px 16px;
            font-size: 0.93rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--midnight-navy);
            background-color: #FAF8F5;
            border: 1.5px solid var(--warm-beige);
            border-radius: 12px;
            transition: all 0.25s ease;
        }

        .form-select-luxury {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23B08D57' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
            cursor: pointer;
        }

        .form-control-luxury:focus, .form-select-luxury:focus {
            outline: none;
            background-color: #FFFFFF;
            border-color: var(--brushed-brass);
            box-shadow: 0 0 0 4px rgba(176, 141, 87, 0.14);
        }

        /* Botón Guardar */
        .btn-luxury-submit {
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
            margin-top: 10px;
        }

        .btn-luxury-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(22, 32, 51, 0.45);
            background: linear-gradient(135deg, #263454 0%, var(--deep-navy) 100%);
            color: #FFFFFF;
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
                    <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></strong>
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

    <!-- Contenido del Formulario -->
    <div class="main-container">
        <div class="form-card">
            <div class="form-card-header-bar"></div>
            <h1 class="form-title">Registrar Nueva Incidencia</h1>
            <p class="form-subtitle">Complete los campos detallados para abrir un nuevo ticket de soporte tecnológico.</p>

            <?php if (!empty($error)): ?>
                <div class="alert-luxury" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="titulo" class="input-label">Título del Problema</label>
                    <input type="text" class="form-control-luxury" id="titulo" name="titulo" placeholder="Ej. No enciende la impresora de red" required>
                </div>

                <div class="row">
                    <!-- Categoría -->
                    <div class="col-md-7 mb-3">
                        <label for="id_categoria" class="input-label">Categoría</label>
                        <select name="id_categoria" id="id_categoria" class="form-select-luxury" required>
                            <option value="" disabled selected>Seleccione una categoría...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Prioridad -->
                    <div class="col-md-5 mb-3">
                        <label for="prioridad" class="input-label">Prioridad</label>
                        <select name="prioridad" id="prioridad" class="form-select-luxury" required>
                            <option value="Baja">Baja</option>
                            <option value="Media" selected>Media</option>
                            <option value="Alta">Alta</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="descripcion" class="input-label">Descripción Detallada</label>
                    <textarea class="form-control-luxury" id="descripcion" name="descripcion" rows="4" placeholder="Describa con detalle el inconveniente presentado..." required></textarea>
                </div>

                <button type="submit" class="btn-luxury-submit">
                    <span>Guardar e Iniciar Ticket</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

</body>
</html>