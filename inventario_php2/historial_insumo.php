<?php
// historial_insumo.php

// Definir el título de la página antes de incluir el header
$page_title = "Historial de Insumo - Sistema de Inventario"; // Título provisional

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para ver el historial de insumos.";
    header("Location: login.php");
    exit();
}

$insumo_id = $_GET['id'] ?? null;
$insumo_nombre = '';
$historial_movimientos = [];
// Los mensajes flash se manejarán a través de $_SESSION['flash_error'] y $_SESSION['flash_message']

if (!$insumo_id) {
    $_SESSION['flash_error'] = "ID de insumo no especificado para el historial.";
    header("Location: dashboard.php");
    exit();
}

try {
    // Obtener el nombre del insumo
    $stmt_insumo = $pdo->prepare("SELECT nombre FROM insumos WHERE id = ?");
    $stmt_insumo->execute([$insumo_id]);
    $insumo = $stmt_insumo->fetch(PDO::FETCH_ASSOC);

    if (!$insumo) {
        $_SESSION['flash_error'] = "Insumo no encontrado.";
        header("Location: dashboard.php");
        exit();
    }
    $insumo_nombre = $insumo['nombre'];

    // Actualizar el título de la página con el nombre del insumo
    $page_title = "Historial de " . htmlspecialchars($insumo_nombre) . " - Sistema de Inventario";


    // Obtener historial de movimientos para este insumo
    $sql_historial = "
        SELECT 
            m.id, 
            m.cantidad, 
            m.tipo_movimiento, 
            m.fecha_movimiento, 
            m.comentario,
            u.username AS usuario_responsable
        FROM 
            movimientos m
        JOIN 
            users u ON m.id_usuario = u.id
        WHERE 
            m.id_insumo = ?
        ORDER BY 
            m.fecha_movimiento DESC
    ";
    $stmt_historial = $pdo->prepare($sql_historial);
    $stmt_historial->execute([$insumo_id]);
    $historial_movimientos = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error al cargar el historial del insumo: " . $e->getMessage();
    error_log("Error al cargar historial de insumo: " . $e->getMessage());
    // Redirigir en caso de error crítico al cargar
    header("Location: dashboard.php");
    exit();
}
?>

<h1 class="page-title">Historial de Movimientos para: <?php echo htmlspecialchars($insumo_nombre); ?></h1>

<?php if (empty($historial_movimientos)): ?>
    <p class="no-records">No hay movimientos registrados para este insumo.</p>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID Mov.</th>
                    <th>Cantidad</th>
                    <th>Tipo</th>
                    <th>Fecha y Hora</th>
                    <th>Comentario</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial_movimientos as $movimiento): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($movimiento['id']); ?></td>
                        <td>
                            <?php 
                            if ($movimiento['tipo_movimiento'] == 'entrada') {
                                echo '<span style="color: green;">+' . htmlspecialchars($movimiento['cantidad']) . '</span>';
                            } else {
                                echo '<span style="color: red;">-' . htmlspecialchars($movimiento['cantidad']) . '</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars(ucfirst($movimiento['tipo_movimiento'])); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['fecha_movimiento']); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['comentario'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['usuario_responsable']); ?></td>
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