// Header scroll effect
const header = document.getElementById('header');
let lastScroll = 0;

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;

    if (currentScroll > 100) {
        header.classList.add('scrolled');
    } else {
        header.classList.remove('scrolled');
    }

    lastScroll = currentScroll;
});

// Mobile menu toggle
const mobileMenuToggle = document.getElementById('mobileMenuToggle');
const navMenu = document.getElementById('navMenu');

mobileMenuToggle.addEventListener('click', () => {
    mobileMenuToggle.classList.toggle('active');
    navMenu.classList.toggle('active');
});

// Close mobile menu when clicking on a link
const navLinks = document.querySelectorAll('.nav-menu a');
navLinks.forEach(link => {
    link.addEventListener('click', () => {
        mobileMenuToggle.classList.remove('active');
        navMenu.classList.remove('active');
    });
});

// Close mobile menu when clicking outside
document.addEventListener('click', (e) => {
    if (!navMenu.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
        mobileMenuToggle.classList.remove('active');
        navMenu.classList.remove('active');
    }
});

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            const offsetTop = target.offsetTop - 80;
            window.scrollTo({
                top: offsetTop,
                behavior: 'smooth'
            });
        }
    });
});

// Show/hide desktop CTA based on screen size
function handleDesktopCta() {
    const desktopCta = document.getElementById('desktopCta');
    const navMenu = document.getElementById('navMenu');
    if (window.innerWidth >= 768) {
        desktopCta.style.display = 'inline-block';
        navMenu.style.display = 'flex';
    } else {
        desktopCta.style.display = 'none';
    }
}

handleDesktopCta();
window.addEventListener('resize', handleDesktopCta);

// Contact form submission via backend
const contactForm = document.querySelector('.contact-form');

if (contactForm) {
    const feedback = contactForm.querySelector('.form-feedback');
    const submitButton = contactForm.querySelector('button[type="submit"]');

    contactForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(contactForm);

        if (feedback) {
            feedback.textContent = '';
            feedback.classList.remove('error', 'success');
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Enviando...';

        try {
            const response = await fetch('/send-contact.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'No pudimos enviar tu mensaje. Inténtalo nuevamente.');
            }

            if (feedback) {
                feedback.textContent = result.message || '¡Gracias! Hemos recibido tu mensaje.';
                feedback.classList.add('success');
            }

            contactForm.reset();
        } catch (error) {
            if (feedback) {
                feedback.textContent = error.message || 'Ocurrió un error al enviar el mensaje. Inténtalo más tarde.';
                feedback.classList.add('error');
            }
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Enviar mensaje';
        }
    });
}
