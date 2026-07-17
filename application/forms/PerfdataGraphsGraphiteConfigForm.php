<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Forms;

use Icinga\Module\Perfdatagraphsgraphite\Client\Graphite;

use Icinga\Forms\ConfigForm;

use Exception;

/**
 * PerfdataGraphsGraphiteConfigForm represents the configuration form for the PerfdataGraphs Graphite Module.
 * TODO: Icinga Web 2.14 introduced a new Web\Form\ConfigForm, we can migrate when 2.14 is more prevalent
 * Then we can also use ipl Validators.
 */
class PerfdataGraphsGraphiteConfigForm extends ConfigForm
{
    public function init()
    {
        $this->setName('form_config_perfdatagraphite');
        $this->setSubmitLabel($this->translate('Save Changes'));
        $this->setValidatePartial(true);
    }

    public function createElements(array $formData)
    {
        $this->addElement('text', 'graphite_api_url', [
            'label' => t('API URL'),
            'description' => t('The URL for Graphite including the scheme'),
            'required' => true,
            'placeholder' => 'http://localhost:8081',
        ]);

        $this->addElement('select', 'graphite_api_auth_method', [
            'label' => 'API authentication method',
            'description' => 'Authentication method to use for the API',
            'multiOptions' => [
                'none' => t('None'),
                'basic' => 'Basic Auth',
                'token' => 'Token',
            ],
            'class' => 'autosubmit',
            'required' => false,
        ]);

        if (isset($formData['graphite_api_auth_method']) && $formData['graphite_api_auth_method'] === 'basic') {
            $this->addElement('text', 'graphite_api_auth_username', [
                'label' => t('HTTP basic auth username'),
                'description' => t('The user for HTTP basic auth'),
                'required' => true,
            ]);

            $this->addElement('password', 'graphite_api_auth_password', [
                'label' => t('HTTP basic auth password'),
                'description' => t('The password for HTTP basic auth'),
                'renderPassword' => true,
                'required' => true,
            ]);
        }

        if (isset($formData['graphite_api_auth_method']) && $formData['graphite_api_auth_method'] === 'token') {
            $this->addElement('text', 'graphite_api_auth_tokentype', [
                'label' => t('Token type for the Authorization header'),
                'description' => t('API Token type for the Authorization header (default: Bearer)'),
                'value' => 'Bearer',
            ]);

            $this->addElement('password', 'graphite_api_auth_tokenvalue', [
                'label' => t('Token for the Authorization header'),
                'description' => t('API Token for the Authorization header'),
                'renderPassword' => true,
                'required' => true,
            ]);
        }

        $this->addElement('checkbox', 'graphite_api_auth_mtls', [
            'label' => t('Use client certificate (mTLS)'),
            'description' => t('Use client certificate (mTLS) for the connection'),
            'class' => 'autosubmit',
        ]);

        if ($formData['graphite_api_auth_mtls'] === '1') {
            $this->addElement('text', 'graphite_api_auth_mtls_cert', [
                'label' => t('mTLS client certificate path'),
                'description' => t('Path to the client certificate'),
                'required' => true,
            ]);
            $this->addElement('text', 'graphite_api_auth_mtls_key', [
                'label' => t('mTLS client key path'),
                'description' => t('Path to the client key'),
                'required' => true,
            ]);
            $this->addElement('text', 'graphite_api_auth_mtls_ca', [
                'label' => t('mTLS client CA path'),
                'description' => t('Path to the CA. Defaults to system CA'),
                'required' => false,
            ]);
        }

        $this->addElement('number', 'graphite_api_timeout', [
            'label' => t('HTTP timeout in seconds'),
            'description' => t('HTTP timeout for the API in seconds. Should be higher than 0'),
            'required' => true,
            'placeholder' => 10,
        ]);

        $this->addElement('number', 'graphite_max_data_points', [
            'label' => t('The maximum numbers of datapoints each series returns'),
            'description' => t('The maximum numbers of datapoints each series returns. You can disable aggregation by setting this to 0.'),
            'required' => false,
            'placeholder' => 10000,
        ]);

        $this->addElement('checkbox', 'graphite_api_tls_insecure', [
            'description' => t('Skip the TLS verification'),
            'label' => t('Skip the TLS verification')
        ]);

        $this->addElement(
            'text',
            'graphite_writer_host_name_template',
            [
                'label' => t('Host name template'),
                'description' => t(
                    'The value of your Icinga 2 GraphiteWriter\'s'
                        . ' attribute host_name_template (if specified)'
                ),
                'placeholder' => 'icinga2.$host.name$.host.$host.check_command$',
            ]
        );

        $this->addElement(
            'text',
            'graphite_writer_service_name_template',
            [
                'label' => t('Service name template'),
                'description' => t(
                    'The value of your Icinga 2 GraphiteWriter\'s'
                        . ' attribute service_name_template (if specified)'
                ),
                'placeholder' => 'icinga2.$host.name$.services.$service.name$.$service.check_command$',
            ]
        );
    }

