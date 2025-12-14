<?php
// contact.php - Procesador de formulario para MNCApp
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos del formulario
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Fallback para formularios HTML estándar
    $input = [
        'nombre' => $_POST['nombre'] ?? '',
        'correo' => $_POST['correo'] ?? '', 
        'mensaje' => $_POST['mensaje'] ?? ''
    ];
}

$nombre = trim($input['nombre'] ?? '');
$correo = trim($input['correo'] ?? '');
$mensaje = trim($input['mensaje'] ?? '');

// Validación
if (empty($nombre) || empty($correo) || empty($mensaje)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

// Configuración de email
$to = 'info@mncapp.com';
$subject = 'Nuevo contacto desde mncapp.com - ' . $nombre;

// Crear mensaje HTML
$htmlMessage = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #FF6B35 0%, #E55A2B 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .header h1 { color: white; margin: 0; font-size: 24px; }
        .header p { color: white; margin: 10px 0 0 0; opacity: 0.9; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef; }
        .info-box { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .info-title { color: #FF6B35; margin: 0 0 15px 0; font-size: 18px; }
        .message-box { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #FF6B35; }
        .button { background: #FF6B35; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Nuevo Mensaje de Contacto</h1>
            <p>mncapp.com</p>
        </div>
        
        <div class='content'>
            <div class='info-box'>
                <h3 class='info-title'>Información del Cliente</h3>
                <p><strong>Nombre:</strong> " . htmlspecialchars($nombre) . "</p>
                <p><strong>Correo:</strong> <a href='mailto:" . htmlspecialchars($correo) . "' style='color: #FF6B35;'>" . htmlspecialchars($correo) . "</a></p>
                <p><strong>Fecha:</strong> " . date('d/m/Y H:i') . "</p>
            </div>
            
            <div class='info-box'>
                <h3 class='info-title'>Mensaje</h3>
                <div class='message-box'>
                    <p>" . nl2br(htmlspecialchars($mensaje)) . "</p>
                </div>
            </div>
            
            <div style='text-align: center; margin-top: 20px;'>
                <a href='mailto:" . htmlspecialchars($correo) . "' class='button'>Responder Cliente</a>
            </div>
        </div>
    </div>
</body>
</html>
";

// Crear mensaje de texto plano
$textMessage = "
Nuevo Mensaje de Contacto - MNCApp

Información del Cliente:
- Nombre: $nombre
- Correo: $correo  
- Fecha: " . date('d/m/Y H:i') . "

Mensaje:
$mensaje

---
Para responder, envía un email a: $correo
";

// Headers del email
$headers = [
    'From: MNCApp <info@mncapp.com>',
    'Reply-To: ' . $correo,
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion()
];

// Enviar email principal
$emailSent = mail($to, $subject, $htmlMessage, implode("\r\n", $headers));

// Enviar email de confirmación al cliente
if ($emailSent) {
    $confirmSubject = 'Gracias por contactar a MNCApp - Hemos recibido tu mensaje';
    $confirmMessage = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #FF6B35 0%, #E55A2B 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .header h1 { color: white; margin: 0; font-size: 24px; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e9ecef; }
            .message-box { background: white; padding: 25px; border-radius: 8px; text-align: center; }
            .highlight-box { background: #FF6B35; padding: 20px; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>¡Gracias por contactarnos!</h1>
                <p>MNCApp - Soluciones Digitales</p>
            </div>
            
            <div class='content'>
                <div class='message-box'>
                    <h2 style='color: #333; margin: 0 0 20px 0;'>Hola " . htmlspecialchars($nombre) . ",</h2>
                    <p>Hemos recibido tu mensaje y nos pondremos en contacto contigo en las próximas <strong>24 horas</strong>.</p>
                    <p style='color: #666;'>Nuestro equipo revisará tu consulta y te responderá con la información que necesitas.</p>
                    
                    <div class='highlight-box'>
                        <p style='color: white; margin: 0; font-weight: bold;'>¿Necesitas una respuesta más rápida?</p>
                        <p style='color: white; margin: 10px 0 0 0; opacity: 0.9;'>Escríbenos por WhatsApp: <strong>809-939-6422</strong></p>
                    </div>
                </div>
                
                <div style='text-align: center; margin-top: 20px;'>
                    <p style='color: #666; font-size: 14px;'>
                        <strong>MNCApp</strong><br>
                        Transformamos ideas en aplicaciones y soluciones digitales<br>
                        info@mncapp.com | WhatsApp: 809-939-6422
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $confirmHeaders = [
        'From: MNCApp <info@mncapp.com>',
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    mail($correo, $confirmSubject, $confirmMessage, implode("\r\n", $confirmHeaders));
}

// Respuesta JSON
if ($emailSent) {
    echo json_encode([
        'success' => true, 
        'message' => 'Mensaje enviado correctamente. Nos pondremos en contacto contigo pronto.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error al enviar el mensaje. Por favor, contáctanos directamente.'
    ]);
}
?>