<?php

namespace SV\ProxyLinkForum;

use SV\ProxyLinkForum\XF\Entity\LinkForum;
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

    public function installStep1(): void
    {
        $this->schemaManager()->alterTable('xf_link_forum', function (Alter $table) {
            $this->addOrChangeColumn($table, 'sv_proxy_search', 'tinyint')->setDefault(1);
            $this->addOrChangeColumn($table, 'sv_proxy_node_id', 'int')->nullable()->setDefault(null);
        });
    }

    public function upgrade2000501Step1(): void
    {
        $this->installStep1();
    }

    public function upgrade2020200Step1(): void
    {
        $this->db()->query('
            UPDATE xf_link_forum 
            SET sv_proxy_node_id = NULL 
            WHERE sv_proxy_node_id = 0
        ');
    }

    public function upgrade2030000Step1(): void
    {
        $this->installStep1();
    }

    public function upgrade2040000Step1(): void
    {
        /** @var LinkForum[] $linkForums */
        $linkForums = \XF::app()->finder('XF:LinkForum')
                         ->where('sv_proxy_node_id', '<>', null)
                         ->fetch();
        $db = $this->db();
        $db->beginTransaction();
        foreach ($linkForums as $linkForum)
        {
            $linkForum->purgePermissions();
        }
        $db->commit();
    }

    public function uninstallStep1(): void
    {
        $this->schemaManager()->alterTable('xf_link_forum', function (Alter $table): void {
            $table->dropColumns([
                'sv_proxy_node_id',
                'sv_proxy_search',
            ]);
        });
    }
}
