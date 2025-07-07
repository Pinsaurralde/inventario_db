<?php
// edit_user.php

// Definir el título de la página antes de incluir el header
$page_title = "Editar Usuario - Sistema de Inventario"; // Título provisional

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION

// Redirige si el usuario no está logueado o no es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    $_SESSION['flash_error'] = "Acceso denegado. Debes ser administrador para editar usuarios.";
    header("Location: dashboard.php");
    exit();
}

$user_id_to_edit = $_GET['id'] ?? null;
$user_data = null; // Inicializar a null

// Si no se proporciona un ID de usuario para editar, redirige
if (!$user_id_to_edit) {
    $_SESSION['flash_error'] = "ID de usuario no especificado para editar.";
    header("Location: admin_users.php");
    exit();
}

try {
    // Obtener los datos actuales del usuario a editar
    $stmt = $pdo->prepare("SELECT id, username, rol FROM users WHERE id = ?");
    $stmt->execute([$user_id_to_edit]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        $_SESSION['flash_error'] = "Usuario no encontrado.";
        header("Location: admin_users.php");
        exit();
    }

    // Actualizar el título de la página con el nombre de usuario
    $page_title = "Editar Usuario: " . htmlspecialchars($user_data['username']) . " - Sistema de Inventario";

    // --- Procesar Actualización de Usuario ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
        $new_username = trim($_POST['username']);
        $new_rol = $_POST['rol'];
        $new_password = $_POST['password']; // Contraseña puede estar vacía
        $confirm_password = $_POST['confirm_password'];

        if (empty($new_username)) {
            $_SESSION['flash_error'] = "El nombre de usuario no puede estar vacío.";
        } elseif (!in_array($new_rol, ['admin', 'user'])) {
            $_SESSION['flash_error'] = "Rol de usuario inválido.";
        } elseif (!empty($new_password) && strlen($new_password) < 6) {
            $_SESSION['flash_error'] = "La nueva contraseña debe tener al menos 6 caracteres.";
        } elseif (!empty($new_password) && $new_password !== $confirm_password) {
            $_SESSION['flash_error'] = "La contraseña y la confirmación no coinciden.";
        } else {
            try {
                // Verificar si el nuevo nombre de usuario ya existe para otro ID
                $stmt_check_username = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $stmt_check_username->execute([$new_username, $user_id_to_edit]);
                if ($stmt_check_username->fetchColumn() > 0) {
                    $_SESSION['flash_error'] = "El nombre de usuario '" . htmlspecialchars($new_username) . "' ya está en uso por otro usuario.";
                } else {
                    $sql = "UPDATE users SET username = ?, rol = ? ";
                    $params = [$new_username, $new_rol];

                    if (!empty($new_password)) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $sql .= ", password_hash = ? ";
                        $params[] = $hashed_password;
                    }
                    $sql .= "WHERE id = ?";
                    $params[] = $user_id_to_edit;

                    $stmt_update = $pdo->prepare($sql);
                    $stmt_update->execute($params);

                    $_SESSION['flash_message'] = "Usuario '" . htmlspecialchars($new_username) . "' actualizado exitosamente.";

                    // Si el usuario que se está editando es el mismo que está logueado,
                    // actualiza la sesión para reflejar los cambios de rol o nombre de usuario.
                    if ($_SESSION['user_id'] == $user_id_to_edit) {
                        $_SESSION['username'] = $new_username;
                        $_SESSION['user_rol'] = $new_rol;
                    }

                    // Redirigir a la misma página para limpiar el POST y mostrar el mensaje flash
                    header("Location: edit_user.php?id=" . htmlspecialchars($user_id_to_edit));
                    exit();
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Error al actualizar usuario: " . $e->getMessage();
                error_log("Error al actualizar usuario en edit_user: " . $e->getMessage());
            }
        }
        // Si hay errores en el POST, recargar los datos del usuario para mantener los campos
        // No se redirige aquí para que los mensajes de error se muestren en la misma página y el usuario pueda corregir.
        $stmt = $pdo->prepare("SELECT id, username, rol FROM users WHERE id = ?");
        $stmt->execute([$user_id_to_edit]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC); // Re-fetch para mostrar los valores actuales o los del POST si hubo error
        if (!$user_data) { // Si por alguna razón el usuario desaparece durante la edición
            $_SESSION['flash_error'] = "Error al cargar datos del usuario después del intento de actualización.";
            header("Location: admin_users.php");
            exit();
        }
    }

} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error al cargar los datos del usuario: " . $e->getMessage();
    error_log("Error al cargar usuario para edición en edit_user: " . $e->getMessage());
    header("Location: admin_users.php"); // Redirigir en caso de error crítico al cargar
    exit();
}
?>

<h1 class="page-title">Editar Usuario: <?php echo htmlspecialchars($user_data['username'] ?? 'N/A'); ?></h1>

<?php if ($user_data): ?>
    <div class="form-section">
        <form action="edit_user.php?id=<?php echo htmlspecialchars($user_data['id']); ?>" method="post">
            <input type="hidden" name="update_user" value="1">
            <div class="form-group">
                <label for="username">Nombre de Usuario:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
            </div>
            <div class="form-group">
                <label for="rol">Rol:</label>
                <select id="rol" name="rol" required>
                    <option value="admin" <?php echo ($user_data['rol'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                    <option value="user" <?php echo ($user_data['rol'] === 'user') ? 'selected' : ''; ?>>Usuario Estándar</option>
                </select>
            </div>
            <div class="form-group">
                <label for="password">Nueva Contraseña (dejar vacío para no cambiar):</label>
                <input type="password" id="password" name="password" placeholder="Ingresa nueva contraseña">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmar Nueva Contraseña:</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirma nueva contraseña">
            </div>
            <button type="submit" class="button primary">Actualizar Usuario</button>
            <a href="admin_users.php" class="button secondary">Cancelar</a>
        </form>
    </div>
<?php else: ?>
    <p class="message error">No se pudo cargar la información del usuario.</p>
<?php endif; ?>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>