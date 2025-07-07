<?php
// edit_insumo.php

// Definir el título de la página antes de incluir el header
$page_title = "Editar Insumo - Sistema de Inventario"; // Título provisional, se actualizará si se encuentra el insumo

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para acceder a esta página.";
    header("Location: login.php");
    exit();
}

$insumo_id = $_GET['id'] ?? null;
$insumo = null;
$categorias = [];
// Los mensajes flash se manejarán a través de $_SESSION['flash_error'] y $_SESSION['flash_message']

if (!$insumo_id) {
    $_SESSION['flash_error'] = "ID de insumo no especificado para edición.";
    header("Location: dashboard.php");
    exit();
}

try {
    // 1. Obtener datos del insumo a editar
    $stmt_insumo = $pdo->prepare("SELECT id, nombre, stock, stock_minimo, id_categoria FROM insumos WHERE id = ?");
    $stmt_insumo->execute([$insumo_id]);
    $insumo = $stmt_insumo->fetch(PDO::FETCH_ASSOC);

    if (!$insumo) {
        $_SESSION['flash_error'] = "Insumo no encontrado.";
        header("Location: dashboard.php");
        exit();
    }

    // Actualizar el título de la página con el nombre del insumo
    $page_title = "Editar Insumo: " . htmlspecialchars($insumo['nombre']) . " - Sistema de Inventario";

    // 2. Obtener todas las categorías para el selector
    $stmt_categorias = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

    // Si el formulario fue enviado (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre']);
        $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
        $stock_minimo = filter_input(INPUT_POST, 'stock_minimo', FILTER_VALIDATE_INT);
        $id_categoria = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);
        // Manejar el caso de "Sin Categoría" (si el ID es 0 o null)
        $id_categoria = ($id_categoria === false || $id_categoria === null || $id_categoria == 0) ? null : $id_categoria;


        if (empty($nombre) || $stock === false || $stock < 0 || $stock_minimo === false || $stock_minimo < 0) {
            $_SESSION['flash_error'] = "Todos los campos de número deben ser enteros positivos y el nombre no puede estar vacío.";
        } else {
            // Verificar si el nombre ya existe para otro insumo (excluyendo el actual)
            $stmt_check_nombre = $pdo->prepare("SELECT COUNT(*) FROM insumos WHERE nombre = ? AND id != ?");
            $stmt_check_nombre->execute([$nombre, $insumo_id]);
            if ($stmt_check_nombre->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Ya existe un insumo con este nombre. Por favor, elige otro.";
            } else {
                try {
                    $sql = "UPDATE insumos SET nombre = ?, stock = ?, stock_minimo = ?, id_categoria = ? WHERE id = ?";
                    $stmt_update = $pdo->prepare($sql);
                    $stmt_update->execute([$nombre, $stock, $stock_minimo, $id_categoria, $insumo_id]);

                    $_SESSION['flash_message'] = "Insumo '" . htmlspecialchars($nombre) . "' actualizado exitosamente.";
                    header("Location: dashboard.php");
                    exit();

                } catch (PDOException $e) {
                    $_SESSION['flash_error'] = "Error al actualizar insumo: " . $e->getMessage();
                    error_log("Error al actualizar insumo en edit_insumo: " . $e->getMessage());
                }
            }
        }
        // Si hay errores en el POST, recargamos los datos para que el formulario muestre los valores POST
        // y los mensajes flash se mostrarán en la siguiente carga de la página.
        // Aquí no se redirige si hay error en POST para que el usuario pueda corregir sin perder los datos ingresados.
        // Las variables de display se actualizarán más abajo con los valores del POST.
    }

} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error al cargar datos del insumo o categorías: " . $e->getMessage();
    error_log("Error al cargar edit_insumo: " . $e->getMessage());
    // Si hay un error crítico al cargar, redirigir al dashboard.
    header("Location: dashboard.php");
    exit();
}

// Variables para pre-llenar el formulario
// Usamos los valores del insumo recuperado si no hay POST, o los valores del POST si hubo un intento de envío con errores.
$nombre_display = $insumo['nombre'] ?? '';
$stock_display = $insumo['stock'] ?? 0;
$stock_minimo_display = $insumo['stock_minimo'] ?? 0;
$categoria_seleccionada = $insumo['id_categoria'] ?? null;

// Si hubo un POST y un error, re-populateamos con los valores del POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['flash_error'])) { // Comprobamos si se estableció un flash_error
    $nombre_display = htmlspecialchars($_POST['nombre'] ?? '');
    $stock_display = htmlspecialchars($_POST['stock'] ?? 0);
    $stock_minimo_display = htmlspecialchars($_POST['stock_minimo'] ?? 0);
    $categoria_seleccionada = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);
    // Asegurarse de que 'Sin Categoría' se maneje correctamente si se envió un valor nulo/cero
    if ($categoria_seleccionada === false || $categoria_seleccionada == 0) {
        $categoria_seleccionada = null;
    }
}


?>

<h1 class="page-title">Editar Insumo: <?php echo htmlspecialchars($nombre_display); ?></h1>

<div class="form-section">
    <form action="edit_insumo.php?id=<?php echo htmlspecialchars($insumo_id); ?>" method="post">
        <div class="form-group">
            <label for="nombre">Nombre del Insumo:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo $nombre_display; ?>" required>
        </div>
        <div class="form-group">
            <label for="stock">Stock Actual:</label>
            <input type="number" id="stock" name="stock" value="<?php echo $stock_display; ?>" min="0" required>
        </div>
        <div class="form-group">
            <label for="stock_minimo">Stock Mínimo:</label>
            <input type="number" id="stock_minimo" name="stock_minimo" value="<?php echo $stock_minimo_display; ?>" min="0" required>
        </div>
        <div class="form-group">
            <label for="id_categoria">Categoría:</label>
            <select id="id_categoria" name="id_categoria">
                <option value="">Sin Categoría</option> <?php foreach ($categorias as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['id']); ?>"
                        <?php echo ($categoria_seleccionada == $cat['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="button primary">Actualizar Insumo</button>
        <a href="dashboard.php" class="button secondary">Cancelar</a>
    </form>
</div>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>