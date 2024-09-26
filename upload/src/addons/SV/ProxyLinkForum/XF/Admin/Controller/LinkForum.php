<?php

namespace SV\ProxyLinkForum\XF\Admin\Controller;

use XF\Entity\AbstractNode as AbstractNodeEntity;
use XF\Entity\Node as NodeEntity;
use XF\Mvc\FormAction;

/**
 * @extends \XF\Admin\Controller\LinkForum
 */
class LinkForum extends XFCP_LinkForum
{
    /**
     * @param FormAction         $form
     * @param NodeEntity         $node
     * @param AbstractNodeEntity $data
     */
    protected function saveTypeData(FormAction $form, NodeEntity $node, AbstractNodeEntity $data)
    {
        parent::saveTypeData($form, $node, $data);

        assert($data instanceof \SV\ProxyLinkForum\XF\Entity\LinkForum);
        $data->sv_proxy_node_id = (int)$this->filter('sv_proxy_node_id', 'uint');
        $data->sv_proxy_search = (bool)$this->filter('sv_proxy_search', 'bool');
    }
}
