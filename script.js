// MNCApp JavaScript - Versión Compatible con Hosting Estándar

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Add smooth scrolling behavior
    initializeSmoothScroll();
    
    // Add loading animations on page load
    addPageLoadAnimations();
    
    // Initialize form handling
    initializeFormHandling();
});

// Smooth scroll to contact section
function scrollToContact() {
    const contactSection = document.getElementById('contacto');
    if (contactSection) {
        contactSection.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// Open WhatsApp with pre-filled message
function openWhatsApp() {
    const message = encodeURIComponent('Hola, me interesa conocer más sobre los servicios de MNCApp. ¿Podrían proporcionarme más información?');
    const whatsappUrl = `https://wa.me/18099396422?text=${message}`;
    window.open(whatsappUrl, '_blank');
}

// Initialize smooth scroll for all internal links
function initializeSmoothScroll() {
    const links = document.querySelectorAll('a[href^="#"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Add animations on page load
function addPageLoadAnimations() {
    const serviceCards = document.querySelectorAll('.service-card');
    serviceCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        }, index * 200);
    });
}

// Handle form submission - MÉTODO COMPATIBLE
async function submitForm(event) {
    event.preventDefault();
    
    const form = document.getElementById('contactForm');
    const submitButton = document.getElementById('submitButton');
    const successMessage = document.getElementById('successMessage');
    
    // Get form data
    const formData = new FormData(form);
    const data = {
        nombre: formData.get('nombre').trim(),
        correo: formData.get('correo').trim(),
        mensaje: formData.get('mensaje').trim()
    };
    
    // Basic validation
    if (!data.nombre || !data.correo || !data.mensaje) {
        showNotification('Por favor, completa todos los campos.', 'error');
        return;
    }
    
    if (!isValidEmail(data.correo)) {
        showNotification('Por favor, ingresa un email válido.', 'error');
        return;
    }
    
    // Show loading state
    setLoadingState(submitButton, true);
    showNotification('Enviando mensaje...', 'info');
    
    try {
        // Método 1: Envío tradicional con FormData (evita error 409)
        const success = await sendViaFormSubmission(data);
        
        if (success) {
            showSuccessMessage(successMessage);
            form.reset();
            showNotification('¡Mensaje enviado correctamente! Te contactaremos pronto.', 'success');
        } else {
            throw new Error('Form submission failed');
        }
        
    } catch (error) {
        console.log('Form submission failed:', error);
        
        // Método 2: Fallback con instrucciones claras
        showNotification('Preparando alternativa... Se abrirá tu cliente de email.', 'warning');
        
        setTimeout(() => {
            showInstructionsModal(data);
            sendEmailDirectly(data);
        }, 1500);
    }
    
    setLoadingState(submitButton, false);
}

// Método 1: Envío tradicional de formulario (compatible con la mayoría de hostings)
async function sendViaFormSubmission(data) {
    try {
        // Create a temporary form for traditional submission
        const tempForm = document.createElement('form');
        tempForm.method = 'POST';
        tempForm.action = 'contact_simple.php';
        tempForm.style.display = 'none';
        
        // Add form fields
        Object.keys(data).forEach(key => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = data[key];
            tempForm.appendChild(input);
        });
        
        // Add AJAX indicator
        const ajaxInput = document.createElement('input');
        ajaxInput.type = 'hidden';
        ajaxInput.name = 'ajax';
        ajaxInput.value = '1';
        tempForm.appendChild(ajaxInput);
        
        document.body.appendChild(tempForm);
        
        // Submit form via fetch to avoid page redirect
        const formData = new FormData(tempForm);
        
        const response = await fetch('contact_simple.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        // Clean up
        document.body.removeChild(tempForm);
        
        if (response.ok) {
            const result = await response.text();
            
            // Check if it's JSON response
            try {
                const jsonResult = JSON.parse(result);
                return jsonResult.success;
            } catch {
                // If it's HTML response, check for success indicators
                return result.includes('Mensaje enviado correctamente') || 
                       result.includes('¡Mensaje enviado correctamente!');
            }
        }
        
        return false;
        
    } catch (error) {
        console.error('Form submission error:', error);
        return false;
    }
}

