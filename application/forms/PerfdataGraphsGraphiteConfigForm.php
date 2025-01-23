<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Forms;

use Icinga\Forms\ConfigForm;

use Exception;
use GuzzleHttp\Client;

/**
 * PerfdataGraphsGraphiteConfigForm represents the configuration form for the PerfdataGraphs Graphite Module.
 */
class PerfdataGraphsGraphiteConfigForm extends ConfigForm
{
    public function init()
    {
        $this->setName('form_config_resource');
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
            'description' => t('Graphite-API Username'),
            'label' => 'Graphite-API User'
        ]);

        $this->addElement('text', 'graphite_api_password', [
            'description' => t('Graphite-API Password'),
            'label' => 'Graphite-API Password'
        ]);

        $this->addElement('number', 'graphite_api_timeout', [
            'description' => t('Graphite-API timeout in seconds'),
            'label' => 'Graphite-API timeout in seconds'
        ]);

        $this->addElement('checkbox', 'graphite_api_tls_insecure', [
            'description' => t('Skip the TLS verification'),
            'label' => 'Skip the TLS verification'
        ]);
    }

    public function addSubmitButton()
    {
        parent::addSubmitButton()
            ->getElement('btn_submit')
            ->setDecorators(['ViewHelper']);

        $this->addElement(
            'submit',
            'resource_validation',
            [
                'ignore' => true,
                'label' => $this->translate('Validate Configuration'),
                'data-progress-label' => $this->translate('Validation In Progress'),
                'decorators' => ['ViewHelper']
            ]
        );

        $this->setAttrib('data-progress-element', 'resource-progress');
        $this->addElement(
            'note',
            'resource-progress',
            [
                'decorators' => [
                    'ViewHelper',
                    ['Spinner', ['id' => 'resource-progress']]
                ]
            ]
        );

        $this->addDisplayGroup(
            ['btn_submit', 'resource_validation', 'resource-progress'],
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
        if ($this->getElement('resource_validation')->isChecked() && parent::isValid($formData)) {
            $validation = static::validateFormData($this);
            if ($validation !== null) {
                $this->addElement(
                    'note',
                    'inspection_output',
                    [
                        'order' => 0,
                        'value' => '<strong>' . $this->translate('Validation Log') . "</strong>\n\n"
                            . join("\n", $validation['output'] ?? []),
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

    public static function validateFormData($form)
    {
        try {
            $client = new Client([
                'base_uri' => $form->getValue('graphite_api_url', 'http://localhost:8081'),
                'timeout' => (int) $form->getValue('graphite_api_timeout', 10),
                'verify' => (bool) $form->getValue('graphite_api_tls_insecure'),
                'auth' => [$form->getValue('graphite_api_username'), $form->getValue('graphite_api_password')]
            ]);

            $response = $client->get('/metrics');
        } catch (\Exception $e) {
            return ['error' => 'Connection not successful', 'output' => [$e]];
        }

        if ($response->getStatusCode() == 200) {
            return ['output' => ['Connection successful']];
        }

        return ['error' => 'Connection not successful', 'output' => []];
    }
}
