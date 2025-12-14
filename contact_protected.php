<?php
/**
 * MNCApp Contact Form - Sistema Anti-Spam Completo
 * Sin dependencias externas - Funciona en cualquier hosting
 */

// Headers CORS
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================================
// CONFIGURACIÓN
// ============================================================================

// Google reCAPTCHA
define('RECAPTCHA_SECRET_KEY', '6LeCrgIsAAAAAMaeViEjn4KIpDgojI6Zd1511SjS');
define('RECAPTCHA_MIN_SCORE', 0.5); // Score mínimo aceptable (0.0 - 1.0)

// Email Configuration
define('SMTP_HOST', 'mail.mncapp.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'info@mncapp.com');
define('SMTP_PASSWORD', 'Info2024');
define('EMAIL_FROM', 'info@mncapp.com');
define('EMAIL_TO', 'info@mncapp.com');

// Rate Limiting
define('RATE_LIMIT_MAX', 5); // Máximo de mensajes
define('RATE_LIMIT_WINDOW', 3600); // Por hora (en segundos)

// Spam Detection
define('SPAM_THRESHOLD', 0.5); // Umbral de spam (0.0 - 1.0)

// Archivo para guardar rate limiting (crear carpeta temp/ con permisos de escritura)
define('RATE_LIMIT_FILE', __DIR__ . '/temp/rate_limit.json');

// ============================================================================
// FUNCIONES DE PROTECCIÓN ANTI-SPAM
// ============================================================================

/**
 * Verificar Google reCAPTCHA v3
 */
function verify_recaptcha($token) {
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = array(
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $token
    );
    
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        )
    );
    
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        return array('success' => false, 'score' => 0.0);
    }
    
    return json_decode($result, true);
}

/**
 * Obtener IP del cliente
 */
function get_client_ip() {
    $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                     'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Rate Limiting - Verificar intentos por IP
 */
function check_rate_limit($ip) {
    // Crear directorio temp si no existe
    $temp_dir = dirname(RATE_LIMIT_FILE);
    if (!file_exists($temp_dir)) {
        @mkdir($temp_dir, 0755, true);
    }
    
    $current_time = time();
    $rate_data = array();
    
    // Leer datos existentes
    if (file_exists(RATE_LIMIT_FILE)) {
        $content = @file_get_contents(RATE_LIMIT_FILE);
        if ($content) {
            $rate_data = json_decode($content, true) ?: array();
        }
    }
    
    // Limpiar entradas antiguas
    if (isset($rate_data[$ip])) {
        $rate_data[$ip] = array_filter($rate_data[$ip], function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < RATE_LIMIT_WINDOW;
        });
    } else {
        $rate_data[$ip] = array();
    }
    
    // Verificar límite
    if (count($rate_data[$ip]) >= RATE_LIMIT_MAX) {
        return false;
    }
    
    // Agregar intento actual
    $rate_data[$ip][] = $current_time;
    
    // Guardar
    @file_put_contents(RATE_LIMIT_FILE, json_encode($rate_data));
    
    return true;
}

/**
 * Calcular spam score del mensaje
 */
function calculate_spam_score($nombre, $correo, $mensaje) {
    $score = 0.0;
    
    // Patrones de spam comunes
    $spam_patterns = array(
        '/viagra|cialis|pharmacy|casino|lottery|winner/i' => 0.3,
        '/click here|buy now|limited time|act now/i' => 0.2,
        '/crypto|bitcoin|forex|investment opportunity/i' => 0.3,
        '/http[s]?:\/\//i' => 0.2, // URLs
        '/\$\$|€€|££|money|cash|prize/i' => 0.15,
    );
    
    foreach ($spam_patterns as $pattern => $weight) {
        if (preg_match($pattern, $mensaje)) {
            $score += $weight;
        }
    }
    
    // Verificar URLs excesivas
    $url_count = preg_match_all('/http[s]?:\/\//', $mensaje);
    if ($url_count > 2) {
        $score += 0.2;
    }
    
    // Emails desechables
    $disposable_domains = array('tempmail', 'disposable', '10minute', 'guerrilla', 'throwaway');
    foreach ($disposable_domains as $domain) {
        if (stripos($correo, $domain) !== false) {
            $score += 0.2;
            break;
        }
    }
    
    // Mensaje muy corto
    if (str_word_count($mensaje) < 5) {
        $score += 0.1;
    }
    
    // Caracteres repetitivos
    if (preg_match('/(.)\1{4,}/', $mensaje)) {
        $score += 0.15;
    }
    
    // Nombre sospechoso (solo letras repetidas o números)
    if (preg_match('/^(.)\1+$/', $nombre) || preg_match('/^\d+$/', $nombre)) {
        $score += 0.2;
    }
    
    return min($score, 1.0);
}

/**
 * Enviar email usando SMTP
 */
function send_email($to, $subject, $html_body, $from_email = null, $from_name = 'MNCApp') {
    $from_email = $from_email ?: EMAIL_FROM;
    
    // Headers para email HTML
    $headers = array(
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    );
    
    // Intentar envío con mail()
    $success = @mail($to, $subject, $html_body, implode("\r\n", $headers));
    
    return $success;
}

/**
 * Guardar mensaje en log (opcional)
 */