// Show instructions modal
function showInstructionsModal(data) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h3 class="text-lg font-bold text-gray-900 mb-4">📧 Tu email se está preparando</h3>
            <div class="text-sm text-gray-600 space-y-3">
                <div class="bg-blue-50 p-3 rounded">
                    <p class="font-medium text-blue-900">¿Qué hacer ahora?</p>
                    <ol class="list-decimal list-inside space-y-1 mt-2 text-blue-800">
                        <li>Se abrirá tu programa de email</li>
                        <li>Verifica que el mensaje esté completo</li>
                        <li>Haz clic en "Enviar"</li>
                    </ol>
                </div>
                
                <div class="bg-green-50 p-3 rounded">
                    <p class="font-medium text-green-900">🚀 Alternativa más rápida:</p>
                    <p class="text-green-700 text-xs mt-1">WhatsApp: 809-939-6422</p>
                    <p class="text-green-600 text-xs">Mensaje listo para copiar:</p>
                    <div class="bg-white p-2 rounded text-xs mt-1 border">
                        "Hola, soy ${data.nombre} (${data.correo}). ${data.mensaje}"
                    </div>
                </div>
            </div>
            
            <div class="flex gap-2 mt-6">
                <button onclick="this.parentElement.parentElement.parentElement.remove()" 
                        class="flex-1 bg-gray-500 text-white py-2 px-4 rounded text-sm">
                    Entendido
                </button>
                <button onclick="copyToClipboard('${data.mensaje}'); openWhatsApp(); this.parentElement.parentElement.parentElement.remove();" 
                        class="flex-1 bg-green-600 text-white py-2 px-4 rounded text-sm">
                    Ir a WhatsApp
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Auto-remove after 10 seconds
    setTimeout(() => {
        if (modal.parentElement) {
            modal.remove();
        }
    }, 10000);
}

// Copy to clipboard function
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
    }
}

// Send email directly using mailto
function sendEmailDirectly(data) {
    const subject = encodeURIComponent(`Contacto desde mncapp.com - ${data.nombre}`);
    const body = encodeURIComponent(`Hola MNCApp,

Mi nombre es: ${data.nombre}
Mi email es: ${data.correo}
Fecha: ${new Date().toLocaleDateString('es-ES')}

Mensaje:
${data.mensaje}

Saludos,
${data.nombre}

---
Enviado desde el formulario de contacto de mncapp.com`);
    
    const mailtoLink = `mailto:info@mncapp.com?subject=${subject}&body=${body}`;
    window.location.href = mailtoLink;
}

// Set loading state for button
function setLoadingState(button, isLoading) {
    if (isLoading) {
        button.disabled = true;
        button.classList.add('loading');
        button.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Enviando...
        `;
    } else {
        button.disabled = false;
        button.classList.remove('loading');
        button.innerHTML = `
            <span class="submit-text">Enviar mensaje</span>
            <span class="submit-arrow ml-2">→</span>
        `;
    }
}

// Show success message
function showSuccessMessage(element) {
    element.classList.remove('hidden');
    element.classList.add('show');
    
    setTimeout(() => {
        element.classList.add('hidden');
        element.classList.remove('show');
    }, 5000);
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-40 p-4 rounded-md shadow-lg transition-all duration-300 transform translate-x-full max-w-sm`;
    
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
    
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
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

// Validate email format
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Initialize form handling
function initializeFormHandling() {
    const form = document.getElementById('contactForm');
    if (form) {
        const inputs = form.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                this.classList.remove('border-red-500');
                this.classList.add('border-gray-200');
            });
        });
    }
}

// Validate individual form field
function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    
    if (field.required && !value) {
        isValid = false;
    } else if (field.type === 'email' && value && !isValidEmail(value)) {
        isValid = false;
    }
    
    if (isValid) {
        field.classList.remove('border-red-500');
        field.classList.add('border-gray-200');
    } else {
        field.classList.add('border-red-500');
        field.classList.remove('border-gray-200');
    }
    
    return isValid;
}

// Add scroll animations
window.addEventListener('scroll', function() {
    const scrolled = window.pageYOffset;
    const header = document.querySelector('header');
    
    if (scrolled > 50) {
        header.classList.add('backdrop-blur-md', 'bg-white/90');
    } else {
        header.classList.remove('backdrop-blur-md', 'bg-white/90');
    }
});

// Performance: Lazy load images when they come into view
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            }
        });
    });

    document.querySelectorAll('img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
}