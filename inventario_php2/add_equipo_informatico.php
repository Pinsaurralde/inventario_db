<?php
// add_equipo_informatico.php

// Definir el título de la página
$page_title = "Añadir Nuevo Equipo Informático - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para añadir equipos informáticos.";
    header("Location: login.php");
    exit();
}

// =============================================================
// NUEVO: Obtener los tipos de equipo desde la base de datos
// Este bloque debe ir antes de que se genere el HTML del formulario.
$tipos_equipo_db = [];
try {
    $stmt_tipos = $pdo->query("SELECT id, nombre_tipo FROM tipos_equipo ORDER BY nombre_tipo ASC");
    $tipos_equipo_db = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar tipos de equipo en add_equipo_informatico: " . $e->getMessage());
    $_SESSION['flash_error'] = ($_SESSION['flash_error'] ?? '') . "<br>Error al cargar los tipos de equipo desde la base de datos.";
    // Opcional: si la carga de tipos es crítica y no quieres que el formulario se muestre sin ellos,
    // puedes redirigir aquí o salir. Para este caso, solo mostramos un error y el formulario podría
    // mostrarse con la lista vacía o incompleta si la carga falla.
}
// =============================================================

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y sanear los datos del formulario
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
    $tipo = filter_input(INPUT_POST, 'tipo', FILTER_SANITIZE_STRING);
    $marca = filter_input(INPUT_POST, 'marca', FILTER_SANITIZE_STRING);
    $modelo = filter_input(INPUT_POST, 'modelo', FILTER_SANITIZE_STRING);
    $num_serie = filter_input(INPUT_POST, 'num_serie', FILTER_SANITIZE_STRING);
    $patrimonio = filter_input(INPUT_POST, 'patrimonio', FILTER_SANITIZE_STRING);
    $procesador = filter_input(INPUT_POST, 'procesador', FILTER_SANITIZE_STRING);
    $ram = filter_input(INPUT_POST, 'ram', FILTER_SANITIZE_STRING);
    $almacenamiento = filter_input(INPUT_POST, 'almacenamiento', FILTER_SANITIZE_STRING);
    $SO = filter_input(INPUT_POST, 'SO', FILTER_SANITIZE_STRING);
    $usuario_asignado = filter_input(INPUT_POST, 'usuario_asignado', FILTER_SANITIZE_STRING);
    $estado = filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_STRING);
    $ubicacion = filter_input(INPUT_POST, 'ubicacion', FILTER_SANITIZE_STRING);
    $fecha_asignacion = filter_input(INPUT_POST, 'fecha_asignacion', FILTER_SANITIZE_STRING); // Se valida más abajo
    $observaciones = filter_input(INPUT_POST, 'observaciones', FILTER_SANITIZE_STRING);

    // Validaciones básicas
    $errors = [];
    if (empty($nombre)) {
        $errors[] = "El campo 'Nombre' es obligatorio.";
    }
    if (empty($tipo)) {
        $errors[] = "El campo 'Tipo' es obligatorio.";
    }
    if (empty($marca)) {
        $errors[] = "El campo 'Marca' es obligatorio.";
    }
    if (empty($modelo)) {
        $errors[] = "El campo 'Modelo' es obligatorio.";
    }
    if (empty($num_serie)) {
        $errors[] = "El campo 'Número de Serie' es obligatorio.";
    }
    if (empty($estado)) {
        $errors[] = "El campo 'Estado' es obligatorio.";
    }

    // Validar formato de fecha
    if (!empty($fecha_asignacion) && !strtotime($fecha_asignacion)) {
        $errors[] = "El formato de la 'Fecha de Asignación' no es válido.";
        $fecha_asignacion = NULL; // Asignar NULL si es inválido
    } else if (empty($fecha_asignacion)) {
        $fecha_asignacion = NULL; // Permite que sea NULL en la base de datos
    }

    if (empty($errors)) {
        try {
            // Verificar si el número de serie ya existe (es UNIQUE)
            $stmt_check_serie = $pdo->prepare("SELECT COUNT(*) FROM equipos_informaticos WHERE num_serie = ?");
            $stmt_check_serie->execute([$num_serie]);
            if ($stmt_check_serie->fetchColumn() > 0) {
                $errors[] = "El número de serie '$num_serie' ya existe en la base de datos.";
            }

            // Verificar si el patrimonio ya existe (es UNIQUE y NO NULO)
            if (!empty($patrimonio)) {
                $stmt_check_patrimonio = $pdo->prepare("SELECT COUNT(*) FROM equipos_informaticos WHERE patrimonio = ?");
                $stmt_check_patrimonio->execute([$patrimonio]);
                if ($stmt_check_patrimonio->fetchColumn() > 0) {
                    $errors[] = "El número de patrimonio '$patrimonio' ya existe en la base de datos.";
                }
            }

            if (empty($errors)) {
                // Preparar la consulta SQL para insertar los datos
                $stmt = $pdo->prepare(
                    "INSERT INTO equipos_informaticos (
                        nombre, tipo, marca, modelo, num_serie, patrimonio, procesador, ram,
                        almacenamiento, SO, usuario_asignado, estado, ubicacion, fecha_asignacion, observaciones
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )"
                );

                // Ejecutar la consulta
                $stmt->execute([
                    $nombre, $tipo, $marca, $modelo, $num_serie, $patrimonio, $procesador, $ram,
                    $almacenamiento, $SO, $usuario_asignado, $estado, $ubicacion, $fecha_asignacion, $observaciones
                ]);

                $new_equipo_id = $pdo->lastInsertId(); // Obtener el ID del nuevo equipo

                // --- REGISTRAR MOVIMIENTO INICIAL EN EL HISTORIAL ---
                try {
                    $stmt_historial = $pdo->prepare(
                        "INSERT INTO historial_equipos (equipo_id, tipo_movimiento, detalles, usuario_id)
                         VALUES (?, ?, ?, ?)"
                    );
                    $detalles_historial = "Equipo agregado al inventario. Tipo: $tipo, Marca: $marca, Modelo: $modelo, Nro. Serie: $num_serie.";
                    // Asegúrate de que $_SESSION['user_id'] esté realmente disponible
                    $usuario_que_agrega = $_SESSION['user_id'] ?? null; 

                    $stmt_historial->execute([$new_equipo_id, 'Agregado', $detalles_historial, $usuario_que_agrega]);

                } catch (PDOException $e) {
                    error_log("Error al insertar historial en add_equipo_informatico: " . $e->getMessage());
                    $_SESSION['flash_error'] = ($_SESSION['flash_error'] ?? '') . "<br>Error al registrar historial: " . $e->getMessage();
                }
                // --- FIN REGISTRO HISTORIAL ---

                // Establecer un mensaje de éxito y redirigir
                $_SESSION['flash_message'] = "Equipo informático añadido exitosamente.";
                header("Location: equipos_informaticos.php");
                exit();
            }
        } catch (PDOException $e) {
            // Capturar errores de base de datos
            $errors[] = "Error de base de datos: " . $e->getMessage();
            error_log("Error al añadir equipo informático: " . $e->getMessage());
        }
    }

    // Si hay errores, guardarlos en la sesión para mostrarlos
    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode("<br>", $errors);
    }
    // Redirigir de nuevo al formulario para mostrar errores si los hay o datos rellenados
    header("Location: add_equipo_informatico.php");
    exit();
}
?>

