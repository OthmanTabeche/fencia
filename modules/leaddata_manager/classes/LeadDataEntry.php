<?php
class LeadDataEntry extends ObjectModel
{
    public $id_leaddata_entry;
    public $firstname;
    public $lastname;
    public $email;
    public $phone;
    public $request_type; // 'Contacto', 'Producto', 'ListaEspera'
    public $message;
    public $product_name;
    public $is_registered;
    public $date_add;

    public static $definition = [
        'table' => 'leaddata_entries',
        'primary' => 'id_leaddata_entry',
        'fields' => [
            'firstname'    => ['type' => self::TYPE_STRING, 'validate' => 'isName', 'size' => 255],
            'lastname'     => ['type' => self::TYPE_STRING, 'validate' => 'isName', 'size' => 255],
            'email'        => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'required' => true, 'size' => 255],
            'phone'        => ['type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'size' => 50],
            'request_type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 50],
            'message'      => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'],
            'product_name' => ['type' => self::TYPE_STRING, 'validate' => 'isCatalogName', 'size' => 255],
            'is_registered'=> ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'date_add'     => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];
}