<?php
// home.php (o main_menu.php / portal.php)

// Definir el título de la página antes de incluir el header
$page_title = "Menú Principal - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para acceder al menú principal.";
    header("Location: login.php");
    exit();
}
?>

<h1 class="page-title">Bienvenido al Sistema de Gestión</h1>

<?php // Los mensajes flash ya se manejan y muestran en includes/header.php ?>

<div class="options-grid">
    <a href="dashboard.php" class="option-card">
        <span class="icon"><i class="fas fa-boxes"></i></span> <h3>Gestión de Insumos</h3>
        <p>Administra el inventario de insumos y sus movimientos.</p>
    </a>
    
    <a href="impresoras.php" class="option-card">
        <span class="icon"><i class="fas fa-print"></i></span> <h3>Gestión de Impresoras</h3>
        <p>Administra el inventario y estado de las impresoras.</p>
    </a>
    
    <a href="equipos_informaticos.php" class="option-card">
        <span class="icon"><i class="fas fa-laptop"></i></span> <h3>Inventario Equipos</h3>
        <p>Gestiona los equipos tecnológicos de la empresa.</p>
    </a>
    
   
</div>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>