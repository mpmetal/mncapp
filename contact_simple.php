<?php
// contact_simple.php - CONFIGURACIÓN CORRECTA PARA MNCAPP

// Headers para evitar errores CORS y de contenido
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Si es una solicitud OPTIONS (preflight), responder y terminar
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Función para logs de depuración
function logDebug($message) {
    error_log(date('Y-m-d H:i:s') . " - MNCApp Contact: " . $message);
}

logDebug("=== INICIO DE PROCESAMIENTO ===");
logDebug("Método: " . $_SERVER['REQUEST_METHOD']);

// Solo procesar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos del formulario
$nombre = '';
$correo = '';
$mensaje = '';

// Intentar obtener datos de diferentes formas
if (isset($_POST['nombre'], $_POST['correo'], $_POST['mensaje'])) {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $mensaje = trim($_POST['mensaje']);
    logDebug("Datos obtenidos via POST");
} else {
    // Intentar JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if ($data && isset($data['nombre'], $data['correo'], $data['mensaje'])) {
        $nombre = trim($data['nombre']);
        $correo = trim($data['correo']);
        $mensaje = trim($data['mensaje']);
        logDebug("Datos obtenidos via JSON");
    }
}

logDebug("Nombre: $nombre, Correo: $correo, Mensaje: " . substr($mensaje, 0, 50) . "...");

// Validación
if (empty($nombre) || empty($correo) || empty($mensaje)) {
    logDebug("ERROR: Campos vacíos");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    logDebug("ERROR: Email inválido: $correo");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

// CONFIGURACIÓN DE EMAIL CON TUS CREDENCIALES CORRECTAS
$smtp_host = 'mail.mncapp.com';
$smtp_port = 465;
$smtp_user = 'info@mncapp.com';
$smtp_pass = 'Info2024'; // CONTRASEÑA CORRECTA
$email_from = 'info@mncapp.com';

logDebug("Configuración SMTP: $smtp_host:$smtp_port con usuario $smtp_user");

// Preparar email
$to = 'info@mncapp.com';
$subject = 'Nuevo contacto desde mncapp.com - ' . $nombre;
$fecha = date('d/m/Y H:i');

// Crear mensaje HTML profesional
$html_message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #FF6B35 0%, #E55A2B 100%); padding: 30px; text-align: center; }
        .header h1 { color: white; margin: 0; font-size: 24px; }
        .content { background: #f8f9fa; padding: 30px; }
        .info-box { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; }
        .info-title { color: #FF6B35; font-size: 18px; margin-bottom: 15px; }
        .message-box { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #FF6B35; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Nuevo Mensaje de Contacto</h1>
            <p style='color: white; margin: 10px 0 0 0; opacity: 0.9;'>mncapp.com</p>
        </div>
        
        <div class='content'>
            <div class='info-box'>
                <h3 class='info-title'>Información del Cliente</h3>
                <p><strong>Nombre:</strong> " . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "</p>
                <p><strong>Correo:</strong> <a href='mailto:" . htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') . "' style='color: #FF6B35;'>" . htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') . "</a></p>
                <p><strong>Fecha:</strong> $fecha</p>
            </div>
            
            <div class='info-box'>
                <h3 class='info-title'>Mensaje</h3>
                <div class='message-box'>
                    <p style='margin: 0; white-space: pre-wrap;'>" . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . "</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
";

// Mensaje de texto plano
$text_message = "
Nuevo Mensaje de Contacto - MNCApp

Información del Cliente:
- Nombre: $nombre
- Correo: $correo  
- Fecha: $fecha

Mensaje:
$mensaje

---
Para responder, envía un email a: $correo
";

// Intentar enviar con mail() básico primero
$headers = array(
    'From: MNCApp <info@mncapp.com>',
    'Reply-To: ' . $correo,
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion()
);

logDebug("Intentando envío con mail() básico");
$mail_sent = @mail($to, $subject, $html_message, implode("\r\n", $headers));

if ($mail_sent) {
    logDebug("Email enviado exitosamente con mail() básico");
    
    // Enviar confirmación al cliente
    $confirm_subject = 'Gracias por contactar a MNCApp - Hemos recibido tu mensaje';
    $confirm_message = "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #FF6B35 0%, #E55A2B 100%); padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0;'>¡Gracias por contactarnos!</h1>
                <p style='color: white; opacity: 0.9;'>MNCApp - Soluciones Digitales</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px;'>
                <div style='background: white; padding: 25px; text-align: center;'>
                    <h2 style='color: #333; margin: 0 0 20px 0;'>Hola " . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . ",</h2>
                    <p>Hemos recibido tu mensaje y nos pondremos en contacto contigo en las próximas <strong>24 horas</strong>.</p>
                    <p style='color: #666;'>Nuestro equipo revisará tu consulta y te responderá con la información que necesitas.</p>
                    
                    <div style='background: #FF6B35; padding: 20px; margin: 20px 0; color: white;'>
                        <p style='margin: 0; font-weight: bold;'>¿Necesitas una respuesta más rápida?</p>
                        <p style='margin: 10px 0 0 0;'>Escríbenos por WhatsApp: <strong>809-939-6422</strong></p>
                    </div>
                </div>
                
                <div style='text-align: center; margin-top: 20px; color: #666; font-size: 14px;'>
                    <strong>MNCApp</strong><br>
                    Transformamos ideas en aplicaciones y soluciones digitales<br>
                    info@mncapp.com | WhatsApp: 809-939-6422
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $confirm_headers = array(
        'From: MNCApp <info@mncapp.com>',
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8'
    );
    
    @mail($correo, $confirm_subject, $confirm_message, implode("\r\n", $confirm_headers));
    logDebug("Email de confirmación enviado a: $correo");
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true, 
        'message' => 'Mensaje enviado correctamente. Nos pondremos en contacto contigo pronto.'
    ]);
    
} else {
    logDebug("ERROR: No se pudo enviar con mail() básico");
    
    // Si falla el envío básico, intentar configuración SMTP manual
    logDebug("Intentando configuración SMTP avanzada...");
    
    // Aquí podrías implementar PHPMailer o similar si es necesario
    // Por ahora, devolver error para diagnosticar
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error temporal del servidor de email. Por favor contacta directamente a info@mncapp.com o WhatsApp: 809-939-6422'
    ]);
}

logDebug("=== FIN DE PROCESAMIENTO ===");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Contacto - MNCApp</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <h2>Resultado del Contacto</h2>
    
    <?php if ($message): ?>
        <div style="padding: 15px; margin: 20px 0; border-radius: 5px; <?php echo $success ? 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <p><a href="/" style="color: #FF6B35;">← Volver al sitio principal</a></p>
    
    <script>
    // Redirigir automáticamente después de 3 segundos si fue exitoso
    <?php if ($success): ?>
        setTimeout(function() {
            window.location.href = '/';
        }, 3000);
    <?php endif; ?>
    </script>
</body>
</html>