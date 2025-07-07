<?php
// includes/header.php

// Asegura que la sesión esté iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluye la conexión a la base de datos
require_once 'db.php';

// Verificar si el usuario está logueado. Si no, redirigir al login.
if (!isset($_SESSION['user_id'])) {
    // Si no está logueado, guarda un mensaje flash antes de redirigir
    $_SESSION['flash_error'] = "Debes iniciar sesión para acceder a esta página.";
    header("Location: login.php");
    exit();
}

// Variables para los mensajes flash (éxito y error)
$flash_message = '';
$flash_error = '';

if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Obtener el título de la página si se ha definido en el script que lo incluye
// Si no, se usa un título predeterminado.
$current_page_title = isset($page_title) ? $page_title : "Sistema de Inventario";

// Determinar la página actual para resaltar el botón de navegación
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($current_page_title); ?></title>
    <link rel="stylesheet" href="css/style.css?v=1.2"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="header">
        <div class="user-info">
            Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> (Rol: <?php echo htmlspecialchars($_SESSION['user_rol']); ?>)
        </div>
        <div class="header-buttons">
            <?php 
            // Mostrar el botón "Menú Principal" solo si NO estamos en home.php
            if ($current_page !== 'home.php'): 
            ?>
                <a href="home.php" class="button primary">Menú Principal</a>
            <?php endif; ?>
            
            <?php if ($_SESSION['user_rol'] == 'admin'): ?>
                <?php
                // Mostrar el botón "Configuración" solo si NO estamos en admin_panel.php
                if ($current_page !== 'admin_panel.php'):
                ?>
                    <a href="admin_panel.php" class="button primary">Configuración</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="logout.php" class="button danger">Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <?php if ($flash_error): ?>
            <p class="message error"><?php echo htmlspecialchars($flash_error); ?></p>
        <?php endif; ?>
        <?php if ($flash_message): ?>
            <p class="message success"><?php echo htmlspecialchars($flash_message); ?></p>
        <?php endif; ?>