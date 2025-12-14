const COURSES_DATA_URL = '/assets/data/cursos.json';
let courses = [];
let editingId = null;
let sectionCounter = 0;

async function loadCourses() {
    const stored = localStorage.getItem('otecCourses');
    if (stored) {
        courses = JSON.parse(stored);
        renderTable();
        return;
    }

    try {
        const response = await fetch(COURSES_DATA_URL);
        if (!response.ok) throw new Error('No se pudo obtener cursos');
        const data = await response.json();
        courses = Array.isArray(data) ? data : [];
        localStorage.setItem('otecCourses', JSON.stringify(courses));
    } catch (error) {
        console.error('Error cargando cursos desde JSON:', error);
        courses = [];
    }

    renderTable();
}

function renderTable() {
    const tbody = document.getElementById('coursesTableBody');

    if (courses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No hay cursos. Crea el primero usando el botón "+ Nuevo Curso"</td></tr>';
        return;
    }

    tbody.innerHTML = courses.map(course => `
        <tr>
            <td>${course.id}</td>
            <td><strong>${course.title}</strong></td>
            <td>${course.duration}</td>
            <td>${course.dates || '-'}</td>
            <td>${course.sections?.length || 0}</td>
            <td class="actions">
                <button onclick="editCourse(${course.id})" class="btn btn-secondary btn-small">Editar</button>
                <button onclick="deleteCourse(${course.id})" class="btn btn-danger btn-small">Eliminar</button>
            </td>
        </tr>
    `).join('');
}

function openModal(id = null) {
    editingId = id;
    const modal = document.getElementById('courseModal');
    const form = document.getElementById('courseForm');

    form.reset();
    document.getElementById('sectionsContainer').innerHTML = '';
    sectionCounter = 0;

    if (id) {
        const course = courses.find(c => c.id === id);
        if (course) {
            document.getElementById('modalTitle').textContent = 'Editar Curso';
            document.getElementById('courseId').value = course.id;
            document.getElementById('courseTitle').value = course.title;
            document.getElementById('courseDuration').value = course.duration;
            document.getElementById('courseIntro').value = course.intro;
            document.getElementById('courseImage').value = course.image || '';
            document.getElementById('courseDates').value = course.dates || '';

            if (course.sections) {
                course.sections.forEach(section => {
                    addSection(section);
                });
            }
        }
    } else {
        document.getElementById('modalTitle').textContent = 'Nuevo Curso';
    }

    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('courseModal').classList.remove('active');
}

