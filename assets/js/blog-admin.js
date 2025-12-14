let articles = [];
let editingId = null;

async function loadArticles() {
    try {
        const response = await fetch('/assets/data/blog-articulos.json', { cache: 'no-cache' });
        if (!response.ok) {
            throw new Error('No se pudieron cargar los artículos predeterminados');
        }

        articles = await response.json();
        localStorage.setItem('blogArticles', JSON.stringify(articles));
    } catch (error) {
        console.error(error);
        const stored = localStorage.getItem('blogArticles');
        articles = stored ? JSON.parse(stored) : [];
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
            document.getElementById('articleContent').value = article.content || '';
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

async function saveArticle(e) {
    e.preventDefault();

    const articleData = {
        id: editingId,
        title: document.getElementById('articleTitle').value,
        summary: document.getElementById('articleSummary').value,
        category: document.getElementById('articleCategory').value,
        filename: document.getElementById('articleFilename').value,
        date: document.getElementById('articleDate').value,
        image: document.getElementById('articleImage').value,
        content: document.getElementById('articleContent').value
    };

    const payload = {
        action: editingId ? 'update_blog' : 'create_blog',
        article: articleData
    };

    try {
        const response = await fetch('/generate-course.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'No se pudo guardar el artículo');
        }

        articles = data.articles;
        localStorage.setItem('blogArticles', JSON.stringify(articles));
        renderTable();
        closeModal();

        alert(editingId ? 'Artículo actualizado y publicado' : 'Artículo creado y publicado');
    } catch (error) {
        console.error(error);
        alert(error.message || 'Ocurrió un error al guardar el artículo');
    }
}

function editArticle(id) {
    openModal(id);
}

async function deleteArticle(id) {
    if (!confirm('¿Estás seguro de eliminar este artículo?')) {
        return;
    }

    try {
        const response = await fetch('/generate-course.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_blog', id })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'No se pudo eliminar el artículo');
        }

        articles = data.articles;
        localStorage.setItem('blogArticles', JSON.stringify(articles));
        renderTable();
        alert('Artículo eliminado y despublicado');
    } catch (error) {
        console.error(error);
        alert(error.message || 'Ocurrió un error al eliminar el artículo');
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
