<?php

namespace SV\ProxyLinkForum\XF\Permission;

use SV\ProxyLinkForum\XF\Entity\Node as ExtendedNodeEntity;

/**
 * @extends \XF\Permission\NodePermissions
 */
class NodePermissions extends XFCP_NodePermissions
{
    /**
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getContentTree()
    {
        return parent::getContentTree()->filter(function (int $id, ExtendedNodeEntity $node) {
            return !$node->isProxiedNode();
        });
    }
}