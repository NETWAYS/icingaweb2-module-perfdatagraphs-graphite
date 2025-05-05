<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Controllers;

use Icinga\Module\Perfdatagraphsgraphite\Forms\PerfdataGraphsGraphiteConfigForm;

use Icinga\Application\Config;
use Icinga\Web\Widget\Tabs;

use ipl\Html\HtmlString;
use ipl\Web\Compat\CompatController;

/**
 * ConfigController manages the configuration for the PerfdataGraphs Graphite Module.
 */
class ConfigController extends CompatController
{
    protected bool $disableDefaultAutoRefresh = true;

    /**
     * Initialize the Controller.
     */
    public function init(): void
    {
        // Assert the user has access to this controller.
        $this->assertPermission('config/modules');
        parent::init();
    }

    /**
     * generalAction provides the configuration form.
     * For now we have everything on a single Tab, might be extended in the future.
     */
    public function generalAction(): void
    {
        $config = Config::module('perfdatagraphsgraphite');

        $c = [
            'graphite_api_url' => $config->get('graphite', 'api_url', 'http://localhost:8081'),
            'graphite_api_timeout' => (int) $config->get('graphite', 'api_timeout', 10),
            'graphite_api_user' => $config->get('graphite', 'api_user'),
            'graphite_api_password' => $config->get('graphite', 'api_token'),
            'graphite_api_tls_insecure' => (bool) $config->get('graphite', 'api_tls_insecure', false),
            'graphite_writer_host_name_template' => $config->get('graphite', 'writer_host_name_template', 'icinga2.$host.name$.host.$host.check_command$'),
            'graphite_writer_service_name_template' => $config->get('graphite', 'writer_service_name_template', 'icinga2.$host.name$.services.$service.name$.$service.check_command$'),
        ];

        $form = (new PerfdataGraphsGraphiteConfigForm())
            ->populate($c)
            ->setIniConfig($config);
        $form->handleRequest();

        $this->mergeTabs($this->Module()->getConfigTabs()->activate('general'));

        $this->addContent(new HtmlString($form->render()));
    }

    /**
     * Merge tabs with other tabs contained in this tab panel.
     *
     * @param Tabs $tabs
     */
    protected function mergeTabs(Tabs $tabs): self
    {
        foreach ($tabs->getTabs() as $tab) {
            $this->tabs->add($tab->getName(), $tab);
        }

        return $this;
    }
}
