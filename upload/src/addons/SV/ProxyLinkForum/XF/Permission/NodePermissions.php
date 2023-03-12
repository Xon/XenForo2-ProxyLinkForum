<?php

namespace SV\ProxyLinkForum\XF\Permission;

/**
 * Extends \XF\Permission\NodePermissions
 */
class NodePermissions extends XFCP_NodePermissions
{
    /**
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getContentTree()
    {
        return parent::getContentTree()->filter(function (int $id, \SV\ProxyLinkForum\XF\Entity\Node $node) {
            return !$node->isProxiedNode();
        });
    }
}