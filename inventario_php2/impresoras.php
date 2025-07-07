<?php
// impresoras.php

// Definir el título de la página
$page_title = "Gestión de Impresoras - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión y la conexión a DB (a través de db.php)
require_once 'includes/header.php';

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para acceder a la gestión de impresoras.";
    header("Location: login.php");
    exit();
}

// --- Lógica para ELIMINAR una impresora (al principio del script, antes del HTML) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete_impresora' && isset($_GET['id'])) {
    $impresora_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($impresora_id_to_delete) {
        try {
            // Iniciar una transacción
            $pdo->beginTransaction();

            // Eliminar registros relacionados en la tabla de historial
            // Esto es crucial para mantener la integridad referencial, especialmente si no tienes ON DELETE CASCADE
            $stmt_historial_delete = $pdo->prepare("DELETE FROM impresoras_historial WHERE impresora_id = ?");
            $stmt_historial_delete->bindParam(1, $impresora_id_to_delete, PDO::PARAM_INT);
            $stmt_historial_delete->execute();

            // Eliminar la impresora
            $stmt_delete = $pdo->prepare("DELETE FROM impresoras WHERE id = ?");
            $stmt_delete->bindParam(1, $impresora_id_to_delete, PDO::PARAM_INT);
            $stmt_delete->execute();

            $pdo->commit(); // Confirmar la transacción

            if ($stmt_delete->rowCount() > 0) {
                $_SESSION['flash_message'] = "Impresora eliminada exitosamente.";
            } else {
                $_SESSION['flash_error'] = "No se pudo encontrar la impresora para eliminar o ya fue eliminada.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack(); // Revertir la transacción
            error_log("Error al eliminar impresora: " . $e->getMessage());
            $_SESSION['flash_error'] = "Error de base de datos al eliminar la impresora: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "ID de impresora no válido para la eliminación.";
    }
    header("Location: impresoras.php"); // Redirige para limpiar la URL
    exit();
}


// --- CONFIGURACIÓN DE PAGINACIÓN ---
$records_per_page = 10; // Puedes ajustar esto según tus necesidades
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if (!$current_page || $current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

// --- Lógica para obtener el TOTAL de impresoras para la paginación ---
$total_impresoras = 0;
try {
    // $pdo ya está disponible aquí gracias a la inclusión de header.php
    $stmt_count = $pdo->query("SELECT COUNT(*) FROM impresoras");
    $total_impresoras = $stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Error al contar impresoras para paginación: " . $e->getMessage());
    $_SESSION['flash_error'] = "Error al contar impresoras para paginación.";
}
$total_pages = ceil($total_impresoras / $records_per_page);

// --- Lógica para obtener las impresoras de la base de datos ---
$impresoras = [];
$error_fetching_impresoras = '';

try {
    $stmt = $pdo->prepare("SELECT id, nombre, tipo, marca, modelo, numero_serie, patrimonio, tipo_conexion, ip_address, url_interfaz, ubicacion, estado, consumible_tipo, fecha_adquisicion, usuario_asignado, observaciones FROM impresoras ORDER BY nombre LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $impresoras = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_fetching_impresoras = "Error al cargar la lista de impresoras: " . $e->getMessage();
    error_log("Error al cargar impresoras en impresoras.php: " . $e->getMessage());
}

?>

<h1 class="page-title">Gestión de Impresoras</h1>

<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="message success">
        <?php echo htmlspecialchars($_SESSION['flash_message']); unset($_SESSION['flash_message']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="message error">
        <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
    </div>
<?php endif; ?>

<div class="dashboard-controls">
    <div class="search-bar">
        <input type="text" id="quickSearchImpresoraInput" placeholder="Buscar por nombre, marca, modelo, IP, estado..." class="search-input">
    </div>
    <div class="add-button-container">
        <a href="add_impresora.php" class="button primary"><i class="fas fa-plus"></i> Añadir Nueva Impresora</a>
    </div>
</div>

<?php if ($error_fetching_impresoras): ?>
    <p class="message error"><?php echo htmlspecialchars($error_fetching_impresoras); ?></p>
<?php endif; ?>

<?php if (empty($impresoras) && $total_impresoras == 0): ?>
    <p class="no-records" id="noRecordsMessage">No hay impresoras registradas en el sistema.</p>
<?php elseif (empty($impresoras) && $total_impresoras > 0): ?>
    <p class="no-records" id="noRecordsMessage">No hay impresoras en esta página. Es posible que hayas accedido a una página que ya no existe.</p>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Conexión</th>
                    <th>IP</th>
                    <th>Ubicación</th>
                    <th>Estado</th>
                    <th>Consumible</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="impresorasTableBody">
                <?php foreach ($impresoras as $impresora): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($impresora['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($impresora['marca']); ?></td>
                        <td><?php echo htmlspecialchars($impresora['modelo']); ?></td>
                        <td><?php echo htmlspecialchars($impresora['tipo_conexion']); ?></td>
                        <td><?php echo htmlspecialchars($impresora['ip_address'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($impresora['ubicacion']); ?></td>
                        <td><?php echo htmlspecialchars($impresora['estado']); ?></td>
                        <td><?php echo htmlspecialchars($impresora['consumible_tipo'] ?? 'N/A'); ?></td>
                        <td class="actions-column">
                            <?php if (!empty($impresora['url_interfaz'])): ?>
                                <a href="<?php echo htmlspecialchars($impresora['url_interfaz']); ?>" target="_blank" class="button secondary small" title="Ir a Interfaz">
                                    <i class="fas fa-external-link-alt"></i> </a>
                            <?php endif; ?>
                            <a href="edit_impresora.php?id=<?php echo htmlspecialchars($impresora['id']); ?>" class="button secondary small" title="Editar Impresora">
                                <i class="fas fa-edit"></i> </a>
                            <a href="impresora_historial.php?id=<?php echo htmlspecialchars($impresora['id']); ?>" class="button info small" title="Ver Historial">
                                <i class="fas fa-history"></i> </a>
                            <a href="impresoras.php?action=delete_impresora&id=<?php echo htmlspecialchars($impresora['id']); ?>"
                               onclick="return confirm('¿Está seguro de que desea eliminar la impresora <?php echo htmlspecialchars($impresora['nombre']); ?>? Esta acción es irreversible.');"
                               class="button button-small danger" title="Eliminar Impresora">
                               <i class="fas fa-trash"></i> </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-controls" id="paginationControls">
        <?php if ($total_pages > 1): ?>
            <p>Página <?php echo $current_page; ?> de <?php echo $total_pages; ?> (Total: <?php echo $total_impresoras; ?> impresoras)</p>
            <div class="pagination-buttons">
                <?php if ($current_page > 1): ?>
                    <a href="impresoras.php?page=<?php echo $current_page - 1; ?>" class="button secondary">&laquo; Anterior</a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);

                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="impresoras.php?page=<?php echo $i; ?>" class="button <?php echo ($i == $current_page) ? 'primary' : 'secondary'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="impresoras.php?page=<?php echo $current_page + 1; ?>" class="button secondary">Siguiente &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quickSearchImpresoraInput = document.getElementById('quickSearchImpresoraInput');
    const dataTable = document.querySelector('.data-table');
    const paginationControls = document.getElementById('paginationControls');
    const noRecordsMessage = document.getElementById('noRecordsMessage');

    if (!dataTable) {
        console.warn("No se encontró la tabla de datos (.data-table) para la búsqueda rápida de impresoras.");
        return;
    }

    const tableRows = dataTable.querySelectorAll('tbody tr');

    quickSearchImpresoraInput.addEventListener('keyup', function() {
        const searchTerm = quickSearchImpresoraInput.value.toLowerCase().trim();

        let foundResults = false;

        tableRows.forEach(row => {
            let rowText = '';
            row.querySelectorAll('td').forEach(cell => {
                if (!cell.classList.contains('actions-column')) {
                    rowText += cell.textContent.toLowerCase() + ' ';
                }
            });

            if (rowText.includes(searchTerm)) {
                row.style.display = '';
                foundResults = true;
            } else {
                row.style.display = 'none';
            }
        });

        if (searchTerm !== '') {
            if (paginationControls) {
                paginationControls.style.display = 'none';
            }
            if (noRecordsMessage) {
                if (foundResults) {
                    noRecordsMessage.style.display = 'none';
                } else {
                    noRecordsMessage.style.display = 'block';
                    noRecordsMessage.innerText = 'No se encontraron impresoras que coincidan con la búsqueda en la página actual.';
                }
            }
        } else {
            if (paginationControls) {
                paginationControls.style.display = 'block';
            }
            if (noRecordsMessage) {
                const initialNoRecordsCondition = <?php echo json_encode(empty($impresoras) && $total_impresoras == 0); ?>;
                const initialNoPageCondition = <?php echo json_encode(empty($impresoras) && $total_impresoras > 0); ?>;

                if (initialNoRecordsCondition) {
                    noRecordsMessage.style.display = 'block';
                    noRecordsMessage.innerText = 'No hay impresoras registradas en el sistema.';
                } else if (initialNoPageCondition) {
                    noRecordsMessage.style.display = 'block';
                    noRecordsMessage.innerText = 'No hay impresoras en esta página. Es posible que hayas accedido a una página que ya no existe.';
                } else {
                    noRecordsMessage.style.display = 'none';
                }
            }
        }
    });

    if (quickSearchImpresoraInput.value.trim() === '') {
        if (paginationControls) {
            paginationControls.style.display = 'block';
        }
        if (noRecordsMessage) {
            const initialNoRecordsCondition = <?php echo json_encode(empty($impresoras) && $total_impresoras == 0); ?>;
            const initialNoPageCondition = <?php echo json_encode(empty($impresoras) && $total_impresoras > 0); ?>;

            if (initialNoRecordsCondition) {
                noRecordsMessage.style.display = 'block';
                noRecordsMessage.innerText = 'No hay impresoras registradas en el sistema.';
            } else if (initialNoPageCondition) {
                noRecordsMessage.style.display = 'block';
                noRecordsMessage.innerText = 'No hay impresoras en esta página. Es posible que hayas accedido a una página que ya no existe.';
            } else {
                noRecordsMessage.style.display = 'none';
            }
        }
    }
});
</script>

<?php
// Incluir el archivo de pie de página
require_once 'includes/footer.php';
?>