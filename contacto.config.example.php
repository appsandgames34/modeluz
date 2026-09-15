<?php
// ============================================================================
// CONFIGURACIÓN DEL FORMULARIO DE CONTACTO — PLANTILLA DE EJEMPLO
// ----------------------------------------------------------------------------
// Copia este archivo a contacto.config.php (está en .gitignore) y rellena
// los valores reales. Sube ese archivo por FTP; nunca lo commitees al repo.
// ============================================================================
return [
	// Correo donde quieres RECIBIR las consultas (p. ej. tu Gmail o buzón).
	'destinatario' => 'tu@dominio.es',

	// Dirección desde la que se ENVÍA. DEBE ser una cuenta de TU dominio;
	// si no, el hosting puede marcarlo como spam.
	// El email del visitante va en Reply-To, así que "Responder" le contesta a él.
	'remitente' => 'web@tudominio.es',

	// Prefijo del asunto del correo (opcional).
	'asunto' => 'Nueva consulta web',

	// Secret key de Cloudflare Turnstile.
	// Obtenla en dash.cloudflare.com → Turnstile → tu sitio → Secret key.
	'turnstile_secret' => 'TURNSTILE_SECRET_KEY_AQUI',
];
