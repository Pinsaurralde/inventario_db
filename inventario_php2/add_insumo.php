<?php
// add_insumo.php

// Definir el título de la página antes de incluir el header
$page_title = "Agregar Insumo - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION
// Redirige si el usuario no está logueado o no es admin (la verificación de login ya está en header.php,
// pero esta es una capa adicional para roles específicos)
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    // Establecer un mensaje de error flash antes de redirigir
    $_SESSION['flash_error'] = "Acceso denegado. Debes ser administrador para agregar insumos.";
    header("Location: dashboard.php"); // O a una página de error o al login, según tu flujo
    exit();
}

$nombre = '';
$descripcion = '';
$stock = 0;
$stock_minimo = 0;
$id_categoria = '';
// $error_message y $success_message ya vienen del header, no es necesario re-declararlas aquí.
$categorias = [];

// Obtener categorías para el dropdown
try {
    $stmt = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Usar la variable de mensaje de error que viene del header
    $_SESSION['flash_error'] = "Error al cargar categorías: " . $e->getMessage();
    error_log("Error al cargar categorías en add_insumo: " . $e->getMessage());
    // Si hay un error grave en la carga de categorías, podríamos querer redirigir o mostrar un mensaje más explícito.
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $stock = (int)$_POST['stock'];
    $stock_minimo = (int)$_POST['stock_minimo'];
    $id_categoria = $_POST['id_categoria']; // Puede ser vacío si no se selecciona

    if (empty($nombre)) {
        $_SESSION['flash_error'] = "El nombre del insumo es obligatorio.";
    } elseif ($stock < 0 || $stock_minimo < 0) {
        $_SESSION['flash_error'] = "El stock y el stock mínimo no pueden ser negativos.";
    } else {
        try {
            // Verificar si el nombre del insumo ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM insumos WHERE nombre = ?");
            $stmt->execute([$nombre]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Ya existe un insumo con este nombre. Por favor, elige otro.";
            } else {
                $sql = "INSERT INTO insumos (nombre, descripcion, stock, stock_minimo, id_categoria) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nombre,
                    $descripcion,
                    $stock,
                    $stock_minimo,
                    !empty($id_categoria) ? $id_categoria : null // Guardar null si no se selecciona categoría
                ]);

                $_SESSION['flash_message'] = "Insumo '" . htmlspecialchars($nombre) . "' agregado exitosamente.";
                header("Location: dashboard.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error al agregar insumo: " . $e->getMessage();
            error_log("Error al agregar insumo: " . $e->getMessage());
        }
    }
    // Si hubo un error en el POST, recargar la página para mostrar el error flash.
    // Opcional: Para evitar reenvío de formulario, podrías redirigir con un mensaje de error.
    // header("Location: add_insumo.php"); exit();
}
?>

<h1 class="page-title">Agregar Nuevo Insumo</h1>

<div class="form-section">
    <form action="add_insumo.php" method="post">
        <div class="form-group">
            <label for="nombre">Nombre del Insumo:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
        </div>
        <div class="form-group">
            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion"><?php echo htmlspecialchars($descripcion); ?></textarea>
        </div>
        <div class="form-group">
            <label for="stock">Stock Inicial:</label>
            <input type="number" id="stock" name="stock" value="<?php echo htmlspecialchars($stock); ?>" min="0" required>
        </div>
        <div class="form-group">
            <label for="stock_minimo">Stock Mínimo:</label>
            <input type="number" id="stock_minimo" name="stock_minimo" value="<?php echo htmlspecialchars($stock_minimo); ?>" min="0" required>
        </div>
        <div class="form-group">
            <label for="id_categoria">Categoría:</label>
            <select id="id_categoria" name="id_categoria">
                <option value="">-- Seleccione una categoría --</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['id']); ?>" <?php echo ($id_categoria == $cat['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="button success">Agregar Insumo</button>
        <a href="dashboard.php" class="button secondary">Cancelar</a>
    </form>
</div>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>