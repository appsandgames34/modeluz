<?php
// ============================================================================
// CONFIGURACIÓN DEL FORMULARIO DE CONTACTO
// ----------------------------------------------------------------------------
// Este es el ÚNICO archivo que hay que editar cuando tengas hosting y dominio.
// Rellena los dos campos y sube el archivo por FTP a la misma carpeta que
// contacto.php. Mientras estén vacíos, el formulario avisará de que todavía
// no está configurado (no se pierde ningún envío: simplemente no se envía).
// ============================================================================
return [
	// Correo donde quieres RECIBIR las consultas (p. ej. tu Gmail o buzón).
	'destinatario' => '',

	// Dirección desde la que se ENVÍA. DEBE ser una cuenta de TU dominio
	// (p. ej. 'web@tudominio.es'); si no, el hosting puede marcarlo como spam.
	// El email del visitante va en Reply-To, así que "Responder" le contesta a él.
	'remitente' => '',

	// Prefijo del asunto del correo (opcional).
	'asunto' => 'Nueva consulta web',
];
