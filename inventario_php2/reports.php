<?php
// reports.php

// Definir el título de la página antes de incluir el header
$page_title = "Reportes de Movimientos - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// A partir de aquí, el script ya tiene acceso a $pdo y a $_SESSION

// Redirige si el usuario no está logueado o no es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    $_SESSION['flash_error'] = "Acceso denegado. Debes ser administrador para ver los reportes.";
    header("Location: dashboard.php"); // Redirige a dashboard en lugar de login si ya está logueado pero no es admin
    exit();
}

// Los mensajes flash ya se manejan y muestran en includes/header.php

// Consulta base para obtener todos los movimientos con detalles
// Unimos con las tablas insumos, usuarios y areas para obtener nombres en lugar de IDs
$sql = "
    SELECT 
        m.id,
        m.id_insumo,
        i.nombre AS insumo_nombre,
        m.tipo_movimiento,
        m.cantidad,
        m.fecha_movimiento,
        m.comentario,
        m.id_usuario,
        u.username AS usuario_nombre,
        a.nombre AS area_destino_nombre
    FROM 
        movimientos m
    JOIN 
        insumos i ON m.id_insumo = i.id
    JOIN 
        users u ON m.id_usuario = u.id
    LEFT JOIN 
        areas a ON m.id_area_destino = a.id -- LEFT JOIN porque no todos los movimientos (e.g., entradas) tienen área de destino
    ORDER BY m.fecha_movimiento DESC
";

try {
    $stmt = $pdo->query($sql);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error al cargar el historial de movimientos: " . $e->getMessage();
    error_log("Error al cargar movimientos en reports.php: " . $e->getMessage());
    $movimientos = []; // Asegurarse de que $movimientos esté definido
}
?>

<h1 class="page-title">Reportes de Movimientos</h1>

<div class="report-controls">
    <p>Aquí se añadirán los filtros y el botón para exportar a Excel.</p>
</div>

<?php if (empty($movimientos)): ?>
    <p class="no-records">No hay movimientos registrados en el sistema.</p>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Insumo</th>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Motivo</th>
                    <th>Usuario</th>
                    <th>Área Destino</th> </tr>
            </thead>
            <tbody>
                <?php foreach ($movimientos as $movimiento): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($movimiento['fecha_movimiento']); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['insumo_nombre']); ?></td>
                        <td>
                            <?php 
                            if ($movimiento['tipo_movimiento'] == 'entrada') {
                                echo '<span style="color: green;">Entrada</span>';
                            } else {
                                echo '<span style="color: red;">Salida</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($movimiento['cantidad']); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['comentario'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['usuario_nombre']); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['area_destino_nombre'] ?: 'N/A'); ?></td> </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>