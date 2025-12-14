<?php
/**
 * Archivo de prueba para verificar que PHP funciona correctamente
 * Subir junto con contact_protected.php
 */

header('Content-Type: application/json; charset=UTF-8');

echo json_encode([
    'success' => true,
    'message' => 'PHP está funcionando correctamente',
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'timestamp' => date('Y-m-d H:i:s'),
    'mail_function' => function_exists('mail') ? 'Disponible' : 'No disponible',
    'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Habilitado' : 'Deshabilitado'
]);
?>
