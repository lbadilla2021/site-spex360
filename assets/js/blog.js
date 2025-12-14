let allArticles = [];
let currentCategory = 'todas';

function loadArticles() {
    const stored = localStorage.getItem('blogArticles');
    if (stored) {
        allArticles = JSON.parse(stored);
    } else {
        // Default article
        allArticles = [
            {
                id: 1,
                title: "Automatizaciones en RRHH: Cómo la IA está Transformando la Gestión de Personas",
                summary: "Descubre cómo las automatizaciones y la inteligencia artificial están revolucionando los procesos de recursos humanos, desde reclutamiento hasta análisis predictivo de rotación.",
                category: "Transformación Digital",
                filename: "automatizaciones-rrhh.html",
                date: "2025-01-15",
                image: ""
            }
        ];
        saveArticles();
    }
    renderArticles();
}

function saveArticles() {
    localStorage.setItem('blogArticles', JSON.stringify(allArticles));
}

function renderArticles() {
    const grid = document.getElementById('blogGrid');
    
    const filtered = currentCategory === 'todas' 
        ? allArticles 
        : allArticles.filter(a => a.category === currentCategory);

    if (filtered.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3>No hay artículos en esta categoría</h3>
                <p>Selecciona otra categoría o usa el panel de administración para agregar contenido.</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = filtered.map(article => `
        <a href="blog/${article.filename}" class="article-card">
            <div class="article-image">
                <div class="article-category">${article.category}</div>
                <h3 class="article-title">${article.title}</h3>
            </div>
            <div class="article-content">
                <p class="article-summary">${article.summary}</p>
                <div class="article-footer">
                    <span class="article-date">${formatDate(article.date)}</span>
                    <span class="article-link">Leer más →</span>
                </div>
            </div>
        </a>
    `).join('');
}

function filterCategory(category) {
    currentCategory = category;
    
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    renderArticles();
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
}

loadArticles();