function log_message($data) {
    $log_file = __DIR__ . '/temp/messages_log.json';
    $temp_dir = dirname($log_file);
    
    if (!file_exists($temp_dir)) {
        @mkdir($temp_dir, 0755, true);
    }
    
    $logs = array();
    if (file_exists($log_file)) {
        $content = @file_get_contents($log_file);
        if ($content) {
            $logs = json_decode($content, true) ?: array();
        }
    }
    
    $logs[] = array_merge($data, array('timestamp' => date('Y-m-d H:i:s')));
    
    // Mantener solo los últimos 100 mensajes
    if (count($logs) > 100) {
        $logs = array_slice($logs, -100);
    }
    
    @file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
}

// ============================================================================
// PROCESAMIENTO DEL FORMULARIO
// ============================================================================

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    // Intentar con POST tradicional
    $data = $_POST;
}

// Validar campos requeridos
$nombre = trim($data['nombre'] ?? '');
$correo = trim($data['correo'] ?? '');
$mensaje = trim($data['mensaje'] ?? '');
$recaptcha_token = trim($data['recaptcha_token'] ?? '');
$honeypot = trim($data['honeypot'] ?? '');
$timestamp_field = trim($data['timestamp_field'] ?? '');

// Obtener IP del cliente
$client_ip = get_client_ip();

// PROTECCIÓN 1: Rate Limiting
if (!check_rate_limit($client_ip)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Demasiados intentos. Por favor, intenta más tarde.'
    ]);
    exit;
}

// PROTECCIÓN 2: Honeypot
if (!empty($honeypot)) {
    // Bot detectado - simular éxito pero no enviar email
    log_message(array(
        'type' => 'spam_honeypot',
        'ip' => $client_ip,
        'nombre' => $nombre,
        'correo' => $correo
    ));
    
    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado correctamente',
        'spam_blocked' => true
    ]);
    exit;
}

// PROTECCIÓN 3: Timestamp (formulario llenado muy rápido)
if (!empty($timestamp_field)) {
    $time_taken = time() - floatval($timestamp_field);
    if ($time_taken < 3) {
        // Llenado demasiado rápido - posible bot
        log_message(array(
            'type' => 'spam_fast_fill',
            'ip' => $client_ip,
            'time_taken' => $time_taken
        ));
        
        echo json_encode([
            'success' => true,
            'message' => 'Mensaje enviado correctamente',
            'spam_blocked' => true
        ]);
        exit;
    }
}

// Validación básica
if (empty($nombre) || empty($correo) || empty($mensaje)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Todos los campos son obligatorios'
    ]);
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email inválido'
    ]);
    exit;
}

// PROTECCIÓN 4: Google reCAPTCHA v3
if (!empty($recaptcha_token)) {
    $recaptcha_result = verify_recaptcha($recaptcha_token);
    
    if (!$recaptcha_result['success']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Verificación de seguridad fallida. Por favor, intenta de nuevo.'
        ]);
        exit;
    }
    
    // Verificar score de reCAPTCHA
    $recaptcha_score = $recaptcha_result['score'] ?? 0.0;
    if ($recaptcha_score < RECAPTCHA_MIN_SCORE) {
        log_message(array(
            'type' => 'spam_recaptcha',
            'ip' => $client_ip,
            'score' => $recaptcha_score,
            'nombre' => $nombre
        ));
        
        echo json_encode([
            'success' => true,
            'message' => 'Mensaje enviado correctamente',
            'spam_blocked' => true
        ]);
        exit;
    }
}

// PROTECCIÓN 5: Análisis de contenido spam
$spam_score = calculate_spam_score($nombre, $correo, $mensaje);
$is_spam = $spam_score > SPAM_THRESHOLD;

if ($is_spam) {
    log_message(array(
        'type' => 'spam_content',
        'ip' => $client_ip,
        'spam_score' => $spam_score,
        'nombre' => $nombre,
        'correo' => $correo
    ));
    
    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado correctamente',
        'spam_blocked' => true
    ]);
    exit;
}

// ============================================================================
// MENSAJE LEGÍTIMO - ENVIAR EMAILS
// ============================================================================

$fecha = date('d/m/Y H:i');

// Email para ti
$html_message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
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
                <p><strong>IP:</strong> $client_ip</p>
                <p><strong>Spam Score:</strong> " . round($spam_score * 100) . "%</p>
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

$subject = 'Nuevo contacto desde mncapp.com - ' . $nombre;
$email_sent = send_email(EMAIL_TO, $subject, $html_message);

// Email de confirmación al cliente
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

$confirm_subject = 'Gracias por contactar a MNCApp - Hemos recibido tu mensaje';
@send_email($correo, $confirm_subject, $confirm_message);

// Log del mensaje legítimo
log_message(array(
    'type' => 'legitimate',
    'ip' => $client_ip,
    'nombre' => $nombre,
    'correo' => $correo,
    'spam_score' => $spam_score,
    'email_sent' => $email_sent
));

// Respuesta de éxito
echo json_encode([
    'success' => true,
    'message' => '¡Mensaje enviado correctamente! Te contactaremos pronto.',
    'spam_blocked' => false,
    'email_sent' => $email_sent
]);

?>
