<?php

namespace SV\ProxyLinkForum\XF\Entity;

use XF\Entity\AbstractNode;

/**
 * @extends \XF\Entity\Node
 */
class Node extends XFCP_Node
{
    public function isProxiedNode(): bool
    {
        if ($this->node_type_id === 'LinkForum')
        {
            /** @var ?LinkForum $linkedForum */
            $linkedForum = $this->Data;
            if ($linkedForum !== null && $linkedForum->sv_proxy_node_id !== null)
            {
                return true;
            }
        }

        return false;
    }

    public function setData(?AbstractNode $data): void
    {
        $this->_getterCache['Data'] = $data;
    }
}