<?php
/**
 * Generador de Artículos de Blog - Apex 360
 *
 * Mantiene sincronizado el maestro de datos (assets/data/blog-articulos.json)
 * y genera/actualiza/elimina archivos HTML estáticos en /blog/.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        if ($needle === '') {
            return true;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Acción no especificada']);
        exit;
    }

    $action = $input['action'];
    $article = $input['article'] ?? null;
    $blogPath = __DIR__ . '/assets/data/blog-articulos.json';

    $blogArticles = loadBlogData($blogPath);

    switch ($action) {
        case 'create_blog':
        case 'update_blog':
            if (!$article) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos de artículo no proporcionados']);
                exit;
            }

            [$blogArticles, $articleData, $deletedFile] = upsertBlog($blogArticles, $article, $action === 'update_blog');

            if (!saveBlogData($blogPath, $blogArticles)) {
                http_response_code(500);
                echo json_encode(['error' => 'No se pudo actualizar blog-articulos.json']);
                exit;
            }

            $writeResult = writeBlogHtml($articleData);
            if (!$writeResult['success']) {
                http_response_code(500);
                echo json_encode(['error' => $writeResult['error']]);
                exit;
            }

            if ($deletedFile && file_exists($deletedFile)) {
                @unlink($deletedFile);
            }

            echo json_encode([
                'success' => true,
                'article' => $articleData,
                'articles' => array_values($blogArticles),
                'message' => 'Artículo guardado correctamente'
            ]);
            exit;

        case 'delete_blog':
            $articleId = $article['id'] ?? $input['id'] ?? null;
            if (!$articleId) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de artículo no proporcionado']);
                exit;
            }

            [$blogArticles, $deletedFile] = deleteBlogById($blogArticles, $articleId);

            if (!saveBlogData($blogPath, $blogArticles)) {
                http_response_code(500);
                echo json_encode(['error' => 'No se pudo actualizar blog-articulos.json']);
                exit;
            }

            if ($deletedFile && file_exists($deletedFile)) {
                @unlink($deletedFile);
            }

            echo json_encode([
                'success' => true,
                'articles' => array_values($blogArticles),
                'message' => 'Artículo eliminado correctamente'
            ]);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no soportada']);
            exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}

function sanitizeFilename($filename) {
    if (!$filename) {
        return '';
    }

    $filename = preg_replace('/\.html?$/i', '', $filename);
    $sanitized = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $filename);
    $sanitized = trim($sanitized, '-');

    if ($sanitized === '') {
        return '';
    }

    return $sanitized . '.html';
}

function generateFilenameFromTitle($title) {
    $stopwords = ['de', 'del', 'la', 'el', 'los', 'las', 'a', 'al', 'en', 'y', 'o', 'un', 'una', 'para', 'por', 'con', 'sin'];
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
    $normalized = strtolower($normalized);
    $words = preg_split('/\s+/', $normalized);
    $filtered = array_filter($words, function ($word) use ($stopwords) {
        return $word !== '' && !in_array($word, $stopwords, true);
    });

    $slug = preg_replace('/[^a-z0-9-]+/', '-', implode('-', $filtered));
    $slug = trim(preg_replace('/-+/', '-', $slug), '-');

    if ($slug === '') {
        $slug = 'articulo-' . time();
    }

    return $slug . '.html';
}

function loadBlogData($path) {
    if (!file_exists($path)) {
        return [];
    }

    $content = file_get_contents($path);
    $data = json_decode($content, true);

    return is_array($data) ? $data : [];
}

function saveBlogData($path, $articles) {
    $json = json_encode(array_values($articles), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

function upsertBlog($articles, $article, $isUpdate = false) {
    $requiredFields = ['title', 'summary', 'category', 'date'];
    foreach ($requiredFields as $field) {
        if (empty($article[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido faltante: {$field}"]); 
            exit;
        }
    }

    $incomingId = $article['id'] ?? null;
    $existingIndex = null;
    $existingArticle = null;

    if ($isUpdate) {
        foreach ($articles as $index => $item) {
            if (isset($item['id']) && (int)$item['id'] === (int)$incomingId) {
                $existingIndex = $index;
                $existingArticle = $item;
                break;
            }
        }

        if ($existingIndex === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Artículo no encontrado para actualizar']);
            exit;
        }
    }

    if (!$incomingId) {
        $maxId = 0;
        foreach ($articles as $item) {
            $maxId = max($maxId, (int)($item['id'] ?? 0));
        }
        $incomingId = $maxId + 1;
    }

    $desiredFilename = $article['filename'] ?? generateFilenameFromTitle($article['title']);
    $filename = sanitizeFilename($desiredFilename);
    if (!$filename) {
        http_response_code(400);
        echo json_encode(['error' => 'Nombre de archivo inválido']);
        exit;
    }

    $articleData = [
        'id' => (int)$incomingId,
        'title' => $article['title'],
        'summary' => $article['summary'],
        'category' => $article['category'],
        'filename' => $filename,
        'date' => $article['date'],
        'image' => $article['image'] ?? '',
        'content' => $article['content'] ?? ''
    ];

    $deletedFile = null;

    if ($isUpdate && $existingIndex !== null) {
        $previousFilename = $existingArticle['filename'] ?? null;
        if ($previousFilename && $previousFilename !== $filename) {
            $deletedFile = __DIR__ . '/blog/' . $previousFilename;
        }
        $articles[$existingIndex] = $articleData;
    } else {
        $articles[] = $articleData;
    }

    return [$articles, $articleData, $deletedFile];
}

function deleteBlogById($articles, $articleId) {
    $articleId = (int)$articleId;
    $deletedFile = null;

    foreach ($articles as $index => $article) {
        if ((int)($article['id'] ?? 0) === $articleId) {
            $deletedFile = __DIR__ . '/blog/' . ($article['filename'] ?? '');
            unset($articles[$index]);
            return [array_values($articles), $deletedFile];
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Artículo no encontrado para eliminar']);
    exit;
}

function writeBlogHtml($article) {
    $filename = sanitizeFilename($article['filename'] ?? '');
    if (!$filename) {
        return ['success' => false, 'error' => 'Nombre de archivo inválido'];
    }

    $html = generateBlogHTML($article);

    $blogDir = __DIR__ . '/blog';
    if (!is_dir($blogDir) && !mkdir($blogDir, 0755, true)) {
        return ['success' => false, 'error' => 'No se pudo crear la carpeta blog'];
    }

    if (!is_writable($blogDir)) {
        return ['success' => false, 'error' => 'La carpeta blog no tiene permisos de escritura'];
    }

    $filepath = $blogDir . '/' . $filename;
    $result = file_put_contents($filepath, $html, LOCK_EX);

    if ($result === false) {
        return ['success' => false, 'error' => 'Error al escribir archivo'];
    }

    @chmod($filepath, 0644);
    return ['success' => true, 'path' => '/blog/' . $filename];
}

function generateBlogHTML($article) {
    $title = htmlspecialchars($article['title']);
    $summary = htmlspecialchars($article['summary']);
    $category = htmlspecialchars($article['category']);
    $date = htmlspecialchars($article['date']);
    $image = htmlspecialchars($article['image'] ?? '');

    $content = !empty($article['content'])
        ? formatContent($article['content'])
        : '<p>' . nl2br($summary) . '</p>';

    $imageHTML = '';
    if (!empty($image)) {
        $imageHTML = <<<HTML
            <div class="article-hero-image">
                <img src="{$image}" alt="{$title}">
            </div>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="es-CL">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} | Blog Apex 360</title>
    <meta name="description" content="{$summary}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1A1F3A;
            --accent: #E8AA42;
            --text-dark: #1E293B;
            --text-body: #475569;
            --border: #E5E7EB;
            --bg-light: #F8F9FA;
            --font-display: 'Sora', sans-serif;
            --font-body: 'DM Sans', sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-body);
            color: var(--text-body);
            background: #fff;
            line-height: 1.7;
        }

        header {
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(10px);
            z-index: 10;
        }

        nav {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--primary);
            text-decoration: none;
        }

        .article-wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        .article-meta {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1.5rem;
            color: var(--text-body);
            font-size: 0.95rem;
        }

        .badge { background: var(--bg-light); color: var(--primary); padding: 0.35rem 0.75rem; border-radius: 999px; font-weight: 600; }
        .date { color: var(--text-body); }

        h1 { font-family: var(--font-display); color: var(--text-dark); font-size: clamp(2rem, 4vw, 2.6rem); margin-bottom: 1rem; }

        .article-summary { font-size: 1.1rem; color: var(--text-body); margin-bottom: 1.75rem; }

        .article-content { display: grid; gap: 1.25rem; color: var(--text-body); font-size: 1rem; }
        .article-content p { line-height: 1.7; }
        .article-content ul { padding-left: 1.2rem; display: grid; gap: 0.5rem; }
        .article-content li { line-height: 1.6; }

        .article-hero-image { margin: 2rem 0; border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
        .article-hero-image img { width: 100%; display: block; }

        .back-link { display: inline-flex; align-items: center; gap: 0.35rem; color: var(--primary); text-decoration: none; margin-top: 2rem; font-weight: 600; }

        footer {
            border-top: 1px solid var(--border);
            padding: 1.5rem;
            text-align: center;
            color: var(--text-body);
            background: var(--bg-light);
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="../index.html" class="logo">Apex<span>360</span></a>
            <a href="../blog.html" style="color: var(--primary); text-decoration: none; font-weight: 600;">← Volver al blog</a>
        </nav>
    </header>

    <main class="article-wrapper">
        <div class="article-meta">
            <span class="badge">{$category}</span>
            <span class="date">{$date}</span>
        </div>

        <h1>{$title}</h1>
        <p class="article-summary">{$summary}</p>

        {$imageHTML}

        <div class="article-content">
            {$content}
        </div>

        <a class="back-link" href="../blog.html">← Volver al listado</a>
    </main>

    <footer>
        <p>&copy; 2025 Apex 360 - Consultoría RRHH &amp; People Analytics</p>
    </footer>
</body>
</html>
HTML;
}

function formatContent($content) {
    $lines = array_filter(explode("\n", $content), 'trim');
    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '-')) {
            if (!$inList) {
                $html .= '<ul>';
                $inList = true;
            }
            $item = htmlspecialchars(trim(substr($trimmed, 1)));
            $html .= "<li>{$item}</li>";
        } else {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<p>' . htmlspecialchars($trimmed) . '</p>';
        }
    }

    if ($inList) {
        $html .= '</ul>';
    }

    return $html ?: '<p>' . htmlspecialchars($content) . '</p>';
}
?>
