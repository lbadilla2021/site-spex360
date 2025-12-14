let articles = [];
let editingId = null;

async function loadArticles() {
    const stored = localStorage.getItem('blogArticles');
    if (stored) {
        articles = JSON.parse(stored);
        renderTable();
        return;
    }

    try {
        const response = await fetch('/assets/data/blog-articulos.json');
        if (!response.ok) {
            throw new Error('No se pudieron cargar los artículos predeterminados');
        }

        articles = await response.json();
        localStorage.setItem('blogArticles', JSON.stringify(articles));
    } catch (error) {
        console.error(error);
        articles = [];
    }

    renderTable();
}

function renderTable() {
    const tbody = document.getElementById('articlesTableBody');

    if (articles.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No hay artículos. Crea el primero usando el botón "+ Nuevo Artículo"</td></tr>';
        return;
    }

    tbody.innerHTML = articles.map(article => `
        <tr>
            <td>${article.id}</td>
            <td><strong>${article.title}</strong></td>
            <td><span class="category-badge">${article.category}</span></td>
            <td><code>${article.filename}</code></td>
            <td>${formatDate(article.date)}</td>
            <td class="actions">
                <button onclick="editArticle(${article.id})" class="btn btn-secondary btn-small">Editar</button>
                <button onclick="deleteArticle(${article.id})" class="btn btn-danger btn-small">Eliminar</button>
            </td>
        </tr>
    `).join('');
}

function openModal(id = null) {
    editingId = id;
    const modal = document.getElementById('articleModal');
    const form = document.getElementById('articleForm');

    form.reset();

    if (id) {
        const article = articles.find(a => a.id === id);
        if (article) {
            document.getElementById('modalTitle').textContent = 'Editar Artículo';
            document.getElementById('articleId').value = article.id;
            document.getElementById('articleTitle').value = article.title;
            document.getElementById('articleSummary').value = article.summary;
            document.getElementById('articleCategory').value = article.category;
            document.getElementById('articleFilename').value = article.filename;
            document.getElementById('articleDate').value = article.date;
            document.getElementById('articleImage').value = article.image || '';
        }
    } else {
        document.getElementById('modalTitle').textContent = 'Nuevo Artículo';
        document.getElementById('articleDate').value = new Date().toISOString().split('T')[0];
    }

    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('articleModal').classList.remove('active');
}

function saveArticle(e) {
    e.preventDefault();

    const articleData = {
        title: document.getElementById('articleTitle').value,
        summary: document.getElementById('articleSummary').value,
        category: document.getElementById('articleCategory').value,
        filename: document.getElementById('articleFilename').value,
        date: document.getElementById('articleDate').value,
        image: document.getElementById('articleImage').value
    };

    if (editingId) {
        const index = articles.findIndex(a => a.id === editingId);
        articles[index] = { ...articles[index], ...articleData };
    } else {
        const newId = articles.length > 0 ? Math.max(...articles.map(a => a.id)) + 1 : 1;
        articles.push({ id: newId, ...articleData });
    }

    localStorage.setItem('blogArticles', JSON.stringify(articles));
    renderTable();
    closeModal();

    alert(editingId ? 'Artículo actualizado exitosamente' : 'Artículo creado exitosamente');
}

function editArticle(id) {
    openModal(id);
}

function deleteArticle(id) {
    if (confirm('¿Estás seguro de eliminar este artículo?')) {
        articles = articles.filter(a => a.id !== id);
        localStorage.setItem('blogArticles', JSON.stringify(articles));
        renderTable();
        alert('Artículo eliminado exitosamente');
    }
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
}

document.addEventListener('DOMContentLoaded', () => {
    loadArticles();
});

window.openModal = openModal;
window.closeModal = closeModal;
window.saveArticle = saveArticle;
window.editArticle = editArticle;
window.deleteArticle = deleteArticle;
