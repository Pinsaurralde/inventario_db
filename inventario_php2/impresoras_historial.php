<?php
// impresora_historial.php

$page_title = "Historial de Impresora - Sistema de Inventario";
require_once 'includes/header.php';

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para ver el historial de impresoras.";
    header("Location: login.php");
    exit();
}

$impresora_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$impresora_info = null;
$historial_registros = [];
$errors = [];
$form_data = []; // Para mantener los datos del formulario de añadir historial

// 1. Verificar ID de impresora y cargar información principal
if ($impresora_id) {
    try {
        $stmt_impresora = $pdo->prepare("SELECT id, nombre, marca, modelo FROM impresoras WHERE id = ?");
        $stmt_impresora->bindParam(1, $impresora_id, PDO::PARAM_INT);
        $stmt_impresora->execute();
        $impresora_info = $stmt_impresora->fetch(PDO::FETCH_ASSOC);

        if (!$impresora_info) {
            $_SESSION['flash_error'] = "ID de impresora no encontrado o inválido.";
            header("Location: impresoras.php");
            exit();
        }

        // 2. Cargar registros de historial para esta impresora
        $stmt_historial = $pdo->prepare("SELECT fecha_hora, tipo_evento, detalle, responsable FROM impresoras_historial WHERE impresora_id = ? ORDER BY fecha_hora DESC");
        $stmt_historial->bindParam(1, $impresora_id, PDO::PARAM_INT);
        $stmt_historial->execute();
        $historial_registros = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error al cargar historial de impresora: " . $e->getMessage());
        $_SESSION['flash_error'] = "Error interno al cargar el historial de la impresora.";
        header("Location: impresoras.php");
        exit();
    }
} else {
    $_SESSION['flash_error'] = "ID de impresora no especificado.";
    header("Location: impresoras.php");
    exit();
}

// 3. Procesar el formulario para añadir un nuevo registro de historial
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_evento = filter_input(INPUT_POST, 'tipo_evento', FILTER_SANITIZE_STRING);
    $detalle = filter_input(INPUT_POST, 'detalle', FILTER_SANITIZE_STRING);
    $responsable = $_SESSION['username'] ?? 'Desconocido'; // Usa el nombre de usuario de la sesión

    $form_data = [
        'tipo_evento' => $tipo_evento,
        'detalle' => $detalle
    ];

    if (empty($tipo_evento)) $errors[] = "El tipo de evento es obligatorio.";
    if (empty($detalle)) $errors[] = "El detalle del evento es obligatorio.";

    if (empty($errors)) {
        try {
            $sql = "INSERT INTO impresoras_historial (impresora_id, tipo_evento, detalle, responsable)
                    VALUES (:impresora_id, :tipo_evento, :detalle, :responsable)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':impresora_id', $impresora_id, PDO::PARAM_INT);
            $stmt->bindParam(':tipo_evento', $tipo_evento);
            $stmt->bindParam(':detalle', $detalle);
            $stmt->bindParam(':responsable', $responsable);
            $stmt->execute();

            $_SESSION['flash_message'] = "Registro de historial añadido exitosamente.";
            // Redirige para evitar el reenvío del formulario y recargar la lista de historial
            header("Location: impresora_historial.php?id=" . $impresora_id);
            exit();

        } catch (PDOException $e) {
            error_log("Error al añadir registro de historial: " . $e->getMessage());
            $_SESSION['flash_error'] = "Error de base de datos al añadir el registro de historial.";
            // No redirigimos para que el usuario vea el error y los datos del formulario se mantengan
        }
    } else {
        $_SESSION['flash_error'] = "Por favor, corrija los siguientes errores para añadir el historial: " . implode('<br>', $errors);
    }
}

// Variables para los valores del formulario de añadir historial
$tipo_evento_val = $form_data['tipo_evento'] ?? '';
$detalle_val = $form_data['detalle'] ?? '';

?>

<h1 class="page-title">Historial de Impresora: <?php echo htmlspecialchars($impresora_info['nombre'] . ' (' . $impresora_info['marca'] . ' ' . $impresora_info['modelo'] . ')'); ?></h1>

<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="message success">
        <?php echo htmlspecialchars($_SESSION['flash_message']); unset($_SESSION['flash_message']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="message error">
        <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
    </div>
<?php endif; ?>

<div class="form-container">
    <h2>Añadir Nuevo Registro al Historial</h2>
    <form action="impresora_historial.php?id=<?php echo htmlspecialchars($impresora_id); ?>" method="POST" class="data-form">
        <div class="form-grid">
            <div class="form-group">
                <label for="tipo_evento">Tipo de Evento:</label>
                <select id="tipo_evento" name="tipo_evento" required>
                    <option value="">Seleccione un tipo</option>
                    <option value="Cambio de Estado" <?php echo ($tipo_evento_val === 'Cambio de Estado') ? 'selected' : ''; ?>>Cambio de Estado</option>
                    <option value="Cambio de Ubicación" <?php echo ($tipo_evento_val === 'Cambio de Ubicación') ? 'selected' : ''; ?>>Cambio de Ubicación</option>
                    <option value="Mantenimiento" <?php echo ($tipo_evento_val === 'Mantenimiento') ? 'selected' : ''; ?>>Mantenimiento</option>
                    <option value="Asignación de Usuario" <?php echo ($tipo_evento_val === 'Asignación de Usuario') ? 'selected' : ''; ?>>Asignación de Usuario</option>
                    <option value="Cambio de IP" <?php echo ($tipo_evento_val === 'Cambio de IP') ? 'selected' : ''; ?>>Cambio de IP</option>
                    <option value="Cambio de Consumible" <?php echo ($tipo_evento_val === 'Cambio de Consumible') ? 'selected' : ''; ?>>Cambio de Consumible</option>
                    <option value="Observación" <?php echo ($tipo_evento_val === 'Observación') ? 'selected' : ''; ?>>Observación</option>
                    <option value="Otro" <?php echo ($tipo_evento_val === 'Otro') ? 'selected' : ''; ?>>Otro</option>
                </select>
            </div>
            <div class="form-group full-width">
                <label for="detalle">Detalle del Evento:</label>
                <textarea id="detalle" name="detalle" rows="3" required><?php echo htmlspecialchars($detalle_val); ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="button primary">Añadir Registro</button>
        </div>
    </form>
</div>

<h2>Registros de Historial</h2>

<?php if (empty($historial_registros)): ?>
    <p class="no-records">No hay registros de historial para esta impresora.</p>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha y Hora</th>
                    <th>Tipo de Evento</th>
                    <th>Detalle</th>
                    <th>Responsable</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial_registros as $registro): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($registro['fecha_hora']))); ?></td>
                        <td><?php echo htmlspecialchars($registro['tipo_evento']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($registro['detalle'])); ?></td>
                        <td><?php echo htmlspecialchars($registro['responsable']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="form-actions" style="margin-top: 20px;">
    <a href="impresoras.php" class="button secondary">Volver a Impresoras</a>
</div>

<?php
require_once 'includes/footer.php';
?>