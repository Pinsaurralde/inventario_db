<?php
// add_impresora.php

$page_title = "Añadir Nueva Impresora - Sistema de Inventario";
require_once 'includes/header.php';

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para añadir impresoras.";
    header("Location: login.php");
    exit();
}

$errors = [];
$form_data = []; // Para mantener los datos del formulario en caso de error

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y sanear los datos del formulario
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
    $tipo = filter_input(INPUT_POST, 'tipo', FILTER_SANITIZE_STRING);
    $marca = filter_input(INPUT_POST, 'marca', FILTER_SANITIZE_STRING);
    $modelo = filter_input(INPUT_POST, 'modelo', FILTER_SANITIZE_STRING);
    $numero_serie = filter_input(INPUT_POST, 'numero_serie', FILTER_SANITIZE_STRING);
    $patrimonio = filter_input(INPUT_POST, 'patrimonio', FILTER_SANITIZE_STRING);
    $tipo_conexion = filter_input(INPUT_POST, 'tipo_conexion', FILTER_SANITIZE_STRING);
    $ip_address = filter_input(INPUT_POST, 'ip_address', FILTER_VALIDATE_IP); // Valida formato IP
    $url_interfaz = filter_input(INPUT_POST, 'url_interfaz', FILTER_VALIDATE_URL); // Valida formato URL
    $ubicacion = filter_input(INPUT_POST, 'ubicacion', FILTER_SANITIZE_STRING);
    $estado = filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_STRING);
    $consumible_tipo = filter_input(INPUT_POST, 'consumible_tipo', FILTER_SANITIZE_STRING);
    $fecha_adquisicion = filter_input(INPUT_POST, 'fecha_adquisicion', FILTER_SANITIZE_STRING); // Validar formato DATE
    $usuario_asignado = filter_input(INPUT_POST, 'usuario_asignado', FILTER_SANITIZE_STRING);
    $observaciones = filter_input(INPUT_POST, 'observaciones', FILTER_SANITIZE_STRING);

    // Guardar datos en form_data para repoblar el formulario
    $form_data = [
        'nombre' => $nombre, 'tipo' => $tipo, 'marca' => $marca, 'modelo' => $modelo,
        'numero_serie' => $numero_serie, 'patrimonio' => $patrimonio, 'tipo_conexion' => $tipo_conexion,
        'ip_address' => $ip_address, 'url_interfaz' => $url_interfaz, 'ubicacion' => $ubicacion,
        'estado' => $estado, 'consumible_tipo' => $consumible_tipo, 'fecha_adquisicion' => $fecha_adquisicion,
        'usuario_asignado' => $usuario_asignado, 'observaciones' => $observaciones
    ];

    // --- Validaciones (puedes añadir más si es necesario) ---
    if (empty($nombre)) $errors[] = "El nombre de la impresora es obligatorio.";
    if (empty($tipo)) $errors[] = "El tipo de impresora es obligatorio.";
    if (empty($marca)) $errors[] = "La marca es obligatoria.";
    if (empty($modelo)) $errors[] = "El modelo es obligatorio.";
    if (empty($tipo_conexion)) $errors[] = "El tipo de conexión es obligatorio.";
    if (empty($ubicacion)) $errors[] = "La ubicación es obligatoria.";
    if (empty($estado)) $errors[] = "El estado es obligatorio.";

    // Validación específica para IP y URL si el tipo de conexión es 'Red' o 'Wi-Fi'
    if (in_array($tipo_conexion, ['Red', 'Wi-Fi'])) {
        if (!empty($ip_address) && $ip_address === false) { // filter_input devuelve false si falla la validación
            $errors[] = "La dirección IP no es válida.";
        }
        // url_interfaz no es estrictamente obligatorio para todas las de red, pero puede validarse
        if (!empty($url_interfaz) && $url_interfaz === false) {
             $errors[] = "La URL de la interfaz no es válida.";
        }
    } else { // Si es USB, IP y URL no deben estar presentes
        $ip_address = null;
        $url_interfaz = null;
    }

    // Validación de fecha de adquisición (opcional, pero buena práctica)
    if (!empty($fecha_adquisicion)) {
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_adquisicion)) {
            $errors[] = "El formato de la fecha de adquisición debe ser AAAA-MM-DD.";
        }
    } else {
        $fecha_adquisicion = null; // Guardar como NULL si está vacío
    }

    // Si no hay errores, insertar en la base de datos
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO impresoras (nombre, tipo, marca, modelo, numero_serie, patrimonio, tipo_conexion, ip_address, url_interfaz, ubicacion, estado, consumible_tipo, fecha_adquisicion, usuario_asignado, observaciones)
                    VALUES (:nombre, :tipo, :marca, :modelo, :numero_serie, :patrimonio, :tipo_conexion, :ip_address, :url_interfaz, :ubicacion, :estado, :consumible_tipo, :fecha_adquisicion, :usuario_asignado, :observaciones)";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':marca', $marca);
            $stmt->bindParam(':modelo', $modelo);
            $stmt->bindParam(':numero_serie', $numero_serie);
            $stmt->bindParam(':patrimonio', $patrimonio);
            $stmt->bindParam(':tipo_conexion', $tipo_conexion);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->bindParam(':url_interfaz', $url_interfaz);
            $stmt->bindParam(':ubicacion', $ubicacion);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':consumible_tipo', $consumible_tipo);
            $stmt->bindParam(':fecha_adquisicion', $fecha_adquisicion);
            $stmt->bindParam(':usuario_asignado', $usuario_asignado);
            $stmt->bindParam(':observaciones', $observaciones);

            $stmt->execute();

            $_SESSION['flash_message'] = "Impresora '" . htmlspecialchars($nombre) . "' añadida exitosamente.";
            header("Location: impresoras.php"); // Redirige a la lista de impresoras
            exit();

        } catch (PDOException $e) {
            error_log("Error al añadir impresora: " . $e->getMessage());
            $errors[] = "Error al añadir la impresora: " . $e->getMessage();
            $_SESSION['flash_error'] = "Error de base de datos al añadir la impresora. Por favor, inténtelo de nuevo.";
        }
    } else {
        $_SESSION['flash_error'] = "Por favor, corrija los siguientes errores: " . implode('<br>', $errors);
    }
}

