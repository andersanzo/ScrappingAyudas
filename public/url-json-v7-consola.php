<?php
// Eliminar límite de tiempo de ejecución
set_time_limit(0);

// Activamos el modo debug (true = se guardan y muestran los extractos de HTML)
define('DEBUG', true);
// Cargar el autoload de Composer
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// =============================
// FUNCIONES DE UTILIDAD
// =============================

// Función para obtener datos de una página específica desde la API
function obtenerHtml($page) {
    $url = "https://www.spri.eus/es/wp-json/marketplace/v1/filter";
    $payload = http_build_query([
        'search_text' => '',
        'order' => 'date',
        'page' => $page,
        'lang' => 'es_ES',
        'selected_filters' => [
            'tematica-ayudas3' => ['Ver todas las ayudas']
        ]
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);

    $respuesta = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Error: ' . curl_error($ch);
    }

    curl_close($ch);

    $data = json_decode($respuesta, true);
    return $data;
}

// Función para obtener el HTML de una URL específica
function obtenerHtmlDeUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    // Configuramos un User Agent
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $htmlContent = curl_exec($ch);
    if (curl_errno($ch)) {
        // En caso de error, devolvemos cadena vacía
        $htmlContent = '';
    }
    curl_close($ch);
    return $htmlContent;
}

// =============================
// SACAR LINKS Y GUARDARLOS EN UN ARCHIVO JSON
// =============================

// Paso 1: Obtener la primera página para saber el total de páginas
$primeraPagina = obtenerHtml(1);
$totalPaginas = isset($primeraPagina['total_pages']) ? $primeraPagina['total_pages'] : 1;

$htmlPaginas = [];
$htmlPaginas[] = $primeraPagina['html'] ?? '';

// Paso 2: Recorrer automáticamente todas las páginas
for ($i = 2; $i <= $totalPaginas; $i++) {
    $pagina = obtenerHtml($i);
    $htmlPaginas[] = $pagina['html'] ?? '';
}

// Paso 3: Extraer todos los links con la expresión regular dada
$urls = [];
$regex = '/(?:(?:https?|ftp|file):\/\/|www\.|ftp\.)(?:\([-A-Z0-9+&@#\/%=~_|$?!:,.]*\)|[-A-Z0-9+&@#\/%=~_|$?!:,.])*(?:\([-A-Z0-9+&@#\/%=~_|$?!:,.]*\)|[A-Z0-9+&@#\/%=~_|$])/i';

foreach ($htmlPaginas as $html) {
    preg_match_all($regex, $html, $coincidencias);
    $urls = array_merge($urls, $coincidencias[0]);
}

// Paso 4: Filtrar solo las URLs que empiezan por "https://www.spri.eus/es/ayudas"
$urls = array_unique($urls);
$urls = array_filter($urls, function($url) {
    return strpos($url, 'https://www.spri.eus/es/ayudas') === 0;
});
$urls = array_values($urls);

// NUEVA PARTE: Obtener el HTML de cada URL y contar sus caracteres
// Si se detecta "f5_cspm" en el HTML, se resta 1559 al recuento

$urls_con_caracteres = [];$urls_con_caracteres = [];
foreach ($urls as $url) {
    $htmlContent = obtenerHtmlDeUrl($url);
    $rawLength = strlen($htmlContent);
    if (strpos($htmlContent, 'f5_cspm') !== false) {
        $rawLength = max($rawLength - 1559, 0);
    }
    $entry = [
        'url' => $url,
        'length' => $rawLength
    ];
    if (DEBUG) {
        $entry['html_snippet'] = substr($htmlContent, 0, 500);
    }
    $urls_con_caracteres[] = $entry;
}


