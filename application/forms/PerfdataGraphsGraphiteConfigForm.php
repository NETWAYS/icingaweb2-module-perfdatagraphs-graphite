<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Forms;

use Icinga\Module\Perfdatagraphsgraphite\Client\Graphite;

use Icinga\Forms\ConfigForm;

use Exception;

/**
 * PerfdataGraphsGraphiteConfigForm represents the configuration form for the PerfdataGraphs Graphite Module.
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

        $this->addElement('text', 'graphite_api_username', [
            'label' => t('API basic auth username'),
            'description' => t('The user for HTTP basic auth. Not used if empty')
        ]);

        $this->addElement('password', 'graphite_api_password', [
            'label' => t('API HTTP basic auth password'),
            'description' => t('The password for HTTP basic auth. Not used if empty'),
            'renderPassword' => true
        ]);

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
        $username = $form->getValue('graphite_api_username', '');
        $password = $form->getValue('graphite_api_password', '');
        // Hint: We use a "skip TLS" logic in the UI, but Guzzle uses "verify TLS"
        $tlsVerify = !(bool) $form->getValue('graphite_api_tls_insecure', false);
        $hostTemplate = $form->getValue('graphite_writer_host_name_template', '');
        $serviceTemplate = $form->getValue('graphite_writer_service_name_template', '');

        try {
            $c = new Graphite($baseURI, $username, $password, $timeout, $tlsVerify, $maxDataPoints, $hostTemplate, $serviceTemplate);
        } catch (Exception $e) {
            return ['output' => 'General error: ' . $e->getMessage(), 'error' => true];
        }

        $status = $c->status();

        return $status;
    }
}
