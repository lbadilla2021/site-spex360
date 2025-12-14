<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

$respond = function (bool $success, string $message, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
};

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$comments = trim($_POST['comments'] ?? '');

if ($name === '' || $email === '' || $comments === '') {
    $respond(false, 'Completa todos los campos para enviar tu mensaje.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $respond(false, 'Ingresa un correo electrónico válido.', 400);
}

$to = 'contacto@apex360.cl';
$subject = 'Consulta desde el sitio Apex 360';
$body = "Nombre: {$name}\nCorreo: {$email}\nComentarios:\n{$comments}\n";
$headers = "From: contacto@apex360.cl\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if (!mail($to, $subject, $body, $headers)) {
    $respond(false, 'No pudimos enviar tu mensaje en este momento. Inténtalo más tarde.', 500);
}

$respond(true, '¡Gracias! Hemos recibido tu mensaje y te contactaremos pronto.');
