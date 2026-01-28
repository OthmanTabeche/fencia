<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/classes/LeadDataEntry.php');

class Leaddata_manager extends Module
{
    public function __construct()
    {
        $this->name = 'leaddata_manager';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Tu Nombre';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Gestor de Leads Centralizado');
        $this->description = $this->l('Guarda leads en BD para futura exportación.');
    }

    public function install()
    {
        return parent::install() && $this->installDb();
    }

    public function uninstall()
    {
        return $this->uninstallDb() && parent::uninstall();
    }

    public function installDb()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'leaddata_entries` (
            `id_leaddata_entry` int(11) NOT NULL AUTO_INCREMENT,
            `firstname` varchar(255),
            `lastname` varchar(255),
            `email` varchar(255) NOT NULL,
            `phone` varchar(50),
            `request_type` varchar(50) NOT NULL,
            `message` text,
            `product_name` varchar(255),
            `is_registered` tinyint(1) unsigned DEFAULT 0,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_leaddata_entry`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }
    public function uninstallDb()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'leaddata_entries`';
        return Db::getInstance()->execute($sql);
    }

    /**
     * Función PÚBLICA ESTÁTICA para ser llamada desde controladores
     * 
     * @param array $data Datos del lead
     * @return bool|string True si OK, mensaje de error si falla
     */
    public static function saveLeadFromController($data)
    {
        // FIX: Validación de datos obligatorios
        if (empty($data['email'])) {
            return 'El email es obligatorio';
        }

        if (empty($data['request_type'])) {
            return 'El tipo de solicitud es obligatorio';
        }

        if (!Validate::isEmail($data['email'])) {
            return 'El formato del email no es válido';
        }

        if (!Module::isEnabled('leaddata_manager')) {
            return 'El módulo no está activo';
        }

        try {
            $lead = new LeadDataEntry();
            
            $lead->firstname    = isset($data['firstname']) ? pSQL($data['firstname']) : '';
            $lead->lastname     = isset($data['lastname']) ? pSQL($data['lastname']) : '';
            $lead->email        = pSQL($data['email']);
            $lead->phone        = isset($data['phone']) ? pSQL($data['phone']) : '';
            $lead->request_type = pSQL($data['request_type']);
            
            $lead->message      = isset($data['message']) ? Tools::purifyHTML($data['message']) : '';
            
            $lead->product_name = isset($data['product_name']) ? pSQL($data['product_name']) : '';
            $lead->date_add     = date('Y-m-d H:i:s');
            
            $customer = new Customer();
            $customer->getByEmail($lead->email);
            $lead->is_registered = ($customer->id) ? true : false;

            if (!$lead->save()) {
                return 'Error al guardar en la base de datos';
            }

            return true;

        } catch (Exception $e) {

            PrestaShopLogger::addLog(
                'Leaddata_manager error: ' . $e->getMessage(),
                3, // Error level
                null,
                'LeadDataEntry'
            );
            
            return 'Error inesperado: ' . $e->getMessage();
        }
    }
}