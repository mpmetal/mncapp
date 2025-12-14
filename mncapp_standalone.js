// MNCApp - Protected Contact Form (Standalone PHP Version - Fixed)
// Versión con debugging y mejor manejo de rutas

const RECAPTCHA_SITE_KEY = '6LeCrgIsAAAAACWIGrEeRYa6tZFUnRHlPQZ9_r4V';

// Form load timestamp for anti-bot detection
let formLoadTime = Date.now() / 1000;

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 MNCApp Contact Form iniciado');
    
    // Initialize Lucide icons if available
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Set form load timestamp
    const timestampField = document.getElementById('timestamp_field');
    if (timestampField) {
        timestampField.value = formLoadTime;
        console.log('✅ Timestamp field configurado');
    }
    
    // Initialize form submission
    const form = document.getElementById('contactForm');
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
        console.log('✅ Form listener agregado');
    } else {
        console.error('❌ No se encontró el formulario con id="contactForm"');
    }
});

// Handle form submission with reCAPTCHA
async function handleFormSubmit(event) {
    event.preventDefault();
    console.log('📤 Iniciando envío de formulario...');
    
    const form = document.getElementById('contactForm');
    const submitButton = document.getElementById('submitButton');
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    
    // Hide previous messages
    if (successMessage) successMessage.classList.add('hidden');
    if (errorMessage) errorMessage.classList.add('hidden');
    
    // Get form data
    const formData = new FormData(form);
    const data = {
        nombre: formData.get('nombre').trim(),
        correo: formData.get('correo').trim(),
        mensaje: formData.get('mensaje').trim(),
        honeypot: formData.get('website') || '',
        timestamp_field: formData.get('timestamp_field')
    };
    
    console.log('📋 Datos del formulario:', {
        nombre: data.nombre,
        correo: data.correo,
        mensajeLength: data.mensaje.length
    });
    
    // Basic validation
    if (!data.nombre || data.nombre.length < 2) {
        console.warn('⚠️ Validación fallida: nombre muy corto');
        showError('Por favor, ingresa tu nombre completo.');
        return;
    }
    
    if (!data.correo || !isValidEmail(data.correo)) {
        console.warn('⚠️ Validación fallida: email inválido');
        showError('Por favor, ingresa un email válido.');
        return;
    }
    
    if (!data.mensaje || data.mensaje.length < 10) {
        console.warn('⚠️ Validación fallida: mensaje muy corto');
        showError('Por favor, escribe un mensaje más detallado (mínimo 10 caracteres).');
        return;
    }
    
    // Show loading state
    setLoadingState(submitButton, true);
    
    try {
        console.log('🔐 Ejecutando reCAPTCHA...');
        
        // Execute reCAPTCHA
        const recaptchaToken = await executeRecaptcha();
        
        if (!recaptchaToken) {
            throw new Error('No se pudo verificar reCAPTCHA');
        }
        
        console.log('✅ reCAPTCHA token obtenido');
        data.recaptcha_token = recaptchaToken;
        
        // Determinar la ruta correcta del PHP
        // Usar ruta relativa desde donde está el HTML
        const phpUrl = './contact_protected.php';
        
        console.log('🌐 Enviando a:', phpUrl);
        console.log('📦 Payload:', JSON.stringify(data, null, 2));
        
        // Send to PHP backend
        const response = await fetch(phpUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        console.log('📡 Respuesta HTTP:', response.status, response.statusText);
        
        // Leer la respuesta
        const contentType = response.headers.get("content-type");
        console.log('📄 Content-Type:', contentType);
        
        let result;
        
        if (contentType && contentType.includes("application/json")) {
            result = await response.json();
            console.log('✅ Respuesta JSON:', result);
        } else {
            // Si no es JSON, leer como texto
            const text = await response.text();
            console.error('❌ Respuesta no es JSON:', text.substring(0, 500));
            throw new Error('El servidor no devolvió una respuesta válida. Verifica que contact_protected.php esté funcionando correctamente.');
        }
        
        if (response.ok && result.success) {
            console.log('🎉 Mensaje enviado exitosamente!');
            
            // Success
            showSuccess(successMessage);
            form.reset();
            
            // Reset timestamp
            formLoadTime = Date.now() / 1000;
            document.getElementById('timestamp_field').value = formLoadTime;
        } else {
            throw new Error(result.message || 'Error al enviar el mensaje');
        }
        
    } catch (error) {
        console.error('❌ Error al enviar:', error);
        console.error('Stack:', error.stack);
        
        let errorMsg = error.message;
        
        // Mensajes de error más específicos
        if (error.message.includes('Failed to fetch')) {
            errorMsg = 'No se puede conectar con el servidor. Verifica que contact_protected.php esté en la misma carpeta que index.html';
        }
        
        showError(errorMsg);
    } finally {
        setLoadingState(submitButton, false);
    }
}

// Execute Google reCAPTCHA v3
function executeRecaptcha() {
    return new Promise((resolve, reject) => {
        if (typeof grecaptcha === 'undefined') {
            console.error('❌ grecaptcha no está definido');
            reject(new Error('reCAPTCHA no está disponible'));
            return;
        }
        
        grecaptcha.ready(function() {
            console.log('🔐 grecaptcha ready');
            grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'submit' })
                .then(function(token) {
                    console.log('✅ reCAPTCHA token generado:', token.substring(0, 20) + '...');
                    resolve(token);
                })
                .catch(function(error) {
                    console.error('❌ Error en grecaptcha.execute:', error);
                    reject(error);
                });
        });
    });
}

