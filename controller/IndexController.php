<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
class IndexControllerCore extends FrontController
{
    /** @var string */
    public $php_self = 'index';

    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        //  if(Tools::getRemoteAddr() === '190.97.237.149') {
        //      $this->context->updateCustomer(new Customer(877));
        //  }

        // Verificar si es una petición AJAX
        if (Tools::getValue('ajax') == '1' && Tools::getValue('action') == 'sendProductEmail') {
            $this->processAjaxRequest();
        }
        if (Tools::getValue('ajax') == '3' && Tools::getValue('action') == 'sendProductEmailAuction') {
            $this->processAjaxRequestBtnAuctionEmail();
        }

        /* -----------------------------------------------------------
        * INICIO: SOLO PARA TEST
        * ----------------------------------------------------------- */
        if (Tools::getValue('ajax') == '2' && Tools::getValue('action') == 'emailTest') {
            $this->processAjaxRequestTest();
        }
        /* -----------------------------------------------------------
        * FIN: SOLO PARA TEST
        * ----------------------------------------------------------- */

        parent::initContent();
        $this->context->smarty->assign([
            'HOOK_HOME' => Hook::exec('displayHome'),
        ]);
        $this->setTemplate('index');
    }


    private function processAjaxRequest()
    {
        // Establecer cabeceras JSON
        header('Content-Type: application/json');
        
        try {
            // Verificar token CSRF para seguridad (opcional pero recomendado)
            // if (!Tools::checkToken()) {
            //     throw new Exception('Token inválido');
            // }
            
            $productId = (int)Tools::getValue('product_id');
            $productName = Tools::getValue('product_name');
            $productReference = Tools::getValue('product_reference');
            $productLink = Tools::getValue('product_link');
            
            if (!$productId) {
                throw new Exception('ID de producto no válido');
            }
            
            // Obtener información del cliente
            $customer = $this->context->customer;
            $customerName = 'Visitante';
            $customerEmail = 'No registrado';
            
            if (Validate::isLoadedObject($customer) && $customer->id) {
                $customerName = $customer->firstname . ' ' . $customer->lastname;
                $customerEmail = $customer->email;
            }
            
            // Dirección de correo de la tienda
            // $to = Configuration::get('PS_SHOP_EMAIL');
            // if (!$to) {
            //     $to = Configuration::get('PS_SHOP_EMAIL'); // Email por defecto
            // }
            //$to = "leads@fencia.es";
            $to = "sam@hostienda.com";
            
            // Asunto del correo
            $subject = 'Solicitud información - Producto: ' . $productName;
            
            // Preparar variables para el template de email
            $templateVars = array(
                '{product_name}' => $productName,
                '{product_id}' => $productId,
                '{product_reference}' => $productReference,
                '{product_link}' => $productLink,
                '{customer_name}' => $customerName,
                '{customer_email}' => $customerEmail,
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{date}' => date('d/m/Y - H:i:s'),
                '{message}' => 'El cliente desea recibir más información sobre este producto.'
            );

            /* -----------------------------------------------------------
             * INICIO: INTERCEPCIÓN GESTOR DE LEADS (BOTÓN VERDE)
             * ----------------------------------------------------------- */
            try {
                if (Module::isEnabled('leaddata_manager')) {
                    $modulePath = _PS_MODULE_DIR_ . 'leaddata_manager/leaddata_manager.php';
                    if (file_exists($modulePath)) {
                        require_once($modulePath);
                        if (class_exists('Leaddata_manager')) {
                            
                            // Preparamos los datos
                            $leadData = [
                                'firstname'    => $customerName,
                                'lastname'     => '', 
                                'email'        => $customerEmail,
                                'phone'        => '', // Si el formulario enviase teléfono: Tools::getValue('phone')
                                'request_type' => 'Solicitud Info Producto', // Diferenciamos del botón azul
                                'message'      => 'Solicita información. Ref: ' . ($productReference ? $productReference : 'N/A'),
                                'product_name' => $productName ? $productName : 'Producto ID: ' . $productId
                            ];

                            // Guardamos en la BD
                            Leaddata_manager::saveLeadFromController($leadData);
                        }
                    }
                }
            } catch (Exception $e) {
                // Si falla, no detenemos el proceso, solo registramos el error
                PrestaShopLogger::addLog('Error Leaddata GreenBtn: ' . $e->getMessage(), 3);
            }
            
            // Enviar correo
            $mailSent = Mail::Send(
                (int)$this->context->language->id,
                'contact_send_email', // Usar el template de contacto existente
                //Mail::l($subject, (int)$this->context->language->id),
                $subject,
                $templateVars,
                $to,
                null,
                Configuration::get('PS_SHOP_EMAIL'),
                Configuration::get('PS_SHOP_NAME'),
                null,
                null,
                _PS_MAIL_DIR_,
                false,
                (int)$this->context->shop->id
            );
            
            if ($mailSent) {
                echo json_encode([
                    'success' => true,
                    'message' => $this->trans('Correo enviado correctamente. Te contactaremos pronto.', [], 'Shop.Theme.Actions')
                ]);
            } else {
                throw new Exception('No se pudo enviar el correo. Verifica la configuración de email.');
            }
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $this->trans('Error: ', [], 'Shop.Theme.Actions') . $e->getMessage()
            ]);
        }
        
        exit;
    }



