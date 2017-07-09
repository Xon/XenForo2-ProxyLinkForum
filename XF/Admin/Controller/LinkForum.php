<?php

namespace SV\ProxyLinkForum\XF\Admin\Controller;

use XF\Mvc\FormAction;

class LinkForum extends XFCP_LinkForum
{
	protected function saveTypeData(FormAction $form, \XF\Entity\Node $node, \XF\Entity\AbstractNode $data)
	{
		parent::saveTypeData($form, $node, $data);

		$proxyNodeId = $this->filter('sv_proxy_node_id', 'uint');
		$data->set('sv_proxy_node_id', $proxyNodeId);
	}
}