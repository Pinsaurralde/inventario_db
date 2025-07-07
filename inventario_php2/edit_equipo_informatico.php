<?php
// edit_equipo_informatico.php

// Definir el título de la página
$page_title = "Editar Equipo Informático - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para editar equipos informáticos.";
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
    error_log("Error al cargar tipos de equipo en edit_equipo_informatico: " . $e->getMessage());
    $_SESSION['flash_error'] = ($_SESSION['flash_error'] ?? '') . "<br>Error al cargar los tipos de equipo desde la base de datos.";
    // Opcional: si la carga de tipos es crítica, puedes redirigir o salir aquí.
}
// =============================================================

// Inicializar un array para almacenar los datos del equipo
$equipo_data = [];
$edit_errors = [];
$original_equipo_data = []; // Para almacenar los datos originales antes de la edición

// 1. Obtener el ID del equipo de la URL
// Este 'id' es para la función principal de EDICIÓN de la página.
$equipo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Lógica para manejo de eliminación (MOVIDA AQUÍ DESDE equipos_informaticos.php)
// Usamos 'id_delete' para no colisionar con el 'id' principal de la edición.
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id_delete'])) {
    $equipo_id_to_delete = filter_input(INPUT_GET, 'id_delete', FILTER_VALIDATE_INT);

    // Validar que el ID para eliminar sea válido antes de proceder
    if (!$equipo_id_to_delete) {
        $_SESSION['flash_error'] = "ID de equipo inválido para eliminar.";
    } else {
        try {
            // --- REGISTRAR MOVIMIENTO DE ELIMINACIÓN ANTES DE ELIMINAR EL EQUIPO DE LA TABLA PRINCIPAL ---
            // Primero, obtén los datos del equipo a eliminar para el historial
            // Esto asegura que tengamos los datos correctos incluso si la página no fue cargada para edición.
            $stmt_get_equipo = $pdo->prepare("SELECT nombre, num_serie FROM equipos_informaticos WHERE id = ?");
            $stmt_get_equipo->execute([$equipo_id_to_delete]);
            $equipo_for_log = $stmt_get_equipo->fetch(PDO::FETCH_ASSOC);

            if ($equipo_for_log) {
                $equipo_info_for_log = $equipo_for_log['nombre'] . " (Serie: " . ($equipo_for_log['num_serie'] ?? 'N/A') . ")";
                $detalles_historial_delete = "Equipo eliminado. Detalles: " . $equipo_info_for_log;

                try {
                    $stmt_historial_delete = $pdo->prepare(
                        "INSERT INTO historial_equipos (equipo_id, tipo_movimiento, detalles, usuario_id)
                         VALUES (?, ?, ?, ?)"
                    );
                    $usuario_que_elimina = $_SESSION['user_id'] ?? null; // Usa el ID del usuario logueado
                    $stmt_historial_delete->execute([$equipo_id_to_delete, 'Eliminado', $detalles_historial_delete, $usuario_que_elimina]);

                } catch (PDOException $e) {
                    // Registrar el error pero no detener la eliminación del equipo principal
                    error_log("Error al insertar historial (eliminación) en edit_equipo_informatico: " . $e->getMessage());
                    $_SESSION['flash_error'] = ($_SESSION['flash_error'] ?? '') . "<br>Error al registrar historial de eliminación: " . $e->getMessage();
                }
            } else {
                error_log("Intento de eliminar equipo ID: {$equipo_id_to_delete} pero no se encontraron sus datos para el log de historial.");
            }
            // --- FIN REGISTRO HISTORIAL DE ELIMINACIÓN ---

            // Ahora sí, eliminar el equipo de la tabla principal
            $stmt_delete = $pdo->prepare("DELETE FROM equipos_informaticos WHERE id = ?");
            if ($stmt_delete->execute([$equipo_id_to_delete])) {
                $_SESSION['flash_message'] = "Equipo informático eliminado exitosamente.";
            } else {
                $_SESSION['flash_error'] = "No se pudo eliminar el equipo informático.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error de base de datos al eliminar equipo: " . $e->getMessage();
            error_log("Error al eliminar equipo informático: " . $e->getMessage());
        }
    }
    // Después de eliminar, siempre redirigir a la lista principal de equipos
    header("Location: equipos_informaticos.php");
    exit();
}


// --- CONTINÚA CON LA LÓGICA DE EDICIÓN NORMAL SI NO FUE UNA SOLICITUD DE ELIMINACIÓN ---

