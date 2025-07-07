<?php
// salida_insumo.php

// Definir el título de la página antes de incluir el header
$page_title = "Registrar Salida de Insumo - Sistema de Inventario"; // Título provisional

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para registrar salidas de insumos.";
    header("Location: login.php");
    exit();
}

$insumo_id = $_GET['id'] ?? null;
$insumo_nombre = '';
$stock_actual = 0;
$insumo_categoria = 'Sin Categoría'; // Inicializar
$cantidad = ''; // Para pre-llenar el campo en caso de error
$comentario = ''; // Para pre-llenar el campo en caso de error
$selected_area_id = ''; // Inicializar para el selector, para pre-seleccionar en caso de error
$areas_destino = []; // Para almacenar las áreas disponibles

if (!$insumo_id) {
    $_SESSION['flash_error'] = "ID de insumo no especificado para la salida.";
    header("Location: dashboard.php");
    exit();
}

try {
    // Obtener información del insumo y su categoría
    $stmt = $pdo->prepare("
        SELECT
            i.nombre,
            i.stock,
            c.nombre AS nombre_categoria
        FROM
            insumos i
        LEFT JOIN
            categorias c ON i.id_categoria = c.id
        WHERE
            i.id = ?
    ");
    $stmt->execute([$insumo_id]);
    $insumo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$insumo) {
        $_SESSION['flash_error'] = "Insumo no encontrado.";
        header("Location: dashboard.php");
        exit();
    }

    $insumo_nombre = $insumo['nombre'];
    $stock_actual = $insumo['stock'];
    $insumo_categoria = $insumo['nombre_categoria'] ?? 'Sin Categoría'; // Si no tiene categoría, muestra "Sin Categoría"

    // Actualizar el título de la página con el nombre del insumo
    $page_title = "Registrar Salida para: " . htmlspecialchars($insumo_nombre) . " - Sistema de Inventario";


    // Obtener todas las áreas para el selector de destino
    $stmt_areas = $pdo->query("SELECT id, nombre FROM areas ORDER BY nombre ASC");
    $areas_destino = $stmt_areas->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
        $comentario = trim($_POST['comentario']);
        $selected_area_id = filter_input(INPUT_POST, 'id_area_destino', FILTER_VALIDATE_INT);

        // Si la validación del filtro falla, selected_area_id será false o null
        // También si el valor enviado fue una cadena vacía (como el value del option por defecto)
        if ($selected_area_id === false || $selected_area_id === null || (isset($_POST['id_area_destino']) && $_POST['id_area_destino'] === '')) {
            $_SESSION['flash_error'] = "Por favor, selecciona un área de destino.";
            $selected_area_id = ''; // Asegurar que el select no se pre-seleccione si la entrada fue inválida
        } elseif ($cantidad === false || $cantidad <= 0) {
            $_SESSION['flash_error'] = "La cantidad debe ser un número entero positivo.";
        } elseif ($cantidad > $stock_actual) {
            $_SESSION['flash_error'] = "No hay suficiente stock. Stock actual: " . htmlspecialchars($stock_actual);
        } else {
            try {
                $pdo->beginTransaction(); // Iniciar transacción

                // 1. Obtener el stock actual con bloqueo para actualización (para evitar problemas de concurrencia)
                $stmt_lock = $pdo->prepare("SELECT stock FROM insumos WHERE id = ? FOR UPDATE");
                $stmt_lock->execute([$insumo_id]);
                $current_stock_for_update = $stmt_lock->fetchColumn();

                if ($cantidad > $current_stock_for_update) {
                    $pdo->rollBack();
                    $_SESSION['flash_error'] = "No hay suficiente stock (verificación final). Stock actual: " . htmlspecialchars($current_stock_for_update);
                } else {
                    // 2. Actualizar el stock del insumo
                    $new_stock = $current_stock_for_update - $cantidad;
                    $stmt_update = $pdo->prepare("UPDATE insumos SET stock = ? WHERE id = ?");
                    $stmt_update->execute([$new_stock, $insumo_id]);

                    // 3. Registrar el movimiento en la tabla 'movimientos'
                    $tipo = 'salida';
                    $user_id = $_SESSION['user_id'];
                    $sql_movimiento = "INSERT INTO movimientos (id_insumo, cantidad, tipo_movimiento, fecha_movimiento, comentario, id_usuario, id_area_destino) VALUES (?, ?, ?, NOW(), ?, ?, ?)";
                    $stmt_mov = $pdo->prepare($sql_movimiento);
                    $stmt_mov->execute([$insumo_id, $cantidad, $tipo, $comentario, $user_id, $selected_area_id]);

                    $pdo->commit(); // Confirmar transacción

                    // Obtener el nombre del área de destino para el mensaje de éxito
                    $stmt_area_name = $pdo->prepare("SELECT nombre FROM areas WHERE id = ?");
                    $stmt_area_name->execute([$selected_area_id]);
                    $area_name = $stmt_area_name->fetchColumn();

                    $_SESSION['flash_message'] = "Salida de " . htmlspecialchars($cantidad) . " unidades de '" . htmlspecialchars($insumo_nombre) . "' a " . htmlspecialchars($area_name) . " registrada exitosamente. Nuevo stock: " . htmlspecialchars($new_stock) . ".";
                    header("Location: dashboard.php");
                    exit();
                }

            } catch (PDOException $e) {
                $pdo->rollBack(); // Revertir transacción en caso de error
                $_SESSION['flash_error'] = "Error al registrar la salida: " . $e->getMessage();
                error_log("Error al registrar salida de insumo: " . $e->getMessage());
            }
        }
        // Si hay errores en el POST, las variables $cantidad, $comentario y $selected_area_id
        // conservarán los valores del POST para pre-llenar el formulario.
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error al cargar los datos del insumo: " . $e->getMessage();
    error_log("Error al cargar insumo para salida: " . $e->getMessage());
    header("Location: dashboard.php"); // Redirigir en caso de error crítico al cargar
    exit();
}
?>

<h1 class="page-title">Registrar Salida para: <?php echo htmlspecialchars($insumo_nombre); ?></h1>
<p style="text-align: center; font-size: 1.2em; color: #555; margin-top: -15px; margin-bottom: 30px;">
    Stock Actual: <strong><?php echo htmlspecialchars($stock_actual); ?></strong><br>
    Categoría del Insumo: <strong><?php echo htmlspecialchars($insumo_categoria); ?></strong>
</p>

<div class="form-section">
    <form action="salida_insumo.php?id=<?php echo htmlspecialchars($insumo_id); ?>" method="post">
        <div class="form-group">
            <label for="cantidad">Cantidad de Salida:</label>
            <input type="number" id="cantidad" name="cantidad" value="<?php echo htmlspecialchars($cantidad); ?>" min="1" required>
        </div>
        <div class="form-group">
            <label for="comentario">Razón/Comentario:</label>
            <textarea id="comentario" name="comentario" placeholder="Ej: Venta al cliente Y, Uso interno, Merma, etc." rows="4"><?php echo htmlspecialchars($comentario); ?></textarea>
        </div>
        <div class="form-group">
            <label for="id_area_destino">Área de Destino:</label>
            <select id="id_area_destino" name="id_area_destino" required>
                <option value="">Selecciona un área de destino</option>
                <?php foreach ($areas_destino as $area): ?>
                    <option value="<?php echo htmlspecialchars($area['id']); ?>"
                        <?php echo ($selected_area_id == $area['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($area['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="button danger">Registrar Salida</button>
        <a href="dashboard.php" class="button secondary">Cancelar</a>
    </form>
</div>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>