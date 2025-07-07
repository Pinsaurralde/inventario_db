<?php
// dashboard.php

// No necesitas db.php ni session_start() aquí, ni la lógica de redirección,
// ya que todo eso se maneja en header.php.
// Solo necesitas definir el título de la página antes de incluir el header.
$page_title = "Dashboard - Inventario de Insumos";
require_once 'includes/header.php'; // Incluye el header que ya maneja la sesión y DB

// A partir de aquí, el código PHP para obtener los datos de insumos es el mismo
// Obtener todos los insumos, incluyendo el nombre de la categoría
$stmt = $pdo->query("
    SELECT 
        i.id, 
        i.nombre, 
        i.descripcion, -- Añadido para posible uso futuro o consistencia
        i.stock, 
        i.stock_minimo,
        c.nombre AS categoria_nombre 
    FROM 
        insumos i
    LEFT JOIN 
        categorias c ON i.id_categoria = c.id
    ORDER BY i.nombre ASC
");
$insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// TODO: Si tu search bar usa un JS que requiere la URL actual para la búsqueda,
// es posible que quieras mover el JS a un archivo externo y referenciarlo.
// Por ahora, lo dejaremos aquí y se copiará el script de búsqueda también.
?>

<h1 class="page-title">Inventario de Insumos</h1>

<div class="dashboard-controls">
    <div class="search-bar">
        <input type="text" id="quickSearchInput" placeholder="Buscar insumos por nombre..." value="">
    </div>
    <div class="dashboard-actions">
        <a href="add_insumo.php" class="button primary large-button">
            <span class="button-icon">&#43;</span> Añadir Nuevo Insumo
        </a>
    </div>
</div>

<?php if (empty($insumos)): ?>
    <p class="no-records">No hay insumos registrados.</p>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Stock</th>
                    <th>Stock Mínimo</th>
                    <th>Categoria</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($insumos as $insumo): ?>
                    <tr class="<?php echo ($insumo['stock'] <= $insumo['stock_minimo']) ? 'warning-row' : ''; ?>"> <td><?php echo htmlspecialchars($insumo['nombre']); ?></td>
                        <td class="<?php echo ($insumo['stock'] <= $insumo['stock_minimo']) ? 'text-danger' : ''; ?>">
                            <?php echo htmlspecialchars($insumo['stock']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($insumo['stock_minimo']); ?></td>
                        <td><?php echo htmlspecialchars($insumo['categoria_nombre'] ?? 'N/A'); ?></td> 
                        <td class="actions-column">
                            <a href="entrada_insumo.php?id=<?php echo htmlspecialchars($insumo['id']); ?>" class="button success small">Entrada</a>
                            <a href="salida_insumo.php?id=<?php echo htmlspecialchars($insumo['id']); ?>" class="button danger small">Salida</a>
                            <a href="edit_insumo.php?id=<?php echo htmlspecialchars($insumo['id']); ?>" class="button secondary small">Editar</a>
                            <a href="historial_insumo.php?id=<?php echo htmlspecialchars($insumo['id']); ?>" class="button info small">Historial</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quickSearchInput = document.getElementById('quickSearchInput');
    const dataTable = document.querySelector('.data-table');
    
    if (!dataTable) {
        console.warn("No se encontró la tabla de datos (.data-table) para la búsqueda rápida.");
        return; 
    }

    const tableRows = dataTable.querySelectorAll('tbody tr');

    quickSearchInput.addEventListener('keyup', function() {
        const searchTerm = quickSearchInput.value.toLowerCase();

        tableRows.forEach(row => {
            let rowText = '';
            row.querySelectorAll('td').forEach(cell => {
                if (!cell.classList.contains('actions-column')) {
                    rowText += cell.textContent.toLowerCase() + ' ';
                }
            });

            if (rowText.includes(searchTerm)) {
                row.style.display = ''; // Mostrar fila
            } else {
                row.style.display = 'none'; // Ocultar fila
            }
        });
    });
});
</script>

<?php
require_once 'includes/footer.php'; // Incluye el footer al final
?>