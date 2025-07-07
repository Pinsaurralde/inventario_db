<?php
// entrada_insumo.php

// Definir el título de la página antes de incluir el header
$page_title = "Registrar Entrada de Insumo - Sistema de Inventario"; // Título provisional

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para registrar entradas de insumos.";
    header("Location: login.php");
    exit();
}

$insumo_id = $_GET['id'] ?? null;
$insumo_nombre = '';
$stock_actual = 0;
$cantidad = ''; // Para pre-llenar el campo en caso de error
$comentario = ''; // Para pre-llenar el campo en caso de error

if (!$insumo_id) {
    $_SESSION['flash_error'] = "ID de insumo no especificado para la entrada.";
    header("Location: dashboard.php");
    exit();
}

try {
    // Obtener información del insumo
    $stmt = $pdo->prepare("SELECT nombre, stock FROM insumos WHERE id = ?");
    $stmt->execute([$insumo_id]);
    $insumo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$insumo) {
        $_SESSION['flash_error'] = "Insumo no encontrado.";
        header("Location: dashboard.php");
        exit();
    }

    $insumo_nombre = $insumo['nombre'];
    $stock_actual = $insumo['stock'];

    // Actualizar el título de la página con el nombre del insumo
    $page_title = "Registrar Entrada para: " . htmlspecialchars($insumo_nombre) . " - Sistema de Inventario";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
        $comentario = trim($_POST['comentario']);

        if ($cantidad === false || $cantidad <= 0) {
            $_SESSION['flash_error'] = "La cantidad debe ser un número entero positivo.";
            // No redirigimos aquí para que el usuario pueda corregir el formulario
        } else {
            try {
                $pdo->beginTransaction(); // Iniciar transacción

                // 1. Actualizar el stock del insumo
                // SELECT FOR UPDATE para asegurar la atomicidad en un entorno concurrente
                $stmt_lock = $pdo->prepare("SELECT stock FROM insumos WHERE id = ? FOR UPDATE");
                $stmt_lock->execute([$insumo_id]);
                $current_stock_for_update = $stmt_lock->fetchColumn();

                $new_stock = $current_stock_for_update + $cantidad;
                $stmt_update = $pdo->prepare("UPDATE insumos SET stock = ? WHERE id = ?");
                $stmt_update->execute([$new_stock, $insumo_id]);

                // 2. Registrar el movimiento en la tabla 'movimientos'
                $tipo = 'entrada';
                $user_id = $_SESSION['user_id'];
                $sql_movimiento = "INSERT INTO movimientos (id_insumo, cantidad, tipo_movimiento, fecha_movimiento, comentario, id_usuario) VALUES (?, ?, ?, NOW(), ?, ?)";
                $stmt_mov = $pdo->prepare($sql_movimiento);
                $stmt_mov->execute([$insumo_id, $cantidad, $tipo, $comentario, $user_id]);

                $pdo->commit(); // Confirmar transacción

                $_SESSION['flash_message'] = "Entrada de " . htmlspecialchars($cantidad) . " unidades de '" . htmlspecialchars($insumo_nombre) . "' registrada exitosamente. Nuevo stock: " . htmlspecialchars($new_stock) . ".";
                header("Location: dashboard.php");
                exit();

            } catch (PDOException $e) {
                $pdo->rollBack(); // Revertir transacción en caso de error
                $_SESSION['flash_error'] = "Error al registrar la entrada: " . $e->getMessage();
                error_log("Error al registrar entrada de insumo: " . $e->getMessage());
            }
        }
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error al cargar los datos del insumo: " . $e->getMessage();
    error_log("Error al cargar insumo para entrada: " . $e->getMessage());
    header("Location: dashboard.php"); // Redirigir en caso de error crítico al cargar
    exit();
}
?>

<h1 class="page-title">Registrar Entrada para: <?php echo htmlspecialchars($insumo_nombre); ?></h1>
<p style="text-align: center; font-size: 1.2em; color: #555; margin-top: -15px; margin-bottom: 30px;">
    Stock Actual: <strong><?php echo htmlspecialchars($stock_actual); ?></strong>
</p>

<div class="form-section">
    <form action="entrada_insumo.php?id=<?php echo htmlspecialchars($insumo_id); ?>" method="post">
        <div class="form-group">
            <label for="cantidad">Cantidad de Entrada:</label>
            <input type="number" id="cantidad" name="cantidad" value="<?php echo htmlspecialchars($cantidad); ?>" min="1" required>
        </div>
        <div class="form-group">
            <label for="comentario">Razón/Comentario:</label>
            <textarea id="comentario" name="comentario" placeholder="Ej: Compra a proveedor X, Devolución de cliente, etc." rows="4" required><?php echo htmlspecialchars($comentario);?></textarea>
        </div>
        <button type="submit" class="button success">Registrar Entrada</button>
        <a href="dashboard.php" class="button secondary">Cancelar</a>
    </form>
</div>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>