// Paso 5: Convertir a JSON incluyendo ambos apartados
$dataToSave = [
    'urls' => $urls,
    'urls_con_caracteres' => $urls_con_caracteres
];
$jsonData = json_encode($dataToSave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Paso 6: Guardar el JSON en una carpeta específica
$directorio = __DIR__ . '/../JSONS/';
$nombreArchivo = 'URLS-' . date("YmdHis") . '.json';
$rutaCompleta = $directorio . $nombreArchivo;

if (!file_exists($directorio)) {
    mkdir($directorio, 0777, true);
}

file_put_contents($rutaCompleta, $jsonData);

// =============================
// COMPARAR CON ARCHIVO ANTERIOR
// =============================
$archivos = glob($directorio . '*.json'); 
sort($archivos);

if (count($archivos) < 2) {
    die("No hay suficientes archivos para comparar.");
}

$archivoAnterior = $archivos[count($archivos) - 2];
$archivoNuevo = $archivos[count($archivos) - 1];

$jsonAnterior = json_decode(file_get_contents($archivoAnterior), true);
$jsonNuevo = json_decode(file_get_contents($archivoNuevo), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error al decodificar el JSON.");
}

// Para facilitar la comparación, creamos un mapeo completo (incluyendo 'length' y, si existe, 'html_snippet')
function mappingUrlsConCaracteres($list) {
    $result = [];
    foreach ($list as $entry) {
        if (isset($entry['url']) && isset($entry['length'])) {
            $result[$entry['url']] = $entry;
        }
    }
    return $result;
}

$prevData = mappingUrlsConCaracteres($jsonAnterior['urls_con_caracteres'] ?? []);
$newData  = mappingUrlsConCaracteres($jsonNuevo['urls_con_caracteres'] ?? []);

// Comparación de URLs (como antes)
$desaparecidos = array_diff($jsonAnterior['urls'] ?? [], $jsonNuevo['urls'] ?? []);
$nuevos = array_diff($jsonNuevo['urls'] ?? [], $jsonAnterior['urls'] ?? []);

$posiblesCoincidencias = [];
foreach ($desaparecidos as $urlAntigua) {
    foreach ($nuevos as $urlNueva) {
        $diferencia = levenshtein($urlAntigua, $urlNueva);
        if ($diferencia > 0 && $diferencia < 10) {
            $posiblesCoincidencias[] = [
                'antigua' => $urlAntigua,
                'nueva' => $urlNueva,
                'diferencia' => $diferencia
            ];
        }
    }
}

$countsCambiados = [];
foreach ($prevData as $url => $dataAnterior) {
    if (isset($newData[$url])) {
        $nuevoConteo = $newData[$url]['length'];

        // Obtener el HTML de la página para revisar si tiene "f5_cspm"
        $html = file_get_contents($url);
        $nuevoConteo = strlen($html);

        if (strpos($html, 'f5_cspm') !== false) {
            $nuevoConteo -= 1559; // Restar 1559 caracteres si el código está presente
            //$nuevoConteo = max(0, $nuevoConteo); // Asegurar que el conteo no sea negativo
        }

        // Comparar el nuevo conteo con el anterior
        if ($nuevoConteo !== $dataAnterior['length']) {
            $diff = abs($nuevoConteo - $dataAnterior['length']);

            $countsCambiados[$url] = [
                'anterior' => $dataAnterior['length'],
                'nuevo' => $nuevoConteo,
                'diferencia' => $diff
            ];
        }
    }
}

// Asegurar que la variable esté definida antes de usarla
$mensajeHtml = "";

// Verificar si hay cambios antes de generar el mensaje
if (empty($desaparecidos) && empty($nuevos) && empty($posiblesCoincidencias) && empty($countsCambiados)) {
    $mensajeHtml .= "<p>✅ Todos los links y sus recuentos de caracteres del HTML (normalizados) son iguales. No hay cambios.</p>";
} else {
    $mensajeHtml .= "<h2>⚠️ ¡Se han detectado cambios!</h2>";
    
    if (!empty($desaparecidos)) {
        $mensajeHtml .= "<h3>❌ URLs que han desaparecido:</h3><ul>";
        foreach ($desaparecidos as $url) {
            $mensajeHtml .= "<li>{$url}</li>";
        }
        $mensajeHtml .= "</ul>";
    }

    if (!empty($nuevos)) {
        $mensajeHtml .= "<h3>✅ URLs nuevas encontradas:</h3><ul>";
        foreach ($nuevos as $url) {
            $mensajeHtml .= "<li>{$url}</li>";
        }
        $mensajeHtml .= "</ul>";
    }

    if (!empty($countsCambiados)) {
        $mensajeHtml .= "<h3>📊 URLs con cambios en la cantidad de caracteres del HTML:</h3><ul>";
        foreach ($countsCambiados as $url => $data) {
            $mensajeHtml .= "<li>{$url} - Antes: {$data['anterior']} / Ahora: {$data['nuevo']} (Diferencia: {$data['diferencia']})</li>";
        }
        $mensajeHtml .= "</ul>";
    }
}

// ===============================
// MOSTRAR EL MENSAJE EN PANTALLA
// ===============================
header('Content-Type: text/html; charset=UTF-8');
echo $mensajeHtml;
exit;

/*
// ===============================
// GENERAR TABLA PARA EL EMAIL
// ===============================
$mensajeHtml = "";

if (empty($desaparecidos) && empty($nuevos) && empty($posiblesCoincidencias) && empty($countsCambiados)) {
    $mensajeHtml .= "<p>✅ Todos los links y sus recuentos de caracteres del HTML (normalizados) son iguales. No hay cambios.</p>";
} else {
    $mensajeHtml = "<h2>⚠️ ¡Se han detectado cambios!</h2>";

    if (!empty($desaparecidos)) {
        $mensajeHtml .= "<h3>❌ URLs que han desaparecido:</h3><table border='1'><tr><th>URL</th></tr>";
        foreach ($desaparecidos as $url) {
            $mensajeHtml .= "<tr><td>{$url}</td></tr>";
        }
        $mensajeHtml .= "</table>";
    }

    if (!empty($nuevos)) {
        $mensajeHtml .= "<h3>✅ URLs nuevas encontradas:</h3><table border='1'><tr><th>URL</th></tr>";
        foreach ($nuevos as $url) {
            $mensajeHtml .= "<tr><td>{$url}</td></tr>";
        }
        $mensajeHtml .= "</table>";
    }

    if (!empty($posiblesCoincidencias)) {
        $mensajeHtml .= "<h3>🔎 Posibles coincidencias:</h3><table border='1'><tr><th>Antigua</th><th>Nueva</th><th>Diferencia</th></tr>";
        foreach ($posiblesCoincidencias as $coincidencia) {
            $mensajeHtml .= "<tr><td>{$coincidencia['antigua']}</td><td>{$coincidencia['nueva']}</td><td>{$coincidencia['diferencia']}</td></tr>";
        }
        $mensajeHtml .= "</table>";
    }

    if (!empty($countsCambiados)) {
        $mensajeHtml .= "<h3>📊 Comparativa del recuento de caracteres del HTML (normalizado):</h3>";
        $mensajeHtml .= "<table border='1'><tr><th>URL</th><th>Caracteres Anterior</th><th>Caracteres Nuevo</th><th>Diferencia</th></tr>";
        foreach ($countsCambiados as $url => $data) {
            $mensajeHtml .= "<tr><td>{$url}</td><td>{$data['anterior']}</td><td>{$data['nuevo']}</td><td>{$data['diferencia']}</td></tr>";
        }
        $mensajeHtml .= "</table>";
    }

    // Si estamos en modo debug, se puede incluir información extra (por ejemplo, extractos de HTML)
    if (DEBUG) {
        $mensajeHtml .= "<h3>🐞 Debug: Extractos de HTML (primeros 500 caracteres)</h3>";
        $mensajeHtml .= "<table border='1'><tr><th>URL</th><th>Extracto HTML</th></tr>";
        foreach ($newData as $url => $data) {
            $snippet = $data['html_snippet'] ?? 'N/A';
            $mensajeHtml .= "<tr><td>{$url}</td><td><pre>" . htmlspecialchars($snippet) . "</pre></td></tr>";
        }
        $mensajeHtml .= "</table>";
    }
}


// ===============================
// LEER CREDENCIALES DESDE config.php
// ===============================
function obtenerCredenciales() {
    $rutaArchivo = __DIR__ . '/../config.php';
    
    if (!file_exists($rutaArchivo)) {
        die("❌ Error: No se encuentra el archivo de credenciales en $rutaArchivo");
    }
    
    $lineas = file($rutaArchivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $credenciales = [];
    
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if (strpos($linea, '=') === false) continue;
        list($clave, $valor) = explode('=', $linea, 2);
        $credenciales[trim($clave)] = trim($valor);
    }
    
    return $credenciales;
}

$credenciales = obtenerCredenciales();

$usuario     = $credenciales['USUARIO']      ?? '';
$contrasena  = $credenciales['CONTRASENA']   ?? '';
$mailHost    = $credenciales['MAIL_HOST']    ?? '';
$mailAddress = $credenciales['MAIL_ADDRESS'] ?? '';

if (empty($usuario) || empty($contrasena) || empty($mailHost) || empty($mailAddress)) {
    die("❌ Error: No se pudo obtener alguna de las credenciales necesarias.");
}

// ===============================
// ENVIAR EMAIL CON PHPMailer
// ===============================
$mail = new PHPMailer(true);

try {
    // Forzar uso de SMTP
    $mail->isSMTP();
    $mail->Host       = $mailHost;   // Usamos MAIL_HOST del config
    $mail->SMTPAuth   = true;
    $mail->Username   = $usuario;
    $mail->Password   = $contrasena;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL para puerto 465   
    $mail->Port       = 465;                    // O 587 para TLS
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($mailAddress, 'Notificaciones'); // MAIL_ADDRESS
    $mail->addAddress($mailAddress);

    $mail->isHTML(true);
    $mail->Subject = 'Reporte de cambios en URLs y recuento de caracteres del HTML';
    $mail->Body    = $mensajeHtml;

    // Depuración para ver los errores.
    $mail->SMTPDebug = 2;
    $mail->send();
    echo '✅ Correo enviado exitosamente.';
} catch (Exception $e) {
    echo "❌ Error al enviar el correo. Mailer Error: {$mail->ErrorInfo}";
}
*/