<?php

namespace SV\ProxyLinkForum\XF\Admin\Controller;

use XF\Mvc\FormAction;

/**
 * Class LinkForum
 * @package SV\ProxyLinkForum\XF\Admin\Controller
 */
class LinkForum extends XFCP_LinkForum
{
    /**
     * @param FormAction $form
     * @param \XF\Entity\Node $node
     * @param \XF\Entity\AbstractNode $data
     */
    protected function saveTypeData(FormAction $form, \XF\Entity\Node $node, \XF\Entity\AbstractNode $data)
    {
        parent::saveTypeData($form, $node, $data);

        $proxyNodeId = $this->filter('sv_proxy_node_id', 'uint');
        $data->set('sv_proxy_node_id', $proxyNodeId);
    }
}