// Variables para los valores por defecto del formulario o los datos enviados si hubo errores
// Usamos el operador de fusión de null (??) para mostrar los datos previos en caso de error
$nombre_val = $form_data['nombre'] ?? '';
$tipo_val = $form_data['tipo'] ?? '';
$marca_val = $form_data['marca'] ?? '';
$modelo_val = $form_data['modelo'] ?? '';
$numero_serie_val = $form_data['numero_serie'] ?? '';
$patrimonio_val = $form_data['patrimonio'] ?? '';
$tipo_conexion_val = $form_data['tipo_conexion'] ?? '';
$ip_address_val = $form_data['ip_address'] ?? '';
$url_interfaz_val = $form_data['url_interfaz'] ?? '';
$ubicacion_val = $form_data['ubicacion'] ?? '';
$estado_val = $form_data['estado'] ?? '';
$consumible_tipo_val = $form_data['consumible_tipo'] ?? '';
$fecha_adquisicion_val = $form_data['fecha_adquisicion'] ?? '';
$usuario_asignado_val = $form_data['usuario_asignado'] ?? '';
$observaciones_val = $form_data['observaciones'] ?? '';

?>

<h1 class="page-title">Añadir Nueva Impresora</h1>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="message error">
        <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
    </div>
<?php endif; ?>

