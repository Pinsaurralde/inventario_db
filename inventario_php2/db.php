<?php
// db.php - Configuración de la conexión a la base de datos

$host = 'localhost';
$db   = 'stock_php';
$user = 'root'; // Usuario por defecto de XAMPP
$pass = '';     // Contraseña por defecto de XAMPP (vacío)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Reportar errores como excepciones
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Obtener resultados como array asociativo
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactivar emulación de preparaciones para seguridad real
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Esto es para depuración. En un entorno de producción, es mejor
    // mostrar un mensaje genérico y loguear el error.
    error_log("Error de conexión a la base de datos: " . $e->getMessage()); // Registrar el error
    die("Error en la base de datos. Por favor, inténtalo de nuevo más tarde.");
}
?>