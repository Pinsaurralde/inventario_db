<?php
// admin_areas.php

// Definir el título de la página antes de incluir el header
$page_title = "Gestionar Áreas - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION
// Redirige si el usuario no es administrador (verificación adicional por rol)
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    $_SESSION['flash_error'] = "Acceso denegado. Debes ser administrador para gestionar áreas.";
    header("Location: dashboard.php"); // Redirige a dashboard o login si no es admin
    exit();
}

$area_nombre = ''; // Para pre-llenar el formulario de agregar

// --- Procesar Agregar Área ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_area'])) {
    $area_nombre = trim($_POST['nombre']);

    if (empty($area_nombre)) {
        $_SESSION['flash_error'] = "El nombre del área es obligatorio.";
    } else {
        try {
            // Verificar si el nombre del área ya existe
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM areas WHERE nombre = ?");
            $stmt_check->execute([$area_nombre]);
            if ($stmt_check->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Ya existe un área con el nombre '" . htmlspecialchars($area_nombre) . "'.";
            } else {
                $stmt_insert = $pdo->prepare("INSERT INTO areas (nombre) VALUES (?)");
                $stmt_insert->execute([$area_nombre]);
                $_SESSION['flash_message'] = "Área '" . htmlspecialchars($area_nombre) . "' agregada exitosamente.";
                $area_nombre = ''; // Limpiar campo después de éxito
                // Redirigir para evitar reenvío de formulario y mostrar mensaje flash
                header("Location: admin_areas.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error al agregar área: " . $e->getMessage();
            error_log("Error al agregar área en admin_areas: " . $e->getMessage());
        }
    }
}

// --- Procesar Eliminar Área ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_area'])) {
    $area_id_to_delete = filter_input(INPUT_POST, 'area_id', FILTER_VALIDATE_INT);

    if ($area_id_to_delete === false || $area_id_to_delete <= 0) {
        $_SESSION['flash_error'] = "ID de área inválido para eliminar.";
    } else {
        try {
            // Opcional: Verificar si hay movimientos asociados a esta área antes de eliminar
            // Si tu tabla de movimientos tiene una columna id_area_destino, necesitarías esto:
            $stmt_check_movimientos = $pdo->prepare("SELECT COUNT(*) FROM movimientos WHERE id_area_destino = ?");
            $stmt_check_movimientos->execute([$area_id_to_delete]);
            if ($stmt_check_movimientos->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "No se puede eliminar el área porque tiene movimientos de insumos asociados. Desasocia los movimientos o elimínalos primero.";
            } else {
                // Proceder con la eliminación
                $stmt_delete = $pdo->prepare("DELETE FROM areas WHERE id = ?");
                $stmt_delete->execute([$area_id_to_delete]);
                if ($stmt_delete->rowCount() > 0) {
                    $_SESSION['flash_message'] = "Área eliminada exitosamente.";
                } else {
                    $_SESSION['flash_error'] = "No se pudo eliminar el área o el área no existe.";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error al eliminar área: " . $e->getMessage();
            error_log("Error al eliminar área: " . $e->getMessage());
        }
    }
    // Redirigir para evitar reenvío de formulario y mostrar mensaje flash
    header("Location: admin_areas.php");
    exit();
}

// --- Obtener Lista de Áreas ---
$areas = [];
try {
    $stmt_areas = $pdo->query("SELECT id, nombre, created_at FROM areas ORDER BY nombre ASC");
    $areas = $stmt_areas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error al cargar las áreas: " . $e->getMessage();
    error_log("Error al cargar lista de áreas en admin_areas: " . $e->getMessage());
}
?>

<h1 class="page-title">Gestionar Áreas</h1>

<h2 class="section-title">Agregar Nueva Área</h2>
<div class="form-section">
    <form action="admin_areas.php" method="post">
        <input type="hidden" name="add_area" value="1">
        <div class="form-group">
            <label for="nombre">Nombre del Área:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($area_nombre); ?>" required>
        </div>
        <button type="submit" class="button primary">Agregar Área</button>
    </form>
</div>

<h2 class="section-title" style="margin-top: 40px;">Listado de Áreas</h2>
<?php if (empty($areas)): ?>
    <p class="no-records">No hay áreas registradas.</p>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre del Área</th>
                    <th>Fecha de Creación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($areas as $area): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($area['id']); ?></td>
                        <td><?php echo htmlspecialchars($area['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($area['created_at']); ?></td>
                        <td class="actions-column">
                            <form action="admin_areas.php" method="post" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar el área <?php echo htmlspecialchars($area['nombre']); ?>?');">
                                <input type="hidden" name="delete_area" value="1">
                                <input type="hidden" name="area_id" value="<?php echo htmlspecialchars($area['id']); ?>">
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