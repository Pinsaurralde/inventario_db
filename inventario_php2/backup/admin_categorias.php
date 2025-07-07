<?php
// admin_categorias.php

// Definir el título de la página antes de incluir el header
$page_title = "Gestionar Categorías - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION
// Redirige si el usuario no está logueado o no es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    $_SESSION['flash_error'] = "Acceso denegado. Debes ser administrador para gestionar categorías.";
    header("Location: dashboard.php"); // Redirige a dashboard o login si no es admin
    exit();
}

$categoria_nombre = ''; // Para pre-llenar el formulario de agregar

// --- Procesar Agregar Categoría ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_categoria'])) {
    $categoria_nombre = trim($_POST['nombre']);

    if (empty($categoria_nombre)) {
        $_SESSION['flash_error'] = "El nombre de la categoría es obligatorio.";
    } else {
        try {
            // Verificar si el nombre de la categoría ya existe
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE nombre = ?");
            $stmt_check->execute([$categoria_nombre]);
            if ($stmt_check->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Ya existe una categoría con el nombre '" . htmlspecialchars($categoria_nombre) . "'.";
            } else {
                $stmt_insert = $pdo->prepare("INSERT INTO categorias (nombre) VALUES (?)");
                $stmt_insert->execute([$categoria_nombre]);
                $_SESSION['flash_message'] = "Categoría '" . htmlspecialchars($categoria_nombre) . "' agregada exitosamente.";
                $categoria_nombre = ''; // Limpiar campo después de éxito
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error al agregar categoría: " . $e->getMessage();
            error_log("Error al agregar categoría en admin_categorias: " . $e->getMessage());
        }
    }
    // Redirigir para evitar reenvío de formulario y mostrar mensaje flash
    header("Location: admin_categorias.php");
    exit();
}

// --- Procesar Eliminar Categoría ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_categoria'])) {
    $categoria_id_to_delete = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT);

    if ($categoria_id_to_delete === false || $categoria_id_to_delete <= 0) {
        $_SESSION['flash_error'] = "ID de categoría inválido para eliminar.";
    } else {
        try {
            // ¡IMPORTANTE! Verificar si hay insumos asociados a esta categoría antes de eliminar
            $stmt_check_insumos = $pdo->prepare("SELECT COUNT(*) FROM insumos WHERE id_categoria = ?");
            $stmt_check_insumos->execute([$categoria_id_to_delete]);
            if ($stmt_check_insumos->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "No se puede eliminar la categoría porque tiene insumos asociados. Desasocia los insumos primero o reasigna su categoría.";
            } else {
                // Proceder con la eliminación si no hay insumos asociados
                $stmt_delete = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
                $stmt_delete->execute([$categoria_id_to_delete]);
                if ($stmt_delete->rowCount() > 0) {
                    $_SESSION['flash_message'] = "Categoría eliminada exitosamente.";
                } else {
                    $_SESSION['flash_error'] = "No se pudo eliminar la categoría o la categoría no existe.";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error al eliminar categoría: " . $e->getMessage();
            error_log("Error al eliminar categoría: " . $e->getMessage());
        }
    }
    // Redirigir para evitar reenvío de formulario y mostrar mensaje flash
    header("Location: admin_categorias.php");
    exit();
}

// --- Obtener Lista de Categorías ---
$categorias = [];
try {
    $stmt_categorias = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error al cargar las categorías: " . $e->getMessage();
    error_log("Error al cargar lista de categorías en admin_categorias: " . $e->getMessage());
}
?>

<h1 class="page-title">Gestionar Categorías</h1>

<h2 class="section-title">Agregar Nueva Categoría</h2>
<div class="form-section">
    <form action="admin_categorias.php" method="post">
        <input type="hidden" name="add_categoria" value="1">
        <div class="form-group">
            <label for="nombre">Nombre de la Categoría:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($categoria_nombre); ?>" required>
        </div>
        <button type="submit" class="button primary">Agregar Categoría</button>
    </form>
</div>

<h2 class="section-title" style="margin-top: 40px;">Listado de Categorías</h2>
<?php if (empty($categorias)): ?>
    <p class="no-records">No hay categorías registradas.</p>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre de la Categoría</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $categoria): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($categoria['id']); ?></td>
                        <td><?php echo htmlspecialchars($categoria['nombre']); ?></td>
                        <td class="actions-column">
                            <form action="admin_categorias.php" method="post" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar la categoría <?php echo htmlspecialchars($categoria['nombre']); ?>? Si hay insumos asociados, la eliminación fallará.');">
                                <input type="hidden" name="delete_categoria" value="1">
                                <input type="hidden" name="categoria_id" value="<?php echo htmlspecialchars($categoria['id']); ?>">
                                <button type="submit" class="button danger small">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>