<?php

namespace SV\ProxyLinkForum\XF\ControllerPlugin;



use SV\ProxyLinkForum\XF\Entity\LinkForum;
use XF\Mvc\ParameterBag;

/**
 * Extends \XF\ControllerPlugin\NodePermission
 */
class NodePermission extends XFCP_NodePermission
{
    public function actionList(ParameterBag $params)
    {
        /** @var \SV\ProxyLinkForum\XF\Entity\Node $record */
        $record = $this->assertRecordExists($this->entityIdentifier, $params->{$this->primaryKey});
        if ($record->isProxiedNode())
        {
            /** @var LinkForum $linkProxy */
            $linkProxy = $record->Data;
            return $this->redirect($this->buildLink($this->routePrefix, $linkProxy->ProxiedNode));
        }

        return parent::actionList($params);
    }

    public function actionEdit(ParameterBag $params, $type = null)
    {
        /** @var \SV\ProxyLinkForum\XF\Entity\Node $record */
        $record = $this->assertRecordExists($this->entityIdentifier, $params->{$this->primaryKey});
        if ($record->isProxiedNode())
        {
            return $this->noPermission();
        }

        return parent::actionEdit($params, $type);
    }

    public function actionSave(ParameterBag $params, $type = null)
    {
        /** @var \SV\ProxyLinkForum\XF\Entity\Node $record */
        $record = $this->assertRecordExists($this->entityIdentifier, $params->{$this->primaryKey});
        if ($record->isProxiedNode())
        {
            return $this->noPermission();
        }

        return parent::actionSave($params, $type);
    }
}