<div class="form-container">
    <form action="add_impresora.php" method="POST" class="data-form">
        <div class="form-grid">
            <div class="form-group">
                <label for="nombre">Nombre de la Impresora:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre_val); ?>" required>
            </div>

            <div class="form-group">
                <label for="tipo">Tipo:</label>
                <select id="tipo" name="tipo" required>
                    <option value="">Seleccione el tipo</option>
                    <option value="Láser" <?php echo ($tipo_val === 'Láser') ? 'selected' : ''; ?>>Láser</option>
                    <option value="Inyección de Tinta" <?php echo ($tipo_val === 'Inyección de Tinta') ? 'selected' : ''; ?>>Inyección de Tinta</option>
                    <option value="Matricial" <?php echo ($tipo_val === 'Matricial') ? 'selected' : ''; ?>>Matricial</option>
                    <option value="Multifunción" <?php echo ($tipo_val === 'Multifunción') ? 'selected' : ''; ?>>Multifunción</option>
                    <option value="Térmica" <?php echo ($tipo_val === 'Térmica') ? 'selected' : ''; ?>>Térmica</option>
                    <option value="Otros" <?php echo ($tipo_val === 'Otros') ? 'selected' : ''; ?>>Otros</option>
                </select>
            </div>

            <div class="form-group">
                <label for="marca">Marca:</label>
                <input type="text" id="marca" name="marca" value="<?php echo htmlspecialchars($marca_val); ?>" required>
            </div>

            <div class="form-group">
                <label for="modelo">Modelo:</label>
                <input type="text" id="modelo" name="modelo" value="<?php echo htmlspecialchars($modelo_val); ?>" required>
            </div>

            <div class="form-group">
                <label for="numero_serie">Número de Serie:</label>
                <input type="text" id="numero_serie" name="numero_serie" value="<?php echo htmlspecialchars($numero_serie_val); ?>">
            </div>

            <div class="form-group">
                <label for="patrimonio">Patrimonio:</label>
                <input type="text" id="patrimonio" name="patrimonio" value="<?php echo htmlspecialchars($patrimonio_val); ?>">
            </div>

            <div class="form-group">
                <label for="tipo_conexion">Tipo de Conexión:</label>
                <select id="tipo_conexion" name="tipo_conexion" required>
                    <option value="">Seleccione el tipo de conexión</option>
                    <option value="Red" <?php echo ($tipo_conexion_val === 'Red') ? 'selected' : ''; ?>>Red</option>
                    <option value="USB" <?php echo ($tipo_conexion_val === 'USB') ? 'selected' : ''; ?>>USB</option>
                    <option value="Wi-Fi" <?php echo ($tipo_conexion_val === 'Wi-Fi') ? 'selected' : ''; ?>>Wi-Fi</option>
                </select>
            </div>

            <div class="form-group">
                <label for="ip_address">Dirección IP (solo para Red/Wi-Fi):</label>
                <input type="text" id="ip_address" name="ip_address" value="<?php echo htmlspecialchars($ip_address_val); ?>">
            </div>

            <div class="form-group">
                <label for="url_interfaz">URL de Interfaz (solo para impresoras leasing/red):</label>
                <input type="text" id="url_interfaz" name="url_interfaz" value="<?php echo htmlspecialchars($url_interfaz_val); ?>">
            </div>

            <div class="form-group">
                <label for="ubicacion">Ubicación:</label>
                <input type="text" id="ubicacion" name="ubicacion" value="<?php echo htmlspecialchars($ubicacion_val); ?>" required>
            </div>

            <div class="form-group">
                <label for="estado">Estado:</label>
                <select id="estado" name="estado" required>
                    <option value="">Seleccione el estado</option>
                    <option value="Activa" <?php echo ($estado_val === 'Activa') ? 'selected' : ''; ?>>Activa</option>
                    <option value="En Mantenimiento" <?php echo ($estado_val === 'En Mantenimiento') ? 'selected' : ''; ?>>En Mantenimiento</option>
                    <option value="Fuera de Servicio" <?php echo ($estado_val === 'Fuera de Servicio') ? 'selected' : ''; ?>>Fuera de Servicio</option>
                    <option value="En Almacén" <?php echo ($estado_val === 'En Almacén') ? 'selected' : ''; ?>>En Almacén</option>
                </select>
            </div>

            <div class="form-group">
                <label for="consumible_tipo">Tipo de Consumible (Tóner/Cartucho/Tinta):</label>
                <input type="text" id="consumible_tipo" name="consumible_tipo" value="<?php echo htmlspecialchars($consumible_tipo_val); ?>">
            </div>

            <div class="form-group">
                <label for="fecha_adquisicion">Fecha de Adquisición:</label>
                <input type="date" id="fecha_adquisicion" name="fecha_adquisicion" value="<?php echo htmlspecialchars($fecha_adquisicion_val); ?>">
            </div>

            <div class="form-group">
                <label for="usuario_asignado">Usuario/Departamento Asignado:</label>
                <input type="text" id="usuario_asignado" name="usuario_asignado" value="<?php echo htmlspecialchars($usuario_asignado_val); ?>">
            </div>

            <div class="form-group full-width">
                <label for="observaciones">Observaciones:</label>
                <textarea id="observaciones" name="observaciones" rows="4"><?php echo htmlspecialchars($observaciones_val); ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="button primary">Añadir Impresora</button>
            <a href="impresoras.php" class="button secondary">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoConexionSelect = document.getElementById('tipo_conexion');
    const ipAddressGroup = document.querySelector('.form-group:has(#ip_address)');
    const urlInterfazGroup = document.querySelector('.form-group:has(#url_interfaz)');
    const ipAddressInput = document.getElementById('ip_address');
    const urlInterfazInput = document.getElementById('url_interfaz');

    function toggleIpAndUrlFields() {
        const selectedValue = tipoConexionSelect.value;
        if (selectedValue === 'Red' || selectedValue === 'Wi-Fi') {
            if (ipAddressGroup) ipAddressGroup.style.display = 'block';
            if (urlInterfazGroup) urlInterfazGroup.style.display = 'block';
        } else {
            if (ipAddressGroup) ipAddressGroup.style.display = 'none';
            if (urlInterfazGroup) urlInterfazGroup.style.display = 'none';
            // Limpiar valores si el campo se oculta
            if (ipAddressInput) ipAddressInput.value = '';
            if (urlInterfazInput) urlInterfazInput.value = '';
        }
    }

    // Ejecutar al cargar la página por si hay un valor pre-seleccionado
    toggleIpAndUrlFields();

    // Ejecutar cuando el tipo de conexión cambia
    if (tipoConexionSelect) {
        tipoConexionSelect.addEventListener('change', toggleIpAndUrlFields);
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>