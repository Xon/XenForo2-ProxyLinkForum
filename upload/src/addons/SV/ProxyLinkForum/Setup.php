<?php

namespace SV\ProxyLinkForum;

use SV\StandardLib\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;

class Setup extends AbstractSetup
{
    use InstallerHelper;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $this->schemaManager()->alterTable('xf_link_forum', function (Alter $table) {
            $this->addOrChangeColumn($table, 'sv_proxy_node_id', 'int')->nullable()->setDefault(null);
        });
    }

    public function upgrade2000501Step1()
    {
        $this->installStep1();
    }

    public function uninstallStep1()
    {
        $this->schemaManager()->alterTable('xf_link_forum', function (Alter $table) {
            $table->dropColumns(['sv_proxy_node_id']);
        });
    }
}
