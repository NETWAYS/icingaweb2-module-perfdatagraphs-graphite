<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Forms;

use ipl\Web\Compat\CompatForm;

/**
 * PerfdataGraphsGraphiteConfigForm represents the configuration form for the PerfdataGraphs Graphite Module.
 */
class PerfdataGraphsGraphiteConfigForm extends CompatForm
{
    /**
     * assemble the configuration form with all available options.
     */
    protected function assemble(): void
    {
        $this->addElement('text', 'graphite_api_url', [
            'description' => t('Graphite-API URL'),
            'label' => 'Graphite-API URL'
        ]);

        $this->addElement('text', 'graphite_api_user', [
            'description' => t('Graphite-API User'),
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

        $this->addElement(
            'submit',
            'submit',
            [
                'label' => $this->translate('Save Changes')
            ]
        );
    }
}
