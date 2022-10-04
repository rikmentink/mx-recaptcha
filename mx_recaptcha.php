<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MX_Recaptcha extends Module
{
    private $html;
    private $confirmation;

    public function __construct()
    {
        $this->name = 'mx_recaptcha';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Rik Mentink';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Google reCAPTCHA', [], 'Modules.Mxrecaptcha.Configuration');
        $this->description = $this->trans('reCAPTCHA v3 artificial intelligence protects your website from bots.', [], 'Modules.Mxrecaptcha.Configuration');
        $this->confirmUninstall = $this->trans('Do you want to uninstall the module? Your website\'s forms will be unprotected.', [], 'Modules.Mxrecaptcha.Configuration');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
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
        if (Tools::isSubmit('submitMXRecaptchaConfig'))
        {
            $this->postProcess();
        }

        return $this->displayForm();
    }

    protected function displayForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMXRecaptchaConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) .
            '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        );

        return #$this->getTemplate('top') .
               $this->confirmation .
               $this->html .
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
                    'title' => $this->trans('Configuration', [], 'Modules.Mxrecaptcha.Configuration'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'name' => 'MX_RECAPTCHA_STATUS',
                        'type' => 'switch',
                        'label' => $this->trans('Status', [], 'Modules.Mxrecaptcha.Configuration'),
                        'desc' => $this->trans('Activate reCAPTCHA in live mode.', [], 'Modules.Mxrecaptcha.Configuration'),
                        'class' => 't',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', [], 'Modules.Mxrecaptcha.Configuration'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', [], 'Modules.Mxrecaptcha.Configuration'),
                            ),
                        ),
                    ),
                    array(
                        'name' => 'MX_RECAPTCHA_SITE_KEY',
                        'type' => 'text',
                        'label' => $this->trans('Site Key', [], 'Modules.Mxrecaptcha.Configuration'),
                        'desc' => $this->trans('The reCAPTCHA site key, retrieve this key from your Google account.', [], 'Modules.Mxrecaptcha.Configuration'),
                        'col' => 3,
                    ),
                    array(
                        'name' => 'MX_RECAPTCHA_SECRET_KEY',
                        'type' => 'text',
                        'label' => $this->trans('Secret Key', [], 'Modules.Mxrecaptcha.Configuration'),
                        'desc' => $this->trans('The reCAPTCHA secret key, retrieve this key from your Google account.', [], 'Modules.Mxrecaptcha.Configuration'),
                        'col' => 3,
                    )
                ),
                'submit' => array(
                    'title' => $this->trans('Save', [], 'Modules.Mxrecaptcha.Configuration'),
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
        $error = false;

        $recaptcha = Tools::getValue('MX_RECAPTCHA_STATUS');
        if ($recaptcha != 0 && $recaptcha != 1) {
            $this->html .= $this->displayError($this->trans('Invalid status choice.', [], 'Modules.Mxrecaptcha.Configuration'));
            $error = true;
        }
        else
        {
            Configuration::updateValue('MX_RECAPTCHA_STATUS', $recaptcha);
        }

        $recaptchaSiteKey = Tools::getValue('MX_RECAPTCHA_SITE_KEY');
        if (!$recaptchaSiteKey || empty($recaptchaSiteKey) && ($recaptcha == 1))
        {
            $this->html .= $this->displayError($this->trans('Your public site key is required in order to enable reCAPTCHA.', [], 'Modules.Mxrecaptcha.Configuration'));
            Configuration::updateValue('MX_RECAPTCHA_STATUS', false);
            $error = true;
        }
        else
        {
            Configuration::updateValue('MX_RECAPTCHA_SITE_KEY', $recaptchaSiteKey);
        }

        $recaptchaSecretKey = Tools::getValue('MX_RECAPTCHA_SECRET_KEY');
        if (!$recaptchaSecretKey || empty($recaptchaSecretKey) && ($recaptcha == 1))
        {
            $this->html .= $this->displayError($this->trans('Your secret site key is required in order to enable reCAPTCHA.', [], 'Modules.Mxrecaptcha.Configuration'));
            Configuration::updateValue('MX_RECAPTCHA_STATUS', false);
            $error = true;
        }
        else
        {
            Configuration::updateValue('MX_RECAPTCHA_SECRET_KEY', $recaptchaSecretKey);
        }

        if (!$error)
        {
            $this->confirmation .= $this->displayConfirmation($this->trans('Configuration updated successfully.', [], 'Modules.Mxrecaptcha.Configuration'));
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

    public function isUsingNewTranslationSystem()
    {
        return true;
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