<h1 class="page-title">Añadir Nuevo Equipo Informático</h1>

<div class="form-container">
    <form action="add_equipo_informatico.php" method="POST">
        <div class="form-group">
            <label for="nombre">Nombre del Equipo: <span class="required">*</span></label>
            <input type="text" id="nombre" name="nombre" required value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="tipo">Tipo de Equipo: <span class="required">*</span></label>
            <select id="tipo" name="tipo" required>
                <option value="">Seleccione un tipo</option>
                <?php
                // Ahora iteramos sobre los tipos obtenidos de la base de datos
                foreach ($tipos_equipo_db as $tipo_opt) {
                    $selected = (($_POST['tipo'] ?? '') == $tipo_opt['nombre_tipo']) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($tipo_opt['nombre_tipo']) . "\" {$selected}>" . htmlspecialchars($tipo_opt['nombre_tipo']) . "</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="marca">Marca: <span class="required">*</span></label>
            <input type="text" id="marca" name="marca" required value="<?php echo htmlspecialchars($_POST['marca'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="modelo">Modelo: <span class="required">*</span></label>
            <input type="text" id="modelo" name="modelo" required value="<?php echo htmlspecialchars($_POST['modelo'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="num_serie">Número de Serie: <span class="required">*</span></label>
            <input type="text" id="num_serie" name="num_serie" required value="<?php echo htmlspecialchars($_POST['num_serie'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="patrimonio">Número de Patrimonio:</label>
            <input type="text" id="patrimonio" name="patrimonio" value="<?php echo htmlspecialchars($_POST['patrimonio'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="procesador">Procesador:</label>
            <input type="text" id="procesador" name="procesador" value="<?php echo htmlspecialchars($_POST['procesador'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="ram">Memoria RAM:</label>
            <input type="text" id="ram" name="ram" placeholder="Ej: 8GB, 16GB" value="<?php echo htmlspecialchars($_POST['ram'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="almacenamiento">Almacenamiento:</label>
            <input type="text" id="almacenamiento" name="almacenamiento" placeholder="Ej: 256GB SSD, 1TB HDD" value="<?php echo htmlspecialchars($_POST['almacenamiento'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="SO">Sistema Operativo:</label>
            <input type="text" id="SO" name="SO" value="<?php echo htmlspecialchars($_POST['SO'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="usuario_asignado">Usuario Asignado:</label>
            <input type="text" id="usuario_asignado" name="usuario_asignado" value="<?php echo htmlspecialchars($_POST['usuario_asignado'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="estado">Estado: <span class="required">*</span></label>
            <select id="estado" name="estado" required>
                <option value="">Seleccione un estado</option>
                <option value="Disponible" <?php echo (($_POST['estado'] ?? '') == 'Disponible') ? 'selected' : ''; ?>>Disponible</option>
                <option value="Asignado" <?php echo (($_POST['estado'] ?? '') == 'Asignado') ? 'selected' : ''; ?>>Asignado</option>
                <option value="En Reparacion" <?php echo (($_POST['estado'] ?? '') == 'En Reparacion') ? 'selected' : ''; ?>>En Reparación</option>
                <option value="De Baja" <?php echo (($_POST['estado'] ?? '') == 'De Baja') ? 'selected' : ''; ?>>De Baja</option>
                <option value="Perdido" <?php echo (($_POST['estado'] ?? '') == 'Perdido') ? 'selected' : ''; ?>>Perdido</option>
            </select>
        </div>

        <div class="form-group">
            <label for="ubicacion">Ubicación:</label>
            <input type="text" id="ubicacion" name="ubicacion" value="<?php echo htmlspecialchars($_POST['ubicacion'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="fecha_asignacion">Fecha de Asignación:</label>
            <input type="date" id="fecha_asignacion" name="fecha_asignacion" value="<?php echo htmlspecialchars($_POST['fecha_asignacion'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="observaciones">Observaciones:</label>
            <textarea id="observaciones" name="observaciones"><?php echo htmlspecialchars($_POST['observaciones'] ?? ''); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="button primary">Guardar Equipo</button>
            <a href="equipos_informaticos.php" class="button secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>