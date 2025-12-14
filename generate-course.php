<?php
/**
 * Generador de Cursos Estáticos - OTEC Apex
 *
 * Mantiene sincronizado el maestro de datos (assets/data/cursos.json)
 * y genera/actualiza/elimina archivos HTML estáticos en /cursos/.
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
    $course = $input['course'] ?? null;
    $article = $input['article'] ?? null;

    $courses = loadCoursesData(__DIR__ . '/assets/data/cursos.json');
    $blogPath = __DIR__ . '/assets/data/blog-articulos.json';

    switch ($action) {
        case 'create':
        case 'update':
            if (!$course) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos de curso no proporcionados']);
                exit;
            }

            [$courses, $courseData, $deletedFile] = upsertCourse($courses, $course, $action === 'update');

            if (!saveCoursesData(__DIR__ . '/assets/data/cursos.json', $courses)) {
                http_response_code(500);
                echo json_encode(['error' => 'No se pudo actualizar cursos.json']);
                exit;
            }

            $writeResult = writeCourseHtml($courseData);
            if (!$writeResult['success']) {
                http_response_code(500);
                echo json_encode(['error' => $writeResult['error']]);
                exit;
            }

            if ($deletedFile) {
                @unlink($deletedFile);
            }

            echo json_encode([
                'success' => true,
                'course' => $courseData,
                'courses' => array_values($courses),
                'message' => 'Curso guardado correctamente'
            ]);
            exit;

        case 'delete':
            $courseId = $course['id'] ?? $input['id'] ?? null;
            if (!$courseId) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de curso no proporcionado']);
                exit;
            }

            [$courses, $deletedFile] = deleteCourseById($courses, $courseId);

            if (!saveCoursesData(__DIR__ . '/assets/data/cursos.json', $courses)) {
                http_response_code(500);
                echo json_encode(['error' => 'No se pudo actualizar cursos.json']);
                exit;
            }

            if ($deletedFile && file_exists($deletedFile)) {
                @unlink($deletedFile);
            }

            echo json_encode([
                'success' => true,
                'courses' => array_values($courses),
                'message' => 'Curso eliminado correctamente'
            ]);
            exit;

        case 'create_blog':
        case 'update_blog':
            if (!$article) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos de artículo no proporcionados']);
                exit;
            }

            $blogArticles = loadBlogData($blogPath);
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

            $blogArticles = loadBlogData($blogPath);
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

    // 1️⃣ Quitar .html si viene incluido
    $filename = preg_replace('/\.html?$/i', '', $filename);

    // 2️⃣ Sanitizar
    $sanitized = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $filename);
    $sanitized = trim($sanitized, '-');

    if ($sanitized === '') {
        return '';
    }

    // 3️⃣ Agregar .html UNA sola vez
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
        $slug = 'curso-' . time();
    }

    return $slug . '.html';
}

function loadCoursesData($path) {
    if (!file_exists($path)) {
        return [];
    }

    $content = file_get_contents($path);
    $data = json_decode($content, true);

    return is_array($data) ? $data : [];
}

function saveCoursesData($path, $courses) {
    $json = json_encode(array_values($courses), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

function upsertCourse($courses, $course, $isUpdate = false) {
    $requiredFields = ['title', 'duration', 'intro'];
    foreach ($requiredFields as $field) {
        if (empty($course[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido faltante: {$field}"]);
            exit;
        }
    }

    $incomingId = $course['id'] ?? null;
    $existingIndex = null;
    $existingCourse = null;

    if ($isUpdate) {
        foreach ($courses as $index => $item) {
            if (isset($item['id']) && (int)$item['id'] === (int)$incomingId) {
                $existingIndex = $index;
                $existingCourse = $item;
                break;
            }
        }

        if ($existingIndex === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Curso no encontrado para actualizar']);
            exit;
        }
    }

    if (!$incomingId) {
        $maxId = 0;
        foreach ($courses as $item) {
            $maxId = max($maxId, (int)($item['id'] ?? 0));
        }
        $incomingId = $maxId + 1;
    }

    $desiredFilename = $course['filename'] ?? generateFilenameFromTitle($course['title']);
    $filename = sanitizeFilename($desiredFilename);
    if (!$filename) {
        http_response_code(400);
        echo json_encode(['error' => 'Nombre de archivo inválido']);
        exit;
    }

    $sections = isset($course['sections']) && is_array($course['sections']) ? $course['sections'] : [];

    $courseData = [
        'id' => (int)$incomingId,
        'title' => $course['title'],
        'duration' => $course['duration'],
        'intro' => $course['intro'],
        'image' => $course['image'] ?? '',
        'dates' => $course['dates'] ?? '',
        'filename' => $filename,
        'sections' => $sections
    ];

    $deletedFile = null;

    if ($isUpdate && $existingIndex !== null) {
        $previousFilename = $existingCourse['filename'] ?? null;
        if ($previousFilename && $previousFilename !== $filename) {
            $deletedFile = __DIR__ . '/cursos/' . $previousFilename;
        }
        $courses[$existingIndex] = $courseData;
    } else {
        $courses[] = $courseData;
    }

    return [$courses, $courseData, $deletedFile];
}

function deleteCourseById($courses, $courseId) {
    $courseId = (int)$courseId;
    $deletedFile = null;

    foreach ($courses as $index => $course) {
        if ((int)($course['id'] ?? 0) === $courseId) {
            $deletedFile = __DIR__ . '/cursos/' . ($course['filename'] ?? '');
            unset($courses[$index]);
            return [array_values($courses), $deletedFile];
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Curso no encontrado para eliminar']);
    exit;
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

function writeCourseHtml($course) {
    $filename = sanitizeFilename($course['filename'] ?? '');
    if (!$filename) {
        return ['success' => false, 'error' => 'Nombre de archivo inválido'];
    }

    $html = generateCourseHTML($course);

    $cursosDir = __DIR__ . '/cursos';
    if (!is_dir($cursosDir) && !mkdir($cursosDir, 0755, true)) {
        return ['success' => false, 'error' => 'No se pudo crear la carpeta cursos'];
    }

    if (!is_writable($cursosDir)) {
        return ['success' => false, 'error' => 'La carpeta cursos no tiene permisos de escritura'];
    }

    $filepath = $cursosDir . '/' . $filename;
    $result = file_put_contents($filepath, $html, LOCK_EX);

    if ($result === false) {
        return ['success' => false, 'error' => 'Error al escribir archivo'];
    }

    @chmod($filepath, 0644);
    return ['success' => true, 'path' => '/cursos/' . $filename];
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

function generateCourseHTML($course) {
    $title = htmlspecialchars($course['title']);
    $duration = htmlspecialchars($course['duration']);
    $intro = htmlspecialchars($course['intro']);
    $dates = htmlspecialchars($course['dates'] ?? 'Consulta fechas disponibles');
    $image = htmlspecialchars($course['image'] ?? '');
    
    // Generar secciones
    $sectionsHTML = '';
    if (!empty($course['sections'])) {
        foreach ($course['sections'] as $section) {
            $subtitle = htmlspecialchars($section['subtitle']);
            $content = formatContent($section['content']);
            
            $sectionsHTML .= <<<HTML
                <div class="section-block">
                    <h2>{$subtitle}</h2>
                    <div>{$content}</div>
                </div>

HTML;
        }
    } else {
        $sectionsHTML = '<div class="section-block"><p>No hay contenido disponible para este curso.</p></div>';
    }
    
    // Sección de imagen (si existe)
    $imageHTML = '';
    if (!empty($image)) {
        $imageHTML = <<<HTML
            <div class="course-image-container">
                <img src="{$image}" alt="{$title}" class="course-image-full">
            </div>
HTML;
    }
    
    // HTML completo
    return <<<HTML
<!DOCTYPE html>
<html lang="es-CL">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} | OTEC Apex</title>
    <meta name="description" content="{$intro}">
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
            font-weight: 700;
        }
        
        .section-block p,
        .section-block ul {
            font-size: 1.05rem;
            line-height: 1.8;
            color: var(--text-body);
        }
        
        .section-block ul {
            margin-left: 1.5rem;
            margin-top: 1rem;
        }
        
        .section-block li {
            margin-bottom: 0.75rem;
        }
        
        .cta-section {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
            padding: 4rem 3rem;
            border-radius: 16px;
            text-align: center;
            margin: 3rem 0;
        }
        
        .cta-section h2 {
            font-family: var(--font-display);
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .cta-section p {
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .cta-primary {
            display: inline-block;
            padding: 1.25rem 3rem;
            background: var(--primary);
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .cta-primary:hover {
            background: #0F1729;
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(26, 31, 58, 0.3);
        }
        
        footer {
            background: var(--primary);
            color: white;
            padding: 3rem 0 2rem;
            margin-top: 4rem;
        }
        
        .footer-content {
            text-align: center;
        }
        
        .footer-content p {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 1rem;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: var(--accent);
        }
        
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }
            
            .section-block {
                padding: 1.5rem;
            }
            
            .course-header {
                padding: 3rem 2rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="../index.html" class="logo">Apex<span>360</span></a>
            <ul class="nav-menu">
                <li><a href="../index.html">Inicio</a></li>
                <li><a href="../otec.html">OTEC</a></li>
                <li><a href="../index.html#servicios">Servicios</a></li>
                <li><a href="../index.html#contacto">Contacto</a></li>
            </ul>
            <a href="../index.html#contacto" class="cta-button">Inscripción</a>
        </nav>
    </header>

    <div class="course-detail">
        <div class="container">
            <a href="../otec.html" class="back-button">← Volver a cursos</a>
            
            <div class="course-header">
                <div class="container">
                    <div class="course-header-content">
                        <div class="course-badge">{$duration}</div>
                        <h1>{$title}</h1>
                        <p class="course-intro-text">{$intro}</p>
                    </div>
                </div>
            </div>

            {$imageHTML}

            <div class="course-content">
                {$sectionsHTML}
            </div>

            <div class="cta-section">
                <h2>¿Listo para inscribirte?</h2>
                <p>{$dates}</p>
                <a href="../index.html#contacto" class="cta-primary">Solicitar Inscripción</a>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-content">
                <p>&copy; 2025 OTEC Apex Capacitaciones</p>
                <div class="footer-links">
                    <a href="../index.html">Inicio</a>
                    <a href="../otec.html">Cursos</a>
                    <a href="../index.html#contacto">Contacto</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
HTML;
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
