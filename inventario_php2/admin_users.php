<?php
// admin_users.php

// Definir el título de la página antes de incluir el header
$page_title = "Gestionar Usuarios - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION
// Redirige si el usuario no es administrador (verificación adicional por rol)
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    $_SESSION['flash_error'] = "Acceso denegado. Debes ser administrador para gestionar usuarios.";
    header("Location: dashboard.php"); // Redirige a dashboard o login si no es admin
    exit();
}

$username = '';
$rol = 'usuario'; // Valor por defecto para el rol

// --- Procesar Agregar Usuario ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $rol = $_POST['rol'];

    if (empty($username) || empty($password) || empty($rol)) {
        $_SESSION['flash_error'] = "Todos los campos son obligatorios para agregar un usuario.";
    } elseif (strlen($password) < 6) {
        $_SESSION['flash_error'] = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        try {
            // Verificar si el nombre de usuario ya existe
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt_check->execute([$username]);
            if ($stmt_check->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "El nombre de usuario '" . htmlspecialchars($username) . "' ya existe.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt_insert = $pdo->prepare("INSERT INTO users (username, password_hash, rol, created_at) VALUES (?, ?, ?, NOW())");
                $stmt_insert->execute([$username, $password_hash, $rol]);
                $_SESSION['flash_message'] = "Usuario '" . htmlspecialchars($username) . "' agregado exitosamente.";
                $username = ''; // Limpiar campo después de éxito
                $password = ''; // Limpiar campo (aunque no es relevante porque se redirige)
                $rol = 'usuario'; // Resetear rol
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error al agregar usuario: " . $e->getMessage();
            error_log("Error al agregar usuario en admin_users: " . $e->getMessage());
        }
    }
    // Redirigir para evitar reenvío de formulario y mostrar mensaje flash
    header("Location: admin_users.php");
    exit();
}

// --- Procesar Eliminar Usuario ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id_to_delete = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    if ($user_id_to_delete === false || $user_id_to_delete <= 0) {
        $_SESSION['flash_error'] = "ID de usuario inválido para eliminar.";
    } elseif ($user_id_to_delete == $_SESSION['user_id']) {
        $_SESSION['flash_error'] = "No puedes eliminar tu propia cuenta.";
    } else {
        try {
            // No permitir eliminar al último usuario administrador
            $stmt_admin_check = $pdo->prepare("SELECT rol FROM users WHERE id = ?");
            $stmt_admin_check->execute([$user_id_to_delete]);
            $target_rol = $stmt_admin_check->fetchColumn();

            if ($target_rol === 'admin') {
                $stmt_count_admins = $pdo->query("SELECT COUNT(*) FROM users WHERE rol = 'admin'");
                if ($stmt_count_admins->fetchColumn() <= 1) {
                    $_SESSION['flash_error'] = "No se puede eliminar el último usuario administrador.";
                }
            }

            if (!isset($_SESSION['flash_error'])) { // Solo proceder si no se estableció un error antes
                $stmt_delete = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt_delete->execute([$user_id_to_delete]);
                if ($stmt_delete->rowCount() > 0) {
                    $_SESSION['flash_message'] = "Usuario eliminado exitosamente.";
                } else {
                    $_SESSION['flash_error'] = "No se pudo eliminar el usuario o el usuario no existe.";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error al eliminar usuario: " . $e->getMessage();
            error_log("Error al eliminar usuario: " . $e->getMessage());
        }
    }
    // Redirigir para evitar reenvío de formulario y mostrar mensaje flash
    header("Location: admin_users.php");
    exit();
}

// --- Obtener Lista de Usuarios ---
$users = [];
try {
    $stmt_users = $pdo->query("SELECT id, username, rol, created_at FROM users ORDER BY id ASC");
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error al cargar los usuarios: " . $e->getMessage();
    error_log("Error al cargar lista de usuarios en admin_users: " . $e->getMessage());
}
?>

<h1 class="page-title">Gestionar Usuarios</h1>

<h2 class="section-title">Agregar Nuevo Usuario</h2>
<div class="form-section">
    <form action="admin_users.php" method="post">
        <input type="hidden" name="add_user" value="1">
        <div class="form-group">
            <label for="username">Nombre de Usuario:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="rol">Rol:</label>
            <select id="rol" name="rol">
                <option value="usuario" <?php echo ($rol == 'usuario') ? 'selected' : ''; ?>>Usuario</option>
                <option value="admin" <?php echo ($rol == 'admin') ? 'selected' : ''; ?>>Admin</option>
            </select>
        </div>
        <button type="submit" class="button primary">Agregar Usuario</button>
    </form>
</div>

<h2 class="section-title" style="margin-top: 40px;">Listado de Usuarios</h2>
<?php if (empty($users)): ?>
    <p class="no-records">No hay usuarios registrados.</p>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre de Usuario</th>
                    <th>Rol</th>
                    <th>Fecha de Creación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($user['rol'])); ?></td>
                        <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                        <td class="actions-column">
                            <a href="edit_user.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="button secondary small">Editar</a>
                            <?php if ($user['id'] != $_SESSION['user_id']): // No permitir eliminar al propio usuario logueado ?>
                                <form action="admin_users.php" method="post" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar al usuario <?php echo htmlspecialchars($user['username']); ?>? Esta acción es irreversible.');">
                                    <input type="hidden" name="delete_user" value="1">
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                    <button type="submit" class="button danger small">Eliminar</button>
                                </form>
                            <?php else: ?>
                                <span class="button secondary small disabled">No Eliminar</span>
                            <?php endif; ?>
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