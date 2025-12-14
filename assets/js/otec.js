// Course data management
const COURSES_PER_PAGE = 4;
let currentPage = 1;
let allCourses = [];

// Load courses from localStorage
function loadCourses() {
    const stored = localStorage.getItem('otecCourses');
    if (stored) {
        allCourses = JSON.parse(stored);
    } else {
        // Default courses if none exist
        allCourses = [
            {
                id: 1,
                title: "Curso Google Sheets Avanzado",
                duration: "28 horas",
                intro: "Domina Google Sheets para la gestión de datos, colaboración y análisis empresarial. Aprende fórmulas avanzadas, tablas dinámicas y visualización de datos.",
                image: "",
                dates: "Próximo inicio: 15 Feb 2025",
                filename: "curso-google-sheets-avanzado.html",
                sections: [
                    { subtitle: "Fundamentos", content: "Introducción a Google Sheets y navegación de interfaz" },
                    { subtitle: "Fórmulas Avanzadas", content: "VLOOKUP, INDEX-MATCH, y funciones condicionales" }
                ]
            },
            {
                id: 2,
                title: "Técnicas de Trabajo en Alturas",
                duration: "8 horas",
                intro: "Capacitación teórico-práctica sobre trabajo seguro en altura conforme a normativa chilena. Incluye uso de EPP, sistemas anticaídas y armado de andamios.",
                image: "",
                dates: "Próximo inicio: 20 Feb 2025",
                filename: "tecnicas-trabajo-alturas.html",
                sections: [
                    { subtitle: "Introducción", content: "Contexto y relevancia del trabajo en altura en Chile" },
                    { subtitle: "Legislación", content: "Normativas y estándares nacionales" }
                ]
            },
            {
                id: 3,
                title: "Power BI Nivel Básico",
                duration: "12 horas",
                intro: "Aprende a crear dashboards interactivos y reportes profesionales con Power BI. Conecta datos, transforma información y genera insights visuales.",
                image: "",
                dates: "Próximo inicio: 25 Feb 2025",
                filename: "power-bi-nivel-basico.html",
                sections: [
                    { subtitle: "Introducción a Power BI", content: "Interface y componentes principales" },
                    { subtitle: "Visualizaciones", content: "Gráficos y tablas efectivas" }
                ]
            },
            {
                id: 4,
                title: "Power BI Nivel Intermedio",
                duration: "12 horas",
                intro: "Curso de Capacitación intermedio en Power BI para análisis avanzado de datos empresariales.",
                image: "",
                dates: "10-12-2025",
                filename: "power-bi-nivel-intermedio.html",
                sections: []
            },
            {
                id: 5,
                title: "Excel Avanzado para Empresas",
                duration: "24 horas",
                intro: "Domina Excel para análisis empresarial, automatización con macros y reportes profesionales.",
                image: "",
                dates: "Próximo inicio: 1 Mar 2025",
                filename: "excel-avanzado-empresas.html",
                sections: []
            },
            {
                id: 6,
                title: "Prevención de Riesgos Laborales",
                duration: "16 horas",
                intro: "Capacitación integral en identificación y control de riesgos en el lugar de trabajo según normativa chilena.",
                image: "",
                dates: "Próximo inicio: 5 Mar 2025",
                filename: "prevencion-riesgos-laborales.html",
                sections: []
            }
        ];
        saveCourses();
    }
}

function saveCourses() {
    localStorage.setItem('otecCourses', JSON.stringify(allCourses));
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
loadCourses();
renderCourses();
renderPagination();