private function processAjaxRequestBtnAuctionEmail()
    {
        // Establecer cabeceras JSON
        header('Content-Type: application/json');
        
        try {
            // Verificar token CSRF para seguridad (opcional pero recomendado)
            // if (!Tools::checkToken()) {
            //     throw new Exception('Token inválido');
            // }
            
            $productId = (int)Tools::getValue('product_id');
            $productName = Tools::getValue('product_name');
            $productReference = Tools::getValue('product_reference');
            $productLink = Tools::getValue('product_link');
            
            if (!$productId) {
                throw new Exception('ID de producto no válido');
            }
            
            // Obtener información del cliente
            $customer = $this->context->customer;
            $customerName = 'Visitante';
            $customerEmail = 'No registrado';
            
            if (Validate::isLoadedObject($customer) && $customer->id) {
                $customerName = $customer->firstname . ' ' . $customer->lastname;
                $customerEmail = $customer->email;
            }
            
            // Dirección de correo de la tienda
            // $to = Configuration::get('PS_SHOP_EMAIL');
            // if (!$to) {
            //     $to = Configuration::get('PS_SHOP_EMAIL'); // Email por defecto
            // }

            //$to = "leads@fencia.es";
            $to = "";
            
            // Asunto del correo
            $subject = 'Solicitud de aviso disponible - Producto: ' . $productName;
            
            // Preparar variables para el template de email
            $templateVars = array(
                '{product_name}' => $productName,
                '{product_id}' => $productId,
                '{product_reference}' => $productReference,
                '{product_link}' => $productLink,
                '{customer_name}' => $customerName,
                '{customer_email}' => $customerEmail,
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{date}' => date('d/m/Y - H:i:s'),
                '{message}' => 'El cliente desea recibir más información sobre este producto.'
            );
            
            try {
                // 1. Verificamos si el módulo está activo
                if (Module::isEnabled('leaddata_manager')) {
                    
                    // 2. Cargamos el módulo manualmente por si acaso
                    $modulePath = _PS_MODULE_DIR_ . 'leaddata_manager/leaddata_manager.php';
                    if (file_exists($modulePath)) {
                        require_once($modulePath);
                        
                        // 3. Verificamos que la clase existe
                        if (class_exists('Leaddata_manager')) {
                            
                            // 4. Preparamos los datos
                            // Como el usuario ya está logueado, los datos son seguros
                            $leadData = [
                                'firstname'    => isset($customerName) ? $customerName : 'Visitante',
                                'lastname'     => '', 
                                'email'        => isset($customerEmail) ? $customerEmail : '',
                                'phone'        => '', 
                                'request_type' => 'Lista de Espera',
                                'message'      => 'Subasta/Espera. Ref: ' . (isset($productReference) ? $productReference : 'N/A'),
                                'product_name' => isset($productName) ? $productName : 'Producto ID: ' . $productId
                            ];

                            // 5. Guardamos en la BD
                            Leaddata_manager::saveLeadFromController($leadData);
                        }
                    }
                }
            } catch (Exception $e) {
                // Si falla, registramos el error pero NO paramos el email
                PrestaShopLogger::addLog('Error Leaddata: ' . $e->getMessage(), 3);
            }
            /* -----------------------------------------------------------
             * FIN INTERCEPCIÓN
             * ----------------------------------------------------------- */
            
            // Enviar correo
            $mailSent = Mail::Send(
                (int)$this->context->language->id,
                'contact_send_email', // Usar el template de contacto existente
                //Mail::l($subject, (int)$this->context->language->id),
                $subject,
                $templateVars,
                $to,
                null,
                Configuration::get('PS_SHOP_EMAIL'),
                Configuration::get('PS_SHOP_NAME'),
                null,
                null,
                _PS_MAIL_DIR_ . 'myalerts/mails/',
                false,
                (int)$this->context->shop->id
            );
            
            if ($mailSent) {
                echo json_encode([
                    'success' => true,
                    'message' => $this->trans('Correo enviado correctamente. Te contactaremos pronto.', [], 'Shop.Theme.Actions')
                ]);
            } else {
                throw new Exception('No se pudo enviar el correo. Verifica la configuración de email.');
            }
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $this->trans('Error: ', [], 'Shop.Theme.Actions') . $e->getMessage()
            ]);
        }
        
        exit;
    }


    /* -----------------------------------------------------------
    * INICIO: SOLO PARA TEST
    * ----------------------------------------------------------- */    
    private function processAjaxRequestTest()
    {
        // Establecer cabeceras JSON
        header('Content-Type: application/json');
        
        try {
            // Verificar token CSRF para seguridad (opcional pero recomendado)
            // if (!Tools::checkToken()) {
            //     throw new Exception('Token inválido');
            // }
            
           /* $productId = (int)Tools::getValue('product_id');
            $productName = Tools::getValue('product_name');
            $productReference = Tools::getValue('product_reference');
            $productLink = Tools::getValue('product_link');
            
            if (!$productId) {
                throw new Exception('ID de producto no válido');
            }
            */
            // Obtener información del cliente
            $customer = $this->context->customer;
            $customerName = 'Visitante';
            $customerEmail = 'No registrado';
            
            if (Validate::isLoadedObject($customer) && $customer->id) {
                $customerName = $customer->firstname . ' ' . $customer->lastname;
                $customerEmail = $customer->email;
            }
            
            // Dirección de correo de la tienda
            // $to = Configuration::get('PS_SHOP_EMAIL');
            // if (!$to) {
            //     $to = Configuration::get('PS_SHOP_EMAIL'); // Email por defecto
            // }
            $to = "";            
            // Asunto del correo
            $subject = 'Test email order_conf';
            
            // Preparar variables para el template de email
            $templateVars = array(                
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{date}' => date('d/m/Y - H:i:s'),
                '{message}' => 'Test'
            );
            
            // Enviar correo
            $mailSent = Mail::Send(
                (int)$this->context->language->id,
                'order_conf', // Usar el template de contacto existente
                //Mail::l($subject, (int)$this->context->language->id),
                $subject,
                $templateVars,
                $to,
                null,
                Configuration::get('PS_SHOP_EMAIL'),
                Configuration::get('PS_SHOP_NAME'),
                null,
                null,
                _PS_MODULE_DIR_,
                false,
                (int)$this->context->shop->id
            );
            
            if ($mailSent) {
                echo json_encode([
                    'success' => true,
                    'message' => $this->trans('Correo enviado correctamente.', [], 'Shop.Theme.Actions')
                ]);
            } else {
                throw new Exception('No se pudo enviar el correo. Verifica la configuración de email.');
            }
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $this->trans('Error: ', [], 'Shop.Theme.Actions') . $e->getMessage()
            ]);
        }
        
        exit;
    }
    /* -----------------------------------------------------------
    * FIN: SOLO PARA TEST
    * ----------------------------------------------------------- */


}