    public function addSubmitButton()
    {
        parent::addSubmitButton()
            ->getElement('btn_submit')
            ->setDecorators(['ViewHelper']);

        $this->addElement(
            'submit',
            'backend_validation',
            [
                'ignore' => true,
                'label' => $this->translate('Validate Configuration'),
                'data-progress-label' => $this->translate('Validation in Progress'),
                'decorators' => ['ViewHelper']
            ]
        );

        $this->setAttrib('data-progress-element', 'backend-progress');
        $this->addElement(
            'note',
            'backend-progress',
            [
                'decorators' => [
                    'ViewHelper',
                    ['Spinner', ['id' => 'backend-progress']]
                ]
            ]
        );

        $this->addDisplayGroup(
            ['btn_submit', 'backend_validation', 'backend-progress'],
            'submit_validation',
            [
                'decorators' => [
                    'FormElements',
                    ['HtmlTag', ['tag' => 'div', 'class' => 'control-group form-controls']]
                ]
            ]
        );

        return $this;
    }

    public function isValidPartial(array $formData)
    {
        if ($this->getElement('backend_validation')->isChecked() && parent::isValid($formData)) {
            $validation = static::validateFormData($this);
            if ($validation !== null) {
                $this->addElement(
                    'note',
                    'inspection_output',
                    [
                        'order' => 0,
                        'value' => '<strong>' . $this->translate('Validation Log') . "</strong>\n\n"
                            . $validation['output'],
                        'decorators' => [
                            'ViewHelper',
                            ['HtmlTag', ['tag' => 'pre', 'class' => 'log-output']],
                        ]
                    ]
                );

                if (isset($validation['error'])) {
                    $this->warning(sprintf(
                        $this->translate('Failed to successfully validate the configuration: %s'),
                        $validation['error']
                    ));
                    return false;
                }
            }

            $this->info($this->translate('The configuration has been successfully validated.'));
        }

        return true;
    }

    public static function validateFormData($form): array
    {
        $baseURI = $form->getValue('graphite_api_url', 'http://localhost:8081');
        $timeout = (int) $form->getValue('graphite_api_timeout', 10);
        $maxDataPoints = (int) $form->getValue('graphite_max_data_points', 10000);
        // Auth values
        $authMethod = $form->getValue('graphite_api_auth_method', 'none');
        $authTokenType = $form->getValue('graphite_api_auth_tokentype', 'Bearer');
        $authTokenValue = $form->getValue('graphite_api_auth_tokenvalue', '');
        $authUsername = $form->getValue('graphite_api_auth_username', '');
        $authPassword = $form->getValue('graphite_api_auth_password', '');
        // mTLS values
        $authMTLS = $form->getValue('graphite_api_auth_mtls', false);
        $authMTLSCert = $form->getValue('graphite_api_auth_mtls_cert', '');
        $authMTLSKey = $form->getValue('graphite_api_auth_mtls_key', '');
        $authMTLSCA = $form->getValue('graphite_api_auth_mtls_ca', '');
        // Hint: We use a "skip TLS" logic in the UI, but Guzzle uses "verify TLS"
        $tlsVerify = !(bool) $form->getValue('graphite_api_tls_insecure', false);
        $hostTemplate = $form->getValue('graphite_writer_host_name_template', '');
        $serviceTemplate = $form->getValue('graphite_writer_service_name_template', '');

        $auth = [
            'method' => mb_strtolower($authMethod),
            'tokentype' => $authTokenType,
            'tokenvalue' => $authTokenValue,
            'username' => $authUsername,
            'password' => $authPassword,
            'mtls' => $authMTLS,
            'mtls_cert' => $authMTLSCert,
            'mtls_key' => $authMTLSKey,
            'mtls_ca' => $authMTLSCA,
        ];

        try {
            $c = new Graphite(
                baseURI: $baseURI,
                timeout: $timeout,
                maxDataPoints: $maxDataPoints,
                tlsVerify: $tlsVerify,
                hostNameTemplate: $hostTemplate,
                serviceNameTemplate: $serviceTemplate,
                auth: $auth,
            );
        } catch (Exception $e) {
            return ['output' => 'General error: ' . $e->getMessage(), 'error' => true];
        }

        $status = $c->status();

        return $status;
    }
}
