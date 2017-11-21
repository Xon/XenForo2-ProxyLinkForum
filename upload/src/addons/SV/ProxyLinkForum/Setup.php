<?php

namespace SV\ProxyLinkForum;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1()
	{
		$this->schemaManager()->alterTable('xf_link_forum', function (Alter $table)
		{
			$table->addColumn('sv_proxy_node_id', 'int')->nullable();
		});
	}

    public function uninstallStep1()
    {
        $this->schemaManager()->alterTable('xf_link_forum', function (Alter $table)
        {
            $table->dropColumns(['sv_proxy_node_id']);
        });
    }
}
