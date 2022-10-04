<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MX_Recaptcha extends Module
{
    public function __construct()
    {
        $this->name = 'mx_recaptcha';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Rik Mentink';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Google reCAPTCHA');
        $this->description = $this->l('reCAPTCHA v3 artificial intelligence protects your website from bots.');
        $this->confirmUninstall = $this->l('Do you want to uninstall the module? Your website\'s forms will be unprotected.');
        $this->ps_versions_compliance = array('min' => '1.7', 'max' => _PS_VERSION);
    }

    public function install()
    {
        $result =
            parent::install() &&
            Configuration::updateValue('MX_RECAPTCHA_STATUS', false) &&
            Configuration::updateValue('MX_RECAPTCHA_SITE_KEY', '') &&
            Configuration::updateValue('MX_RECAPTCHA_SECRET_KEY', '') &&

            $this->registerHook('displayCaptchaTest');
            # TODO: Register all hooks

        return $result;
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('MX_RECAPTCHA_STATUS')
            && Configuration::updateValue('MX_RECAPTCHA_SITE_KEY')
            && Configuration::updateValue('MX_RECAPTCHA_SECRET_KEY');
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitMXRecaptchaModule'))
        {
            $this->postProcess();
        }

        return $this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMXRecaptchaModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) .
            '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        );

        $notification = '';

        if (Tools::isSubmit('submitMXRecaptchaModule'))
        {
            if (empty($this->_errors))
            {
                $notification = $this->displayConfirmation($this->l('Configuration has been updated successfully!'));
            }
            else
            {
                $notification = $this->displayError($this->l('An error occured while saving the configuration.'));
            }
        }

        return #$this->getTemplate('top') .
               $notification .
               $helper->generateForm(array($this->getConfigForm()));
               #$this->getTemplate('bottom');
    }

    protected function getConfigForm()
    {
        $this->context->smarty->assign(array(
            'iso'      => Tools::strtoupper($this->getIsoLangForLinks()),
            'base_url' => _MODULE_DIR_ . $this->name . DIRECTORY_SEPARATOR,
        ));

        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuration'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'name' => 'MX_RECAPTCHA_STATUS',
                        'type' => 'switch',
                        'label' => $this->l('Status'),
                        'desc' => $this->l('Activate reCAPTCHA in live mode.'),
                        'class' => 't',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'name' => 'MX_RECAPTCHA_SITE_KEY',
                        'type' => 'text',
                        'label' => $this->l('Site Key'),
                        'desc' => $this->l('The reCAPTCHA site key, retrieve this key from your Google account.'),
                        'col' => 3,
                    ),
                    array(
                        'name' => 'MX_RECAPTCHA_SECRET_KEY',
                        'type' => 'text',
                        'label' => $this->l('Secret Key'),
                        'desc' => $this->l('The reCAPTCHA secret key, retrieve this key from your Google account.'),
                        'col' => 3,
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            )
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'MX_RECAPTCHA_STATUS'     => Configuration::get('MX_RECAPTCHA_STATUS'),
            'MX_RECAPTCHA_SITE_KEY'   => Configuration::get('MX_RECAPTCHA_SITE_KEY'),
            'MX_RECAPTCHA_SECRET_KEY' => Configuration::get('MX_RECAPTCHA_SECRET_KEY'),
        );
    }

    private function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key)
        {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    private function getTemplate($template)
    {
        $this->context->smarty->assign(array(
            'iso'      => Tools::strtoupper($this->getIsoLangForLinks()),
            'base_url' => _MODULE_DIR_ . $this->name . DIRECTORY_SEPARATOR,
        ));

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR . $this->name .
            DIRECTORY_SEPARATOR . 'views' .
            DIRECTORY_SEPARATOR . 'templates' .
            DIRECTORY_SEPARATOR . 'admin' .
            DIRECTORY_SEPARATOR . $template . '.tpl'
        );
    }





    public function getIsoLangForLinks()
    {
        $langs = array('en', 'nl');
        $current_lang = $this->context->language->iso_code;

        $iso = (in_array($current_lang, $langs)) ? $current_lang : 'en';
        return $iso;
    }

    public function getOverrides()
    {
        if (!is_dir($this->getLocalPath() . 'override'))
        {
            return null;
        }

        $result = [];

        # TODO: getOverrides()

        return $result;
    }

    public function installOverrides()
    {
        if (!is_dir($this->getLocalPath() . 'override'))
        {
            return true;
        }

        $result = true;

        # TODO: installOverrides()

        return $result;
    }

    public function uninstallOverrides()
    {
        if (!is_dir($this->getLocalPath() . 'override'))
        {
            return true;
        }

        $result = true;

        # TODO: uninstallOverrides()

        return $result;
    }
}
