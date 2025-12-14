// Course data management
const COURSES_PER_PAGE = 4;
const COURSES_DATA_URL = '/assets/data/cursos.json';
let currentPage = 1;
let allCourses = [];

// Load courses from cursos.json (sin persistencia local para mantener datos actualizados)
async function loadCourses() {
    try {
        const response = await fetch(COURSES_DATA_URL, { cache: 'no-cache' });
        if (!response.ok) throw new Error('No se pudo obtener cursos');
        const data = await response.json();
        allCourses = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error('Error cargando cursos desde JSON:', error);
        allCourses = [];
    }
}

function renderCourses() {
    const grid = document.getElementById('coursesGrid');
    const startIndex = (currentPage - 1) * COURSES_PER_PAGE;
    const endIndex = startIndex + COURSES_PER_PAGE;
    const coursesToShow = allCourses.slice(startIndex, endIndex);

    if (coursesToShow.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📚</div>
                <h3>No hay cursos disponibles</h3>
                <p>Usa el panel de administración para agregar cursos</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = coursesToShow.map(course => `
        <div class="course-card" onclick="window.location.href='cursos/${course.filename}'">
            <div class="course-image">
                <div class="course-duration">${course.duration}</div>
                <h3 class="course-title">${course.title}</h3>
            </div>
            <div class="course-content">
                <p class="course-intro">${course.intro}</p>
                <div class="course-footer">
                    <span class="course-dates">${course.dates || 'Consultar fechas'}</span>
                    <a href="cursos/${course.filename}" class="course-link">Ver detalles →</a>
                </div>
            </div>
        </div>
    `).join('');
}

function renderPagination() {
    const totalPages = Math.ceil(allCourses.length / COURSES_PER_PAGE);
    const paginationEl = document.getElementById('pagination');

    if (totalPages <= 1) {
        paginationEl.innerHTML = '';
        return;
    }

    let html = `
        <button class="page-btn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
            ← Anterior
        </button>
    `;

    for (let i = 1; i <= totalPages; i++) {
        html += `
            <button class="page-btn ${i === currentPage ? 'active' : ''}" 
                    onclick="changePage(${i})">
                ${i}
            </button>
        `;
    }

    html += `
        <button class="page-btn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
            Siguiente →
        </button>
    `;

    paginationEl.innerHTML = html;
}

function changePage(page) {
    const totalPages = Math.ceil(allCourses.length / COURSES_PER_PAGE);
    if (page < 1 || page > totalPages) return;
    
    currentPage = page;
    renderCourses();
    renderPagination();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Header scroll effect
window.addEventListener('scroll', () => {
    const header = document.getElementById('header');
    if (window.pageYOffset > 100) {
        header.classList.add('scrolled');
    } else {
        header.classList.remove('scrolled');
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    await loadCourses();
    renderCourses();
    renderPagination();
});
