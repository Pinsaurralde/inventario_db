<?php
// equipos_informaticos.php

// Definir el título de la página
$page_title = "Inventario de Equipos Informáticos - Sistema de Inventario";

// Incluir el archivo de cabecera que maneja la sesión y la conexión a DB (a través de db.php)
require_once 'includes/header.php';

// Redirige si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Debes iniciar sesión para acceder al inventario de equipos informáticos.";
    header("Location: login.php");
    exit();
}

// --- Lógica para ELIMINAR un equipo (al principio del script, antes del HTML) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete_equipo' && isset($_GET['id'])) {
    $equipo_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($equipo_id_to_delete) {
        try {
            // Iniciar una transacción para asegurar la integridad de los datos
            $pdo->beginTransaction();

            // Opcional: Eliminar registros relacionados en la tabla de historial primero
            // Si tienes una tabla 'equipos_historial', descomenta esto:
            // $stmt_historial_delete = $pdo->prepare("DELETE FROM equipos_historial WHERE equipo_id = ?");
            // $stmt_historial_delete->bindParam(1, $equipo_id_to_delete, PDO::PARAM_INT);
            // $stmt_historial_delete->execute();

            // Eliminar el equipo
            $stmt_delete = $pdo->prepare("DELETE FROM equipos_informaticos WHERE id = ?");
            $stmt_delete->bindParam(1, $equipo_id_to_delete, PDO::PARAM_INT);
            $stmt_delete->execute();

            $pdo->commit(); // Confirmar la transacción

            if ($stmt_delete->rowCount() > 0) {
                $_SESSION['flash_message'] = "Equipo eliminado exitosamente.";
            } else {
                $_SESSION['flash_error'] = "No se pudo encontrar el equipo para eliminar o ya fue eliminado.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack(); // Revertir la transacción en caso de error
            error_log("Error al eliminar equipo: " . $e->getMessage());
            $_SESSION['flash_error'] = "Error de base de datos al eliminar el equipo: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "ID de equipo no válido para la eliminación.";
    }
    header("Location: equipos_informaticos.php"); // Redirige para limpiar la URL
    exit();
}


// --- CONFIGURACIÓN DE PAGINACIÓN (Ahora la paginación se aplicará para la carga inicial) ---
// Definición clara al principio para evitar 'Undefined variable'
$records_per_page = 10;
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if (!$current_page || $current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

// --- Lógica para obtener el TOTAL de equipos para la paginación ---
$total_equipos = 0;
try {
    // $pdo ya está disponible aquí gracias a la inclusión de header.php
    $stmt_count = $pdo->query("SELECT COUNT(*) FROM equipos_informaticos");
    $total_equipos = $stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Error al contar equipos para paginación: " . $e->getMessage());
    $_SESSION['flash_error'] = "Error al contar equipos para paginación.";
}
$total_pages = ceil($total_equipos / $records_per_page);

// --- Lógica para obtener los equipos informáticos de la base de datos (con LIMIT y OFFSET) ---
// NOTA: Para que la búsqueda en el cliente funcione bien,
// podríamos cargar TODOS los equipos si el inventario no es demasiado grande.
// Si el inventario es MUY grande (miles de registros), esta estrategia no es eficiente.
// Por ahora, mantendremos la paginación para la carga inicial, y la búsqueda filtrará solo lo visible.
// Si deseas buscar en TODO el inventario sin paginación para el filtrado,
// tendrías que quitar el LIMIT y OFFSET de la consulta de abajo, y cargar todos los equipos.
$equipos_informaticos = [];
$error_fetching_equipos = '';

try {
    $stmt = $pdo->prepare("SELECT id, nombre, tipo, marca, modelo, num_serie, patrimonio, usuario_asignado, estado, ubicacion FROM equipos_informaticos ORDER BY tipo, marca, modelo LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $equipos_informaticos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_fetching_equipos = "Error al cargar la lista de equipos informáticos: " . $e->getMessage();
    error_log("Error al cargar equipos informáticos en equipos_informaticos.php: " . $e->getMessage());
}

?>

<h1 class="page-title">Inventario de Equipos Informáticos</h1>

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
        <input type="text" id="quickSearchEquipoInput" placeholder="Buscar por nombre, tipo, serie, patrimonio, usuario..." class="search-input">
    </div>
    <div class="add-button-container">
        <a href="add_equipo_informatico.php" class="button primary"><i class="fas fa-plus"></i> Añadir Nuevo Equipo</a>
    </div>
</div>

<?php if ($error_fetching_equipos): ?>
    <p class="message error"><?php echo htmlspecialchars($error_fetching_equipos); ?></p>
<?php endif; ?>

<?php if (empty($equipos_informaticos) && $total_equipos == 0): ?>
    <p class="no-records" id="noRecordsMessage">No hay equipos informáticos registrados en el sistema.</p>
<?php elseif (empty($equipos_informaticos) && $total_equipos > 0): ?>
    <p class="no-records" id="noRecordsMessage">No hay equipos en esta página. Es posible que hayas accedido a una página que ya no existe.</p>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Nro. Serie</th>
                    <th>Patrimonio</th>
                    <th>Usuario Asignado</th>
                    <th>Estado</th>
                    <th>Ubicación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="equiposTableBody">
                <?php foreach ($equipos_informaticos as $equipo): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($equipo['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($equipo['tipo']); ?></td>
                        <td><?php echo htmlspecialchars($equipo['marca']); ?></td>
                        <td><?php echo htmlspecialchars($equipo['modelo']); ?></td>
                        <td><?php echo htmlspecialchars($equipo['num_serie']); ?></td>
                        <td><?php echo htmlspecialchars($equipo['patrimonio'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($equipo['usuario_asignado'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($equipo['estado']); ?></td>
                        <td><?php echo htmlspecialchars($equipo['ubicacion'] ?? 'N/A'); ?></td>
                        <td class="actions-column">
                            <a href="equipo_historial.php?id=<?php echo htmlspecialchars($equipo['id']); ?>" class="button button-small info">Historial</a>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-controls" id="paginationControls">
        <?php if ($total_pages > 1): ?>
            <p>Página <?php echo $current_page; ?> de <?php echo $total_pages; ?> (Total: <?php echo $total_equipos; ?> equipos)</p>
            <div class="pagination-buttons">
                <?php if ($current_page > 1): ?>
                    <a href="equipos_informaticos.php?page=<?php echo $current_page - 1; ?>" class="button secondary">&laquo; Anterior</a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);

                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="equipos_informaticos.php?page=<?php echo $i; ?>" class="button <?php echo ($i == $current_page) ? 'primary' : 'secondary'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="equipos_informaticos.php?page=<?php echo $current_page + 1; ?>" class="button secondary">Siguiente &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Usamos el nuevo ID para el campo de búsqueda
    const quickSearchEquipoInput = document.getElementById('quickSearchEquipoInput');
    const dataTable = document.querySelector('.data-table');
    const paginationControls = document.getElementById('paginationControls');
    const noRecordsMessage = document.getElementById('noRecordsMessage');

    // Si no hay tabla, salimos
    if (!dataTable) {
        console.warn("No se encontró la tabla de datos (.data-table) para la búsqueda rápida.");
        return;
    }

    // Obtenemos todas las filas del cuerpo de la tabla
    const tableRows = dataTable.querySelectorAll('tbody tr');

    quickSearchEquipoInput.addEventListener('keyup', function() {
        const searchTerm = quickSearchEquipoInput.value.toLowerCase().trim();

        let foundResults = false; // Bandera para saber si se encontró algún resultado

        tableRows.forEach(row => {
            let rowText = '';
            // Iteramos sobre cada celda de la fila
            row.querySelectorAll('td').forEach(cell => {
                // Excluimos la columna de acciones de la búsqueda para evitar falsos positivos
                if (!cell.classList.contains('actions-column')) {
                    rowText += cell.textContent.toLowerCase() + ' ';
                }
            });

            if (rowText.includes(searchTerm)) {
                row.style.display = ''; // Mostrar fila si coincide
                foundResults = true;
            } else {
                row.style.display = 'none'; // Ocultar fila si no coincide
            }
        });

        // Lógica para mostrar/ocultar paginación y mensaje de "no resultados"
        if (searchTerm !== '') {
            if (paginationControls) {
                paginationControls.style.display = 'none'; // Ocultar paginación durante la búsqueda
            }
            if (noRecordsMessage) {
                if (foundResults) {
                    noRecordsMessage.style.display = 'none'; // Ocultar mensaje si hay resultados
                } else {
                    noRecordsMessage.style.display = 'block'; // Mostrar mensaje si no hay resultados
                    noRecordsMessage.innerText = 'No se encontraron equipos que coincidan con la búsqueda en la página actual.';
                }
            }
        } else {
            // Cuando el campo de búsqueda está vacío, volvemos al estado inicial
            if (paginationControls) {
                paginationControls.style.display = 'block'; // Mostrar paginación
            }
            if (noRecordsMessage) {
                // Revertir a los mensajes iniciales de paginación o "no hay registros"
                const initialNoRecordsCondition = <?php echo json_encode(empty($equipos_informaticos) && $total_equipos == 0); ?>;
                const initialNoPageCondition = <?php echo json_encode(empty($equipos_informaticos) && $total_equipos > 0); ?>;

                if (initialNoRecordsCondition) {
                    noRecordsMessage.style.display = 'block';
                    noRecordsMessage.innerText = 'No hay equipos informáticos registrados en el sistema.';
                } else if (initialNoPageCondition) {
                    noRecordsMessage.style.display = 'block';
                    noRecordsMessage.innerText = 'No hay equipos en esta página. Es posible que hayas accedido a una página que ya no existe.';
                } else {
                    noRecordsMessage.style.display = 'none';
                }
            }
        }
    });

    // Restaurar el estado inicial al cargar la página si el campo de búsqueda está vacío
    if (quickSearchEquipoInput.value.trim() === '') {
        if (paginationControls) {
            paginationControls.style.display = 'block';
        }
        if (noRecordsMessage) {
            const initialNoRecordsCondition = <?php echo json_encode(empty($equipos_informaticos) && $total_equipos == 0); ?>;
            const initialNoPageCondition = <?php echo json_encode(empty($equipos_informaticos) && $total_equipos > 0); ?>;

            if (initialNoRecordsCondition) {
                noRecordsMessage.style.display = 'block';
                noRecordsMessage.innerText = 'No hay equipos informáticos registrados en el sistema.';
            } else if (initialNoPageCondition) {
                noRecordsMessage.style.display = 'block';
                noRecordsMessage.innerText = 'No hay equipos en esta página. Es posible que hayas accedido a una página que ya no existe.';
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