// Validate email format
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Set loading state for button
function setLoadingState(button, isLoading) {
    if (!button) return;
    
    if (isLoading) {
        button.disabled = true;
        button.classList.add('opacity-75', 'cursor-not-allowed');
        button.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Enviando...
        `;
    } else {
        button.disabled = false;
        button.classList.remove('opacity-75', 'cursor-not-allowed');
        button.innerHTML = `
            <span class="submit-text">Enviar mensaje</span>
            <span class="submit-arrow ml-2">→</span>
        `;
    }
}

// Show success message
function showSuccess(element) {
    if (!element) return;
    
    element.classList.remove('hidden');
    
    // Auto-hide after 10 seconds
    setTimeout(() => {
        element.classList.add('hidden');
    }, 10000);
    
    // Show notification
    showNotification('¡Mensaje enviado correctamente! Te contactaremos pronto.', 'success');
}

// Show error message
function showError(message) {
    const errorMessage = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');
    
    if (errorText) {
        errorText.textContent = message;
    }
    
    if (errorMessage) {
        errorMessage.classList.remove('hidden');
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            errorMessage.classList.add('hidden');
        }, 10000);
    }
    
    showNotification(message, 'error');
}

// Show notification toast
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg transition-all duration-300 transform translate-x-full max-w-sm`;
    
    if (type === 'success') {
        notification.className += ' bg-green-100 border border-green-200 text-green-800';
    } else if (type === 'error') {
        notification.className += ' bg-red-100 border border-red-200 text-red-800';
    } else if (type === 'warning') {
        notification.className += ' bg-yellow-100 border border-yellow-200 text-yellow-800';
    } else {
        notification.className += ' bg-blue-100 border border-blue-200 text-blue-800';
    }
    
    notification.innerHTML = `
        <div class="flex items-start">
            <span class="flex-1 text-sm">${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-current opacity-70 hover:opacity-100 flex-shrink-0">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Slide in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

// Open WhatsApp with pre-filled message
function openWhatsApp() {
    const message = encodeURIComponent('Hola, me interesa conocer más sobre los servicios de MNCApp. ¿Podrían proporcionarme más información?');
    const whatsappUrl = `https://wa.me/18099396422?text=${message}`;
    window.open(whatsappUrl, '_blank');

}

// Función para scroll suave al formulario de contacto
function scrollToContact() {
    const contactSection = document.getElementById('contacto');
    if (contactSection) {
        contactSection.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    } else {
        console.error('No se encontró la sección con id="contacto"');
    }
}
// Debug helper - muestra información del sistema
console.log('🔧 Debug Info:');
console.log('- URL actual:', window.location.href);
console.log('- Ruta base:', window.location.pathname);
console.log('- Protocolo:', window.location.protocol);
console.log('- Host:', window.location.host);
