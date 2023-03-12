<?php

namespace SV\ProxyLinkForum\XF\Entity;

use XF\Entity\AbstractNode;

/**
 * Extends \XF\Entity\Node
 */
class Node extends XFCP_Node
{
    public function setData(?AbstractNode $data): void
    {
        $this->_getterCache['Data'] = $data;
    }
}