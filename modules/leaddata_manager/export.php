<?php
/**
 * EXPORTADOR DE LEADS A CSV
 * Ruta: /modules/leaddata_manager/export.php
 * Uso: https://tudominio.com/modules/leaddata_manager/export.php?token=YOUR_SECRET_TOKEN
 */

// Cargar entorno PrestaShop
require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');

// Token de seguridad - CAMBIAR EN PRODUCCIÓN
$tokenSecreto = defined('LEADDATA_EXPORT_TOKEN') ? LEADDATA_EXPORT_TOKEN : "FENCIA_EXPORT_KEY_2026";

// Protección básica contra CSV Injection
function protectCSVInjection($value) {
    if (empty($value)) return $value;
    
    $dangerousChars = ['=', '+', '-', '@', "\t", "\r"];
    
    if (in_array(substr($value, 0, 1), $dangerousChars)) {
        $value = "'" . $value;
    }
    
    return $value;
}

// Validación de token (seguridad crítica)
if (Tools::getValue('token') !== $tokenSecreto) {
    header('HTTP/1.0 403 Forbidden');
    PrestaShopLogger::addLog('Intento de acceso no autorizado a export.php', 2);
    die('Error: Acceso denegado. Token incorrecto.');
}

// Verificar existencia de tabla
if (!Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'leaddata_entries"')) {
    header('HTTP/1.0 500 Internal Server Error');
    PrestaShopLogger::addLog('Tabla leaddata_entries no existe', 3);
    die('Error: La tabla de leads no existe.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leads_export_' . date('Y-m-d_His') . '.csv');

$output = fopen('php://output', 'w');

if (!$output) {
    header('HTTP/1.0 500 Internal Server Error');
    die('Error: No se pudo generar el archivo CSV.');
}

// Cabeceras CSV
fputcsv($output, ['ID', 'Nombre', 'Apellidos', 'Email', 'Telefono', 'Tipo Solicitud', 'Mensaje', 'Producto', 'Fecha', 'Es Cliente?']);

$sql = 'SELECT 
            id_leaddata_entry,
            firstname,
            lastname,
            email,
            phone,
            request_type,
            message,
            product_name,
            date_add,
            is_registered
        FROM ' . _DB_PREFIX_ . 'leaddata_entries 
        ORDER BY date_add DESC';

$rows = Db::getInstance()->executeS($sql);

if ($rows === false) {
    fclose($output);
    header('HTTP/1.0 500 Internal Server Error');
    PrestaShopLogger::addLog('Error en consulta SQL export.php: ' . Db::getInstance()->getMsgError(), 3);
    die('Error: No se pudieron obtener los datos.');
}

foreach ($rows as $row) {
    $mensajeLimpio = str_replace(["\r", "\n"], " ", $row['message']);
    
    fputcsv($output, [
        protectCSVInjection($row['id_leaddata_entry']),
        protectCSVInjection($row['firstname']),
        protectCSVInjection($row['lastname']),
        protectCSVInjection($row['email']),
        protectCSVInjection($row['phone']),
        protectCSVInjection($row['request_type']),
        protectCSVInjection($mensajeLimpio),
        protectCSVInjection($row['product_name']),
        protectCSVInjection($row['date_add']),
        $row['is_registered'] == 1 ? 'SI' : 'NO'
    ]);
}

fclose($output);
exit;