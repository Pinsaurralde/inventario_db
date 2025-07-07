<?php
// equipo_historial.php

// Definir el título de la página
$page_title = "Historial de Equipo Informático - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión, la conexión a DB y la verificación de login
require_once 'includes/header.php';

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para ver el historial de equipos.";
    header("Location: login.php");
    exit();
}

$equipo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$equipo_data = [];
$historial_movimientos = []; // Se inicializa vacío, se llenará con la consulta
$error_fetching_equipo = '';

if (!$equipo_id) {
    $_SESSION['flash_error'] = "ID de equipo no especificado o inválido para el historial.";
    header("Location: equipos_informaticos.php");
    exit();
}

try {
    // Obtener los datos del equipo
    $stmt = $pdo->prepare("SELECT * FROM equipos_informaticos WHERE id = ?");
    $stmt->execute([$equipo_id]);
    $equipo_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipo_data) {
        $_SESSION['flash_error'] = "Equipo informático no encontrado para el historial.";
        header("Location: equipos_informaticos.php");
        exit();
    }

    // --- Lógica para obtener el historial de movimientos de la tabla historial_equipos ---
    // Si tienes una tabla de usuarios y quieres mostrar el nombre de usuario,
    // deberías hacer un JOIN aquí. Por ahora, solo usamos usuario_id.
    $stmt_historial = $pdo->prepare(
        "SELECT
            he.fecha_movimiento,
            he.tipo_movimiento,
            he.detalles,
            u.username AS usuario_nombre -- Asume que tienes una tabla 'users' con 'id' y 'username'
         FROM historial_equipos he
         LEFT JOIN users u ON he.usuario_id = u.id -- Unir con la tabla de usuarios
         WHERE he.equipo_id = ?
         ORDER BY he.fecha_movimiento DESC"
    );
    $stmt_historial->execute([$equipo_id]);
    $historial_movimientos = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);
    // --- FIN LÓGICA HISTORIAL ---

} catch (PDOException $e) {
    $error_fetching_equipo = "Error al cargar los datos del equipo o su historial: " . $e->getMessage();
    error_log("Error en equipo_historial.php: " . $e->getMessage());
    $_SESSION['flash_error'] = "Error interno al cargar el historial del equipo.";
    header("Location: equipos_informaticos.php");
    exit();
}

?>

<h1 class="page-title">Historial y Detalles del Equipo: <?php echo htmlspecialchars($equipo_data['nombre'] ?? 'N/A'); ?></h1>

<?php if ($error_fetching_equipo): ?>
    <p class="message error"><?php echo htmlspecialchars($error_fetching_equipo); ?></p>
<?php endif; ?>

<div class="details-container">
    <h2>Información del Equipo</h2>
    <div class="detail-grid">
        <p><strong>ID:</strong> <?php echo htmlspecialchars($equipo_data['id'] ?? 'N/A'); ?></p>
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($equipo_data['nombre'] ?? 'N/A'); ?></p>
        <p><strong>Tipo:</strong> <?php echo htmlspecialchars($equipo_data['tipo'] ?? 'N/A'); ?></p>
        <p><strong>Marca:</strong> <?php echo htmlspecialchars($equipo_data['marca'] ?? 'N/A'); ?></p>
        <p><strong>Modelo:</strong> <?php echo htmlspecialchars($equipo_data['modelo'] ?? 'N/A'); ?></p>
        <p><strong>Número de Serie:</strong> <?php echo htmlspecialchars($equipo_data['num_serie'] ?? 'N/A'); ?></p>
        <p><strong>Patrimonio:</strong> <?php echo htmlspecialchars($equipo_data['patrimonio'] ?? 'N/A'); ?></p>
        <p><strong>Procesador:</strong> <?php echo htmlspecialchars($equipo_data['procesador'] ?? 'N/A'); ?></p>
        <p><strong>RAM:</strong> <?php echo htmlspecialchars($equipo_data['ram'] ?? 'N/A'); ?></p>
        <p><strong>Almacenamiento:</strong> <?php echo htmlspecialchars($equipo_data['almacenamiento'] ?? 'N/A'); ?></p>
        <p><strong>Sistema Operativo:</strong> <?php echo htmlspecialchars($equipo_data['SO'] ?? 'N/A'); ?></p>
        <p><strong>Usuario Asignado:</strong> <?php echo htmlspecialchars($equipo_data['usuario_asignado'] ?? 'N/A'); ?></p>
        <p><strong>Estado:</strong> <?php echo htmlspecialchars($equipo_data['estado'] ?? 'N/A'); ?></p>
        <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($equipo_data['ubicacion'] ?? 'N/A'); ?></p>
        <p><strong>Fecha de Asignación:</strong> <?php echo htmlspecialchars($equipo_data['fecha_asignacion'] ?? 'N/A'); ?></p>
        <p><strong>Observaciones:</strong> <?php echo nl2br(htmlspecialchars($equipo_data['observaciones'] ?? 'N/A')); ?></p>
        <p><strong>Creado el:</strong> <?php echo htmlspecialchars($equipo_data['created_at'] ?? 'N/A'); ?></p>
        <p><strong>Última Actualización:</strong> <?php echo htmlspecialchars($equipo_data['updated_at'] ?? 'N/A'); ?></p>
    </div>

    <div class="actions-container" style="margin-top: 20px;">
        <a href="edit_equipo_informatico.php?id=<?php echo htmlspecialchars($equipo_data['id']); ?>" class="button primary"><i class="fas fa-edit"></i> Editar Equipo</a>
        <a href="equipos_informaticos.php" class="button secondary"><i class="fas fa-arrow-alt-circle-left"></i> Volver al Inventario</a>
    </div>
</div>

<h2 style="margin-top: 40px;">Historial de Movimientos</h2>
<?php if (empty($historial_movimientos)): ?>
    <p class="no-records">No hay movimientos registrados para este equipo.</p>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha y Hora</th>
                    <th>Tipo de Movimiento</th>
                    <th>Detalles</th>
                    <th>Realizado por</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial_movimientos as $movimiento): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($movimiento['fecha_movimiento']); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['tipo_movimiento']); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['detalles']); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['usuario_nombre'] ?? 'N/A'); ?></td>
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