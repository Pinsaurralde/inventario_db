<?php
// generate_hash.php

$password = 'Info1620';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "Contraseña original: " . $password . "<br>";
echo "Hash generado: " . $hashed_password . "<br><br>";

// Opcional: Verifica si el hash funciona
if (password_verify($password, $hashed_password)) {
    echo "¡El hash generado VERIFICA correctamente!";
} else {
    echo "¡Error: El hash generado NO verifica correctamente!";
}
?>