function addSection(data = null) {
    const container = document.getElementById('sectionsContainer');
    const id = sectionCounter++;

    const sectionHtml = `
        <div class="section-item" id="section-${id}">
            <div class="section-header">
                <strong>Sección ${id + 1}</strong>
                <button type="button" onclick="removeSection(${id})" class="btn btn-danger btn-small">Eliminar</button>
            </div>
            <input type="text" class="section-subtitle" placeholder="Subtítulo de sección" value="${data?.subtitle || ''}" required>
            <textarea class="section-content" placeholder="Contenido (usa * o - para listas)" required>${data?.content || ''}</textarea>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', sectionHtml);
}

function removeSection(id) {
    const element = document.getElementById(`section-${id}`);
    if (element) {
        element.remove();
    }
}

async function saveCourse(e) {
    e.preventDefault();

    const sections = Array.from(document.querySelectorAll('.section-item')).map(item => ({
        subtitle: item.querySelector('.section-subtitle').value,
        content: item.querySelector('.section-content').value
    }));

    const courseData = {
        title: document.getElementById('courseTitle').value,
        duration: document.getElementById('courseDuration').value,
        intro: document.getElementById('courseIntro').value,
        image: document.getElementById('courseImage').value,
        dates: document.getElementById('courseDates').value,
        sections
    };

    let filename;
    let courseId = editingId;

    if (editingId) {
        const index = courses.findIndex(c => c.id === editingId);
        filename = courses[index]?.filename || generateFilename(courseData.title);
        courses[index] = { ...courses[index], ...courseData, filename };
    } else {
        courseId = courses.length > 0 ? Math.max(...courses.map(c => c.id)) + 1 : 1;
        filename = generateFilename(courseData.title);
        courses.push({ id: courseId, ...courseData, filename });
    }

    const coursePayload = { ...courseData, id: courseId, filename };

    try {
        await sendCourseToServer(coursePayload);
        alert('✅ Curso generado en /cursos/');
    } catch (error) {
        const htmlContent = generateCourseHTML(coursePayload);
        downloadHTMLFile(htmlContent, filename);
        alert('📥 No se pudo generar en servidor. Descarga el HTML y súbelo a /cursos/');
    }

    localStorage.setItem('otecCourses', JSON.stringify(courses));
    renderTable();
    closeModal();
}

async function sendCourseToServer(course) {
    const response = await fetch('generate-course.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ course })
    });

    if (!response.ok) {
        throw new Error('Error HTTP al generar curso');
    }

    const data = await response.json();

    if (!data.success) {
        throw new Error(data.error || 'Error al generar curso');
    }

    return data;
}

function generateFilename(title) {
    const stopwords = ['de', 'del', 'la', 'el', 'los', 'las', 'a', 'al', 'en', 'y', 'o', 'un', 'una', 'para', 'por', 'con', 'sin'];

    return title
        .toLowerCase()
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
        .split(' ')
        .filter(word => !stopwords.includes(word))
        .join('-')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        + '.html';
}

function generateCourseHTML(course) {
    const sectionsHTML = course.sections.map(section => `
        <div class="section-block">
            <h2>${escapeHtml(section.subtitle)}</h2>
            <div>${formatContent(section.content)}</div>
        </div>
    `).join('');

    return `<!DOCTYPE html>
<html lang="es-CL">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${escapeHtml(course.title)} | OTEC Apex</title>
    <meta name="description" content="${escapeHtml(course.intro)}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #1A1F3A;
            --primary-light: #2A3654;
            --accent: #E8AA42;
            --accent-dark: #D89A2F;
            --accent-light: #F4C470;
            --secondary: #2D5F6D;
            --text-dark: #1E293B;
            --text-body: #475569;
            --text-light: #64748B;
            --bg-light: #F8F9FA;
            --bg-white: #FFFFFF;
            --border: #E5E7EB;
            --font-display: 'Sora', sans-serif;
            --font-body: 'DM Sans', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body);
            color: var(--text-body);
            line-height: 1.7;
            background: var(--bg-white);
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 clamp(1.5rem, 5vw, 2rem);
        }

        header {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            z-index: 1000;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem clamp(1.5rem, 5vw, 2rem);
            max-width: 1280px;
            margin: 0 auto;
        }

        .logo {
            font-family: var(--font-display);
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
        }

        .logo span {
            color: var(--accent);
        }

        .nav-menu {
            display: flex;
            gap: 2.5rem;
            list-style: none;
        }

        .nav-menu a {
            color: var(--text-body);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-menu a:hover {
            color: var(--primary);
        }

        .cta-button {
            padding: 0.875rem 2rem;
            background: var(--accent);
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .cta-button:hover {
            background: var(--accent-dark);
            transform: translateY(-2px);
        }

        .course-detail {
            margin-top: 80px;
            padding: 4rem 0;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-body);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 2rem;
            transition: gap 0.3s;
        }

        .back-button:hover {
            gap: 0.75rem;
            color: var(--primary);
        }

        .course-header {
            background: linear-gradient(135deg, var(--primary) 0%, #0F1729 100%);
            color: white;
            padding: 4rem 0;
            border-radius: 16px;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .course-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: radial-gradient(circle at 80% 50%, rgba(232, 170, 66, 0.1) 0%, transparent 60%);
        }

        .course-header-content {
            position: relative;
            z-index: 1;
        }

        .course-badge {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            background: rgba(232, 170, 66, 0.2);
            color: var(--accent-light);
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 50px;
            margin-bottom: 1.5rem;
        }

        .course-detail h1 {
            font-family: var(--font-display);
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            color: white;
        }

        .course-intro-text {
            font-size: 1.2rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.9);
            max-width: 800px;
        }

        .course-image-container {
            margin: 3rem 0;
            border-radius: 12px;
            overflow: hidden;
        }

        .course-image-full {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
        }

        .course-content {
            max-width: 900px;
            margin: 0 auto;
        }

        .section-block {
            background: white;
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            transition: all 0.3s;
        }

        .section-block:hover {
            border-color: var(--accent-light);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

        .section-block h2 {
            font-family: var(--font-display);
            font-size: 1.75rem;
            color: var(--text-dark);
            margin-bottom: 1.25rem;
        }

        .section-block p {
            margin-bottom: 1rem;
            color: var(--text-body);
        }

        .section-block ul {
            padding-left: 1.25rem;
            margin-bottom: 1rem;
            color: var(--text-body);
        }

        .section-block li {
            margin-bottom: 0.5rem;
        }

        .cta-section {
            background: linear-gradient(135deg, rgba(26, 31, 58, 0.95) 0%, rgba(15, 23, 41, 0.98) 100%);
            padding: 3rem;
            border-radius: 16px;
            text-align: center;
            color: white;
            margin-top: 3rem;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(232, 170, 66, 0.15) 0%, transparent 70%);
        }

        .cta-section h2 {
            font-family: var(--font-display);
            font-size: clamp(1.5rem, 3vw, 2.2rem);
            margin-bottom: 1rem;
        }

        .cta-section p {
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 1.5rem;
            font-size: 1.05rem;
        }

        .cta-primary {
            display: inline-block;
            padding: 0.9rem 2rem;
            background: var(--accent);
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            border-radius: 8px;
            transition: transform 0.2s ease;
        }

        .cta-primary:hover {
            transform: translateY(-2px);
        }

        footer {
            background: var(--bg-light);
            padding: 2rem 0;
            margin-top: 3rem;
            border-top: 1px solid var(--border);
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
        }

        .footer-links a {
            color: var(--text-body);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        @media (max-width: 1024px) {
            .nav-menu {
                gap: 1.5rem;
            }

            .cta-button {
                padding: 0.75rem 1.5rem;
            }
        }

        @media (max-width: 768px) {
            nav {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .nav-menu {
                width: 100%;
                gap: 1rem;
                justify-content: flex-start;
            }

            .course-header {
                padding: 3rem 1.5rem;
            }

            .course-content {
                padding: 0 0.5rem;
            }

            .section-block {
                padding: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="../apex360-landing.html" class="logo">Apex<span>360</span></a>
            <ul class="nav-menu">
                <li><a href="../apex360-landing.html">Inicio</a></li>
                <li><a href="../otec.html">OTEC</a></li>
                <li><a href="../apex360-landing.html#servicios">Servicios</a></li>
                <li><a href="../apex360-landing.html#contacto">Contacto</a></li>
            </ul>
            <a href="../apex360-landing.html#contacto" class="cta-button">Inscripción</a>
        </nav>
    </header>

    <div class="course-detail">
        <div class="container">
            <a href="../otec.html" class="back-button">← Volver a cursos</a>

            <div class="course-header">
                <div class="container">
                    <div class="course-header-content">
                        <div class="course-badge">${escapeHtml(course.duration)}</div>
                        <h1>${escapeHtml(course.title)}</h1>
                        <p class="course-intro-text">${escapeHtml(course.intro)}</p>
                    </div>
                </div>
            </div>

            ${course.image ? `
                <div class="course-image-container">
                    <img src="${escapeHtml(course.image)}" alt="${escapeHtml(course.title)}" class="course-image-full">
                </div>
            ` : ''}

            <div class="course-content">
                ${sectionsHTML || '<div class="section-block"><p>No hay contenido disponible para este curso.</p></div>'}
            </div>

            <div class="cta-section">
                <h2>¿Listo para inscribirte?</h2>
                <p>${escapeHtml(course.dates || 'Consulta fechas disponibles')}</p>
                <a href="../apex360-landing.html#contacto" class="cta-primary">Solicitar Inscripción</a>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-content">
                <p>&copy; 2025 OTEC Apex Capacitaciones</p>
                <div class="footer-links">
                    <a href="../apex360-landing.html">Inicio</a>
                    <a href="../otec.html">Cursos</a>
                    <a href="../apex360-landing.html#contacto">Contacto</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>`;
}

function formatContent(content) {
    const lines = content.split('\n').filter(line => line.trim());
    let html = '';
    let inList = false;

    lines.forEach(line => {
        const trimmed = line.trim();
        if (trimmed.startsWith('*') || trimmed.startsWith('-')) {
            if (!inList) {
                html += '<ul>';
                inList = true;
            }
            html += `<li>${escapeHtml(trimmed.substring(1).trim())}</li>`;
        } else {
            if (inList) {
                html += '</ul>';
                inList = false;
            }
            html += `<p>${escapeHtml(trimmed)}</p>`;
        }
    });

    if (inList) html += '</ul>';
    return html || `<p>${escapeHtml(content)}</p>`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function downloadHTMLFile(content, filename) {
    const blob = new Blob([content], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function editCourse(id) {
    openModal(id);
}

function deleteCourse(id) {
    if (confirm('¿Estás seguro de eliminar este curso?')) {
        courses = courses.filter(c => c.id !== id);
        localStorage.setItem('otecCourses', JSON.stringify(courses));
        renderTable();
        alert('Curso eliminado exitosamente');
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    await loadCourses();
});

window.openModal = openModal;
window.closeModal = closeModal;
window.saveCourse = saveCourse;
window.addSection = addSection;
window.removeSection = removeSection;
window.editCourse = editCourse;
window.deleteCourse = deleteCourse;
