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
$headers = "From: contacto@apex360.cl\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";

class SimpleSmtpMailer
{
    private string $host;
    private int $port;
    private string $encryption;
    private ?string $username;
    private ?string $password;
    private int $timeout;

    public function __construct(string $host, int $port = 587, string $encryption = 'tls', ?string $username = null, ?string $password = null, int $timeout = 10)
    {
        $this->host = $host;
        $this->port = $port;
        $this->encryption = strtolower($encryption);
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
    }

    private function readResponse($connection): string
    {
        $response = '';
        while (($line = fgets($connection, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }

    private function sendCommand($connection, string $command, string $expectedCode): void
    {
        if ($command !== '') {
            fwrite($connection, $command . "\r\n");
        }

        $response = $this->readResponse($connection);

        if (strpos($response, $expectedCode) !== 0) {
            throw new RuntimeException("SMTP error: {$response}");
        }
    }

    public function send(string $from, string $to, string $subject, string $body, string $headers): bool
    {
        $contextOptions = [];
        $transport = '';

        if ($this->encryption === 'ssl') {
            $transport = 'ssl://';
            $contextOptions['ssl'] = [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ];
        }

        $connection = @stream_socket_client(
            $transport . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create($contextOptions)
        );

        if (!$connection) {
            throw new RuntimeException("No se pudo conectar al servidor SMTP: {$errstr} ({$errno})");
        }

        stream_set_timeout($connection, $this->timeout);

        $hostname = gethostname() ?: 'localhost';

        $this->sendCommand($connection, '', '220');
        $this->sendCommand($connection, 'EHLO ' . $hostname, '250');

        if ($this->encryption === 'tls') {
            $this->sendCommand($connection, 'STARTTLS', '220');
            if (!stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('No se pudo negociar TLS con el servidor SMTP');
            }
            $this->sendCommand($connection, 'EHLO ' . $hostname, '250');
        }

        if ($this->username && $this->password) {
            $this->sendCommand($connection, 'AUTH LOGIN', '334');
            $this->sendCommand($connection, base64_encode($this->username), '334');
            $this->sendCommand($connection, base64_encode($this->password), '235');
        }

        $this->sendCommand($connection, 'MAIL FROM: <' . $from . '>', '250');
        $this->sendCommand($connection, 'RCPT TO: <' . $to . '>', '250');
        $this->sendCommand($connection, 'DATA', '354');

        $message = '';
        $message .= $headers;
        $message .= 'To: ' . $to . "\r\n";
        $message .= 'Subject: ' . $subject . "\r\n";
        $message .= "\r\n" . $body . "\r\n.";
        $this->sendCommand($connection, $message, '250');
        $this->sendCommand($connection, 'QUIT', '221');
        fclose($connection);

        return true;
    }
}

$smtpHost = getenv('SMTP_HOST') ?: '';
$smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
$smtpUser = getenv('SMTP_USER') ?: null;
$smtpPass = getenv('SMTP_PASS') ?: null;
$smtpSecure = strtolower(getenv('SMTP_SECURE') ?: 'tls');
$smtpFrom = getenv('SMTP_FROM') ?: 'contacto@apex360.cl';

if ($smtpHost === '') {
    $respond(false, 'El envío de correos no está configurado. Define SMTP_HOST en las variables de entorno.', 500);
}

if (!in_array($smtpSecure, ['tls', 'ssl', 'none'], true)) {
    $respond(false, 'Configuración SMTP inválida: usa tls, ssl o none para SMTP_SECURE.', 400);
}

try {
    if ($smtpHost !== '') {
        $mailer = new SimpleSmtpMailer($smtpHost, $smtpPort, $smtpSecure, $smtpUser, $smtpPass);
        $mailer->send($smtpFrom, $to, $subject, $body, $headers);
    } else {
        $mailSent = mail($to, $subject, $body, $headers, '-fcontacto@apex360.cl');
        if (!$mailSent) {
            throw new RuntimeException('No se pudo enviar el correo usando sendmail local. Configura SMTP_HOST para usar un servidor externo.');
        }
    }
} catch (Throwable $exception) {
    error_log('Contacto Apex 360: fallo el envío de correo - ' . $exception->getMessage());
    $respond(false, 'No pudimos enviar tu mensaje en este momento. Inténtalo más tarde.', 500);
}

$respond(true, '¡Gracias! Hemos recibido tu mensaje y te contactaremos pronto.');
