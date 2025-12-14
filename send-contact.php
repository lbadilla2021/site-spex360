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

$sanitize = static function (string $value): string {
    return trim(preg_replace("/(\r|\n)/", '', $value));
};

$name = $sanitize($_POST['name'] ?? '');
$email = $sanitize($_POST['email'] ?? '');
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

$smtpHost = getenv('SMTP_HOST') ?: 'mail.apex360.cl';
$smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
$smtpUser = getenv('SMTP_USER') ?: 'contacto@apex360.cl';
$smtpPass = getenv('SMTP_PASS') ?: '';
$smtpEncryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');
$smtpFrom = getenv('SMTP_FROM') ?: 'contacto@apex360.cl';

/**
 * Enviar correo usando SMTP simple sin dependencias externas.
 */
$sendMail = static function () use (
    $to,
    $subject,
    $body,
    $smtpHost,
    $smtpPort,
    $smtpUser,
    $smtpPass,
    $smtpEncryption,
    $smtpFrom,
    $email
) {
    $logPrefix = '[Contacto Apex 360 SMTP] ';

    $sendCommand = static function ($connection, string $command, int $expectedCode) use ($logPrefix): bool {
        if ($command !== '') {
            fwrite($connection, $command . "\r\n");
        }

        $response = fgets($connection, 515);
        if ($response === false) {
            error_log($logPrefix . 'sin respuesta del servidor SMTP');
            return false;
        }

        if (strpos($response, (string) $expectedCode) !== 0) {
            error_log($logPrefix . 'respuesta inesperada: ' . trim($response));
            return false;
        }

        return true;
    };

    $transport = ($smtpEncryption === 'ssl' ? 'ssl://' : 'tcp://') . $smtpHost . ':' . $smtpPort;
    $connection = @stream_socket_client($transport, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);

    if (!$connection) {
        error_log($logPrefix . "no se pudo conectar: {$errstr} ({$errno})");
        return false;
    }

    stream_set_timeout($connection, 10);

    if (!fgets($connection, 515)) {
        error_log($logPrefix . 'no hubo saludo inicial del servidor SMTP');
        fclose($connection);
        return false;
    }

    if (!$sendCommand($connection, 'EHLO apex360.cl', 250)) {
        fclose($connection);
        return false;
    }

    if ($smtpEncryption === 'tls') {
        if (!$sendCommand($connection, 'STARTTLS', 220)) {
            fclose($connection);
            return false;
        }

        if (!stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log($logPrefix . 'no se pudo negociar TLS');
            fclose($connection);
            return false;
        }

        if (!$sendCommand($connection, 'EHLO apex360.cl', 250)) {
            fclose($connection);
            return false;
        }
    }

    if ($smtpUser !== '' && $smtpPass !== '') {
        if (!$sendCommand($connection, 'AUTH LOGIN', 334)) {
            fclose($connection);
            return false;
        }
        if (!$sendCommand($connection, base64_encode($smtpUser), 334)) {
            fclose($connection);
            return false;
        }
        if (!$sendCommand($connection, base64_encode($smtpPass), 235)) {
            fclose($connection);
            return false;
        }
    }

    if (!$sendCommand($connection, 'MAIL FROM: <' . $smtpFrom . '>', 250)) {
        fclose($connection);
        return false;
    }

    if (!$sendCommand($connection, 'RCPT TO: <' . $to . '>', 250)) {
        fclose($connection);
        return false;
    }

    if (!$sendCommand($connection, 'DATA', 354)) {
        fclose($connection);
        return false;
    }

    $headers = [
        'From' => $smtpFrom,
        'Reply-To' => $email,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];

    $formattedHeaders = '';
    foreach ($headers as $key => $value) {
        $formattedHeaders .= $key . ': ' . $value . "\r\n";
    }

    $message = $formattedHeaders . "Subject: {$subject}\r\n\r\n" . $body . "\r\n.";

    fwrite($connection, $message . "\r\n");

    if (!$sendCommand($connection, '', 250)) {
        fclose($connection);
        return false;
    }

    $sendCommand($connection, 'QUIT', 221);
    fclose($connection);

    return true;
};

$mailSent = $sendMail();

if (!$mailSent) {
    error_log('Contacto Apex 360: fallo el envío de correo');
    $respond(false, 'No pudimos enviar tu mensaje en este momento. Inténtalo más tarde.', 500);
}

$respond(true, '¡Gracias! Hemos recibido tu mensaje y te contactaremos pronto.');
