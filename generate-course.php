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

    $courses = loadCoursesData(__DIR__ . '/assets/data/cursos.json');

    switch ($action) {
        case 'create':
        case 'create_course':
        case 'update':
        case 'update_course':
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
        case 'delete_course':
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

    <link rel="stylesheet" href="../assets/css/plantilla-curso.css">

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