// Si no se proporcionó un ID válido para edición o se intentó acceder directamente sin ID
if (!$equipo_id) {
    $_SESSION['flash_error'] = "ID de equipo no especificado o inválido para edición.";
    header("Location: equipos_informaticos.php");
    exit();
}

// 2. Cargar los datos ORIGINALES del equipo desde la base de datos para edición
// Esto se hace antes de cualquier lógica POST para tener los datos de referencia
try {
    $stmt = $pdo->prepare("SELECT * FROM equipos_informaticos WHERE id = ?");
    $stmt->execute([$equipo_id]);
    $equipo_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipo_data) {
        $_SESSION['flash_error'] = "Equipo informático no encontrado.";
        header("Location: equipos_informaticos.php");
        exit();
    }
    // Guardar una copia de los datos originales para comparar en caso de POST
    $original_equipo_data = $equipo_data;

} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error al cargar los datos del equipo: " . $e->getMessage();
    error_log("Error al cargar equipo para edición: " . $e->getMessage());
    header("Location: equipos_informaticos.php");
    exit();
}


// 3. Procesar el formulario cuando se envía (método POST)
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
    $fecha_asignacion = filter_input(INPUT_POST, 'fecha_asignacion', FILTER_SANITIZE_STRING);
    $observaciones = filter_input(INPUT_POST, 'observaciones', FILTER_SANITIZE_STRING);

    // Validaciones básicas (igual que en add_equipo_informatico.php)
    if (empty($nombre)) { $edit_errors[] = "El campo 'Nombre' es obligatorio."; }
    if (empty($tipo)) { $edit_errors[] = "El campo 'Tipo' es obligatorio."; }
    if (empty($marca)) { $edit_errors[] = "El campo 'Marca' es obligatorio."; }
    if (empty($modelo)) { $edit_errors[] = "El campo 'Modelo' es obligatorio."; }
    if (empty($num_serie)) { $edit_errors[] = "El campo 'Número de Serie' es obligatorio."; }
    if (empty($estado)) { $edit_errors[] = "El campo 'Estado' es obligatorio."; }

    // Validar formato de fecha y permitir NULL
    if (!empty($fecha_asignacion) && !strtotime($fecha_asignacion)) {
        $edit_errors[] = "El formato de la 'Fecha de Asignación' no es válido.";
        $fecha_asignacion = NULL;
    } else if (empty($fecha_asignacion)) {
        $fecha_asignacion = NULL;
    }

    // Validación de unicidad para num_serie y patrimonio
    try {
        if ($num_serie !== $original_equipo_data['num_serie']) { // Si el número de serie ha cambiado
            $stmt_check_serie = $pdo->prepare("SELECT COUNT(*) FROM equipos_informaticos WHERE num_serie = ? AND id != ?");
            $stmt_check_serie->execute([$num_serie, $equipo_id]);
            if ($stmt_check_serie->fetchColumn() > 0) {
                $edit_errors[] = "El número de serie '$num_serie' ya existe en otro equipo.";
            }
        }
        if (!empty($patrimonio) && $patrimonio !== ($original_equipo_data['patrimonio'] ?? '')) { // Si el patrimonio ha cambiado y no está vacío
            $stmt_check_patrimonio = $pdo->prepare("SELECT COUNT(*) FROM equipos_informaticos WHERE patrimonio = ? AND id != ?");
            $stmt_check_patrimonio->execute([$patrimonio, $equipo_id]);
            if ($stmt_check_patrimonio->fetchColumn() > 0) {
                $edit_errors[] = "El número de patrimonio '$patrimonio' ya existe en otro equipo.";
            }
        }
    } catch (PDOException $e) {
        $edit_errors[] = "Error al verificar unicidad: " . $e->getMessage();
        error_log("Error al verificar unicidad en edición de equipo: " . $e->getMessage());
    }


    if (empty($edit_errors)) {
        try {
            // Preparar la consulta SQL para actualizar los datos
            $stmt_update = $pdo->prepare(
                "UPDATE equipos_informaticos SET
                    nombre = ?, tipo = ?, marca = ?, modelo = ?, num_serie = ?, patrimonio = ?,
                    procesador = ?, ram = ?, almacenamiento = ?, SO = ?, usuario_asignado = ?,
                    estado = ?, ubicacion = ?, fecha_asignacion = ?, observaciones = ?
                WHERE id = ?"
            );

            // Ejecutar la consulta
            $stmt_update->execute([
                $nombre, $tipo, $marca, $modelo, $num_serie, $patrimonio, $procesador, $ram,
                $almacenamiento, $SO, $usuario_asignado, $estado, $ubicacion, $fecha_asignacion,
                $observaciones, $equipo_id
            ]);

            // --- REGISTRAR MOVIMIENTO DE ACTUALIZACIÓN EN EL HISTORIAL ---
            $changes = [];
            $field_map = [
                'nombre' => 'Nombre', 'tipo' => 'Tipo', 'marca' => 'Marca', 'modelo' => 'Modelo',
                'num_serie' => 'Número de Serie', 'patrimonio' => 'Patrimonio', 'procesador' => 'Procesador',
                'ram' => 'RAM', 'almacenamiento' => 'Almacenamiento', 'SO' => 'Sistema Operativo',
                'usuario_asignado' => 'Usuario Asignado', 'estado' => 'Estado', 'ubicacion' => 'Ubicación',
                'fecha_asignacion' => 'Fecha de Asignación', 'observaciones' => 'Observaciones'
            ];

            // Datos actuales después de la actualización (los que se acaban de guardar)
            $current_data_after_post = [
                'nombre' => $nombre, 'tipo' => $tipo, 'marca' => $marca, 'modelo' => $modelo,
                'num_serie' => $num_serie, 'patrimonio' => $patrimonio, 'procesador' => $procesador,
                'ram' => $ram, 'almacenamiento' => $almacenamiento, 'SO' => $SO,
                'usuario_asignado' => $usuario_asignado, 'estado' => $estado, 'ubicacion' => $ubicacion,
                'fecha_asignacion' => $fecha_asignacion, 'observaciones' => $observaciones
            ];

            foreach ($field_map as $db_field => $display_name) {
                // Manejo especial para campos que pueden ser NULL o vacíos.
                // Convertir valores NULL a cadena vacía o 'Vacío' para comparación consistente.
                $old_value = ($original_equipo_data[$db_field] === null || $original_equipo_data[$db_field] === '') ? 'Vacío' : $original_equipo_data[$db_field];
                $new_value = ($current_data_after_post[$db_field] === null || $current_data_after_post[$db_field] === '') ? 'Vacío' : $current_data_after_post[$db_field];

                // Para fechas, formatear para una comparación legible si no son NULL
                if ($db_field == 'fecha_asignacion' && $old_value !== 'Vacío' && $new_value !== 'Vacío') {
                    $old_value = date('Y-m-d', strtotime($old_value));
                    $new_value = date('Y-m-d', strtotime($new_value));
                }

                if ($old_value !== $new_value) {
                    $changes[] = "$display_name cambió de '$old_value' a '$new_value'.";
                }
            }

            if (!empty($changes)) {
                $detalles_historial_update = "Equipo actualizado. " . implode(" ", $changes);
                try {
                    $stmt_historial = $pdo->prepare(
                        "INSERT INTO historial_equipos (equipo_id, tipo_movimiento, detalles, usuario_id)
                         VALUES (?, ?, ?, ?)"
                    );
                    $usuario_que_actualiza = $_SESSION['user_id'] ?? null; // Usa el ID del usuario logueado
                    $stmt_historial->execute([$equipo_id, 'Actualizado', $detalles_historial_update, $usuario_que_actualiza]);

                } catch (PDOException $e) {
                    error_log("Error al insertar historial (actualizar) en edit_equipo_informatico: " . $e->getMessage());
                    $_SESSION['flash_error'] = ($_SESSION['flash_error'] ?? '') . "<br>Error al registrar historial de actualización: " . $e->getMessage();
                }
            }
            // --- FIN REGISTRO HISTORIAL ---

            // Establecer un mensaje de éxito y redirigir al historial del equipo
            $_SESSION['flash_message'] = "Equipo informático actualizado exitosamente.";
            header("Location: equipo_historial.php?id=" . $equipo_id); // Redirige al historial
            exit();

        } catch (PDOException $e) {
            $edit_errors[] = "Error de base de datos al actualizar: " . $e->getMessage();
            error_log("Error al actualizar equipo informático: " . $e->getMessage());
        }
    }

    // Si hay errores en el POST, rellenar el formulario con los datos enviados por el usuario
    // y mostrar los errores
    if (!empty($edit_errors)) {
        $_SESSION['flash_error'] = implode("<br>", $edit_errors);
        // Mantener los datos en el formulario para que el usuario no los pierda
        $equipo_data = array_merge($original_equipo_data, $_POST); // Mezcla originales con los del POST
    }
    // Si se llegó aquí por errores, el script continúa para mostrar el formulario con los errores/datos

} // Fin del if POST

