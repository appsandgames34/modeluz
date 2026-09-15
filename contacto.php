<?php
// ============================================================================
// contacto.php — recibe el cuestionario de contacto y lo envía por email.
// Sitio estático (Astro) alojado en IONOS. Usa la función mail() del hosting,
// así que NO necesita contraseñas ni configuración de SMTP.
//
// NO edites este archivo: la configuración (destinatario/remitente) vive en
// contacto.config.php, que se rellena cuando haya hosting y dominio.
// ============================================================================
$MAX_BYTES = 10 * 1024 * 1024; // 10 MB

header('Content-Type: application/json; charset=utf-8');

// --- Configuración (archivo aparte; ver contacto.config.php) ---
$cfg = is_file(__DIR__ . '/contacto.config.php') ? include __DIR__ . '/contacto.config.php' : [];
$DESTINATARIO   = trim($cfg['destinatario'] ?? '');
$REMITENTE      = trim($cfg['remitente'] ?? '');
$ASUNTO_PREFIJO = trim($cfg['asunto'] ?? '') ?: 'Nueva consulta web';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
	exit;
}

// Aún sin configurar: avisamos con claridad en vez de intentar enviar en vano.
if ($DESTINATARIO === '' || $REMITENTE === '') {
	http_response_code(503);
	echo json_encode(['ok' => false, 'error' => 'El formulario todavía no está configurado. Vuelve a intentarlo más tarde.']);
	exit;
}

// Honeypot anti-bots: si viene relleno, fingimos éxito y descartamos.
if (!empty($_POST['website'])) {
	echo json_encode(['ok' => true]);
	exit;
}

// Verificación Cloudflare Turnstile (server-side).
// Si la secret key está configurada en contacto.config.php, el token es obligatorio.
$ts_token  = trim($_POST['cf-turnstile-response'] ?? '');
$ts_secret = trim($cfg['turnstile_secret'] ?? '');
if ($ts_secret !== '' && $ts_secret !== 'TURNSTILE_SECRET_KEY_AQUI') {
	$ts_ok = false;
	if ($ts_token !== '') {
		$ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
		if ($ch !== false) {
			curl_setopt_array($ch, [
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => http_build_query(['secret' => $ts_secret, 'response' => $ts_token]),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 5,
			]);
			$ts_resp = curl_exec($ch);
			curl_close($ch);
			if ($ts_resp !== false) {
				$ts_data = json_decode((string) $ts_resp, true);
				$ts_ok   = (bool) ($ts_data['success'] ?? false);
			}
		}
	}
	if (!$ts_ok) {
		// Fingimos éxito para no dar pistas al bot (igual que el honeypot).
		echo json_encode(['ok' => true]);
		exit;
	}
}

$campo = fn(string $k): string => trim(strip_tags((string) ($_POST[$k] ?? '')));

$nombre    = $campo('nombre');
$email     = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$telefono  = $campo('telefono');
$perfil    = $campo('perfil');
$horario   = $campo('horario');
$mensaje   = $campo('mensaje');
$consent   = !empty($_POST['consent']) ? 'Sí' : 'No';
$servicios = isset($_POST['servicios'])
	? implode(', ', array_map(fn($s) => trim(strip_tags((string) $s)), (array) $_POST['servicios']))
	: '';

if ($nombre === '' || !$email || $telefono === '') {
	http_response_code(422);
	echo json_encode(['ok' => false, 'error' => 'Faltan datos obligatorios']);
	exit;
}

$cuerpo = implode("\n", [
	"Perfil: {$perfil}",
	"Servicios: {$servicios}",
	'',
	"Nombre / razón social: {$nombre}",
	"Teléfono: {$telefono}",
	"Email: {$email}",
	"Horario preferido: {$horario}",
	"Acepta tratamiento de datos: {$consent}",
	'',
	'Mensaje:',
	$mensaje !== '' ? $mensaje : '(sin mensaje)',
]);

// ¿Adjunto válido? (PDF o imagen de móvil: JPG, PNG, HEIC, WEBP)
$TIPOS_PERMITIDOS = ['application/pdf', 'image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp'];
$EXT_PERMITIDAS   = ['pdf', 'jpg', 'jpeg', 'png', 'heic', 'heif', 'webp'];
$adjunto = null;
if (
	isset($_FILES['archivo']) &&
	$_FILES['archivo']['error'] === UPLOAD_ERR_OK &&
	$_FILES['archivo']['size'] > 0 &&
	$_FILES['archivo']['size'] <= $MAX_BYTES
) {
	$mime = mime_content_type($_FILES['archivo']['tmp_name']);
	$ext  = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
	if (in_array($mime, $TIPOS_PERMITIDOS) || in_array($ext, $EXT_PERMITIDAS)) {
		$adjunto = [
			'nombre' => preg_replace('/[^\w.\-]/', '_', $_FILES['archivo']['name']),
			'mime'   => in_array($mime, $TIPOS_PERMITIDOS) ? $mime : 'application/octet-stream',
			'datos'  => chunk_split(base64_encode(file_get_contents($_FILES['archivo']['tmp_name']))),
		];
	}
}

$headers = "From: {$REMITENTE}\r\n"
	. "Reply-To: {$nombre} <{$email}>\r\n"
	. "MIME-Version: 1.0\r\n";

if ($adjunto) {
	$b = 'b' . md5(uniqid((string) time(), true));
	$headers .= "Content-Type: multipart/mixed; boundary=\"{$b}\"\r\n";
	$body = "--{$b}\r\n"
		. "Content-Type: text/plain; charset=UTF-8\r\n"
		. "Content-Transfer-Encoding: 8bit\r\n\r\n"
		. $cuerpo . "\r\n"
		. "--{$b}\r\n"
		. "Content-Type: {$adjunto['mime']}; name=\"{$adjunto['nombre']}\"\r\n"
		. "Content-Transfer-Encoding: base64\r\n"
		. "Content-Disposition: attachment; filename=\"{$adjunto['nombre']}\"\r\n\r\n"
		. $adjunto['datos'] . "\r\n"
		. "--{$b}--";
} else {
	$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
	$body = $cuerpo;
}

$asunto = '=?UTF-8?B?' . base64_encode("{$ASUNTO_PREFIJO} — {$perfil}" . ($servicios ? " ({$servicios})" : '')) . '?=';

if (mail($DESTINATARIO, $asunto, $body, $headers)) {
	echo json_encode(['ok' => true]);
} else {
	http_response_code(500);
	echo json_encode(['ok' => false, 'error' => 'No se pudo enviar el correo']);
}
