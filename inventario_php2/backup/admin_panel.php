<?php
// admin_panel.php

// Definir el título de la página antes de incluir el header
$page_title = "Panel de Configuración - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION
// Redirige si el usuario no es administrador (verificación adicional por rol)
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    $_SESSION['flash_error'] = "Acceso denegado. Debes ser administrador para acceder a este panel.";
    header("Location: dashboard.php"); // Redirige a dashboard o login si no es admin
    exit();
}

// Los mensajes flash ya se manejan y muestran en includes/header.php

?>

<h1 class="page-title">Panel de Configuración</h1>

<div class="options-grid">
    <a href="admin_users.php" class="option-card">
        <span class="icon">&#128100;</span> <h3>Gestionar Usuarios</h3>
        <p>Agregar, editar o eliminar usuarios del sistema.</p>
    </a>
    <a href="admin_categorias.php" class="option-card">
        <span class="icon">&#128193;</span> <h3>Gestionar Categorías</h3>
        <p>Administrar las categorías para organizar tus insumos.</p>
    </a>
    <a href="admin_areas.php" class="option-card">
        <span class="icon">&#127968;</span> <h3>Gestionar Áreas</h3>
        <p>Administrar las áreas o ubicaciones de los insumos.</p>
    </a>
    <a href="reports.php" class="option-card">
        <span class="icon"><i class="fas fa-chart-line"></i></span> <h3>Generar Reportes</h3>
        <p>Visualiza y exporta el historial completo de movimientos.</p>
    </a>
</div>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>