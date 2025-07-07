<?php
// login.php

require_once 'db.php'; // Incluye la conexión a la base de datos
session_start(); // Inicia la sesión

// Si el usuario ya está logueado, redirigir al home
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

// Limpiar cualquier mensaje flash de errores previos al intentar iniciar sesión
// Esto es importante para que los mensajes de login sean específicos de este intento
if (isset($_SESSION['flash_error'])) {
    $current_error_message = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
} else {
    $current_error_message = '';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $_SESSION['flash_error'] = "Por favor, ingresa tu nombre de usuario y contraseña.";
    } else {
        try {
            // Preparar la consulta para buscar el usuario por nombre de usuario
            $stmt = $pdo->prepare("SELECT id, username, password_hash, rol FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Verificar si se encontró el usuario y si la contraseña es correcta
            if ($user && password_verify($password, $user['password_hash'])) {
                // Contraseña válida, iniciar sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_rol'] = $user['rol'];

                // Eliminar cualquier mensaje de error previo del login exitoso
                if (isset($_SESSION['flash_error'])) {
                    unset($_SESSION['flash_error']);
                }
                
                header("Location: home.php"); // Redirigir al home
                exit();
            } else {
                $_SESSION['flash_error'] = "Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Error en la base de datos. Por favor, inténtalo de nuevo más tarde.";
            error_log("Error de login (PDOException): " . $e->getMessage() . " - IP: " . $_SERVER['REMOTE_ADDR']); // Registrar el error para depuración
        }
    }
    // Si hubo un error en el POST, reasignar el mensaje para que se muestre en el formulario
    if (isset($_SESSION['flash_error'])) {
        $current_error_message = $_SESSION['flash_error'];
        // No unset aquí, se unsetea al inicio para que persista para la misma carga de página.
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Inventario</title>
    <link rel="stylesheet" href="css/style.css?v=1.1">
</head>
<body class="login-container">
    <div class="login-box">
        <h2>Iniciar Sesión</h2>
        <?php if ($current_error_message): // Mostrar el mensaje de error si existe ?>
            <p class="message error"><?php echo htmlspecialchars($current_error_message); ?></p>
        <?php endif; ?>
        <form action="login.php" method="post">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required autofocus value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="button primary">Entrar</button>
        </form>
    </div>
</body>
</html>