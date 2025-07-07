<?php
// delete_insumo.php

// Iniciar sesión y conexión a la base de datos.
// Se asume que db.php o una configuración similar ya se cargó para establecer $pdo.
// Si esta página es accedida directamente, o las páginas que la llaman NO incluyen header.php,
// deberías re-evaluar si necesitas session_start() y require_once 'db.php'; aquí.
// Para el flujo típico (desde dashboard.php), estos ya estarían inicializados.
// Incluimos header.php para asegurar que la sesión y $pdo estén disponibles, y para la lógica de seguridad.
require_once 'includes/header.php';


// Redirige si el usuario no está logueado o no es admin
// La verificación básica de sesión ya está en header.php. Aquí reforzamos el rol.
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    $_SESSION['flash_error'] = "Acceso denegado. Debes ser administrador para eliminar insumos.";
    header("Location: dashboard.php"); // Redirige a dashboard o login si no es admin
    exit();
}

$insumo_id = $_GET['id'] ?? null;

if (!$insumo_id) {
    $_SESSION['flash_error'] = "ID de insumo no especificado para eliminar.";
    header("Location: dashboard.php");
    exit();
}

try {
    // Opcional: Obtener el nombre del insumo antes de eliminar para el mensaje de éxito
    $stmt = $pdo->prepare("SELECT nombre FROM insumos WHERE id = ?");
    $stmt->execute([$insumo_id]);
    $insumo = $stmt->fetch(PDO::FETCH_ASSOC);
    $insumo_nombre = $insumo ? $insumo['nombre'] : 'Insumo Desconocido';

    // Eliminar el insumo
    $stmt = $pdo->prepare("DELETE FROM insumos WHERE id = ?");
    $stmt->execute([$insumo_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_message'] = "Insumo '" . htmlspecialchars($insumo_nombre) . "' eliminado exitosamente.";
    } else {
        $_SESSION['flash_error'] = "No se encontró el insumo con ID " . htmlspecialchars($insumo_id) . " para eliminar.";
    }
} catch (PDOException $e) {
    // Verificar si el error es por una restricción de clave foránea (por ejemplo, insumos en movimientos)
    if ($e->getCode() == 23000) { // SQLSTATE para integridad de datos (MySQL)
        $_SESSION['flash_error'] = "No se puede eliminar el insumo '" . htmlspecialchars($insumo_nombre) . "' porque está asociado a movimientos de stock.";
    } else {
        $_SESSION['flash_error'] = "Error al eliminar insumo: " . $e->getMessage();
    }
    error_log("Error al eliminar insumo (ID: " . $insumo_id . "): " . $e->getMessage());
}

// Redirigir siempre al dashboard después de intentar eliminar
header("Location: dashboard.php");
exit();
?>