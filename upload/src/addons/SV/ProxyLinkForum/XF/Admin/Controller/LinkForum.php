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

        assert($data instanceof \SV\ProxyLinkForum\XF\Entity\LinkForum);
        $data->sv_proxy_node_id = (int)$this->filter('sv_proxy_node_id', 'uint');
        $data->sv_proxy_search = (bool)$this->filter('sv_proxy_search', 'bool');
    }
}
