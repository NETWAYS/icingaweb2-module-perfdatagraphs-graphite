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
            'description' => t('Graphite-API URL'),
            'label' => 'Graphite-API URL'
        ]);

        $this->addElement('text', 'graphite_api_username', [
            'description' => t('Graphite-API Username for HTTP Basic Auth'),
            'label' => 'Graphite-API Basic Auth User'
        ]);

        $this->addElement('password', 'graphite_api_password', [
            'description' => t('Graphite-API Password for HTTP Basic Auth'),
            'label' => 'Graphite-API Basic Auth Password',
            'renderPassword' => true
        ]);

        $this->addElement('number', 'graphite_api_timeout', [
            'description' => t('Graphite-API timeout in seconds'),
            'label' => 'Graphite-API timeout in seconds'
        ]);

        $this->addElement('checkbox', 'graphite_api_tls_insecure', [
            'description' => t('Skip the TLS verification'),
            'label' => 'Skip the TLS verification'
        ]);

        $this->addElement(
            'text',
            'graphite_writer_host_name_template',
            [
                'label' => $this->translate('Host name template'),
                'description' => $this->translate(
                    'The value of your Icinga 2 GraphiteWriter\'s'
                        . ' attribute host_name_template (if specified)'
                ),
            ]
        );

        $this->addElement(
            'text',
            'graphite_writer_service_name_template',
            [
                'label' => $this->translate('Service name template'),
                'description' => $this->translate(
                    'The value of your Icinga 2 GraphiteWriter\'s'
                        . ' attribute service_name_template (if specified)'
                ),
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
                'data-progress-label' => $this->translate('Validation In Progress'),
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
        $username = $form->getValue('graphite_api_username', '');
        $password = $form->getValue('graphite_api_password', '');
        $tlsVerify = (bool) $form->getValue('graphite_api_tls_insecure', false);
        $hostTemplate = $form->getValue('graphite_writer_host_name_template', '');
        $serviceTemplate = $form->getValue('graphite_writer_service_name_template', '');

        try {
            $c = new Graphite($baseURI, $username, $password, $timeout, $tlsVerify, $hostTemplate, $serviceTemplate);
        } catch (Exception $e) {
            return ['output' => 'General error: ' . $e->getMessage(), 'error' => true];
        }

        $status = $c->status();

        return $status;
    }
}