// Si no es un POST (es una carga inicial de la página o hubo errores),
// los datos del formulario serán los cargados de la base de datos ($equipo_data)
// o los enviados por POST si hubo errores.
?>

<h1 class="page-title">Editar Equipo Informático</h1>

<div class="form-container">
    <form action="edit_equipo_informatico.php?id=<?php echo htmlspecialchars($equipo_id); ?>" method="POST">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($equipo_id); ?>">

        <div class="form-group">
            <label for="nombre">Nombre del Equipo: <span class="required">*</span></label>
            <input type="text" id="nombre" name="nombre" required value="<?php echo htmlspecialchars($equipo_data['nombre'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="tipo">Tipo de Equipo: <span class="required">*</span></label>
            <select id="tipo" name="tipo" required>
                <option value="">Seleccione un tipo</option>
                <?php
                // Iterar sobre los tipos obtenidos de la base de datos
                foreach ($tipos_equipo_db as $tipo_opt) {
                    // Asegúrate de que $equipo_data['tipo'] esté definido antes de usarlo
                    $selected = (($equipo_data['tipo'] ?? '') == $tipo_opt['nombre_tipo']) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($tipo_opt['nombre_tipo']) . "\" {$selected}>" . htmlspecialchars($tipo_opt['nombre_tipo']) . "</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="marca">Marca: <span class="required">*</span></label>
            <input type="text" id="marca" name="marca" required value="<?php echo htmlspecialchars($equipo_data['marca'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="modelo">Modelo: <span class="required">*</span></label>
            <input type="text" id="modelo" name="modelo" required value="<?php echo htmlspecialchars($equipo_data['modelo'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="num_serie">Número de Serie: <span class="required">*</span></label>
            <input type="text" id="num_serie" name="num_serie" required value="<?php echo htmlspecialchars($equipo_data['num_serie'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="patrimonio">Número de Patrimonio:</label>
            <input type="text" id="patrimonio" name="patrimonio" value="<?php echo htmlspecialchars($equipo_data['patrimonio'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="procesador">Procesador:</label>
            <input type="text" id="procesador" name="procesador" value="<?php echo htmlspecialchars($equipo_data['procesador'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="ram">Memoria RAM:</label>
            <input type="text" id="ram" name="ram" placeholder="Ej: 8GB, 16GB" value="<?php echo htmlspecialchars($equipo_data['ram'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="almacenamiento">Almacenamiento:</label>
            <input type="text" id="almacenamiento" name="almacenamiento" placeholder="Ej: 256GB SSD, 1TB HDD" value="<?php echo htmlspecialchars($equipo_data['almacenamiento'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="SO">Sistema Operativo:</label>
            <input type="text" id="SO" name="SO" value="<?php echo htmlspecialchars($equipo_data['SO'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="usuario_asignado">Usuario Asignado:</label>
            <input type="text" id="usuario_asignado" name="usuario_asignado" value="<?php echo htmlspecialchars($equipo_data['usuario_asignado'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="estado">Estado: <span class="required">*</span></label>
            <select id="estado" name="estado" required>
                <option value="">Seleccione un estado</option>
                <?php
                $estados_equipo = ['Disponible', 'Asignado', 'En Reparacion', 'De Baja', 'Perdido'];
                foreach ($estados_equipo as $estado_opt) {
                    $selected = (($equipo_data['estado'] ?? '') == $estado_opt) ? 'selected' : '';
                    echo "<option value=\"{$estado_opt}\" {$selected}>{$estado_opt}</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="ubicacion">Ubicación:</label>
            <input type="text" id="ubicacion" name="ubicacion" value="<?php echo htmlspecialchars($equipo_data['ubicacion'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="fecha_asignacion">Fecha de Asignación:</label>
            <input type="date" id="fecha_asignacion" name="fecha_asignacion" value="<?php echo htmlspecialchars($equipo_data['fecha_asignacion'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="observaciones">Observaciones:</label>
            <textarea id="observaciones" name="observaciones"><?php echo htmlspecialchars($equipo_data['observaciones'] ?? ''); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="button primary">Actualizar Equipo</button>
            <a href="equipo_historial.php?id=<?php echo htmlspecialchars($equipo_id); ?>" class="button secondary">Cancelar y Volver al Historial</a>
            <a href="edit_equipo_informatico.php?action=delete&id_delete=<?php echo htmlspecialchars($equipo_data['id']); ?>" class="button danger" onclick="return confirm('¿Estás seguro de que quieres eliminar este equipo? Esta acción es irreversible y NO quedará registro en el historial.');">Eliminar Equipo</a>
        </div>
    </form>
</div>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>