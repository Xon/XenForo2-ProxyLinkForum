<?php

namespace SV\ProxyLinkForum\XF\Entity;

/**
 * @property \XF\Entity\Forum ProxiedForum
 */
class LinkForum extends XFCP_LinkForum
{
	public function getNodeListExtras()
	{
		if ($this->ProxiedForum)
		{
			return $this->ProxiedForum->getNodeListExtras();
		}

		return parent::getNodeListExtras();
	}

	public function getNodeTemplateRenderer($depth)
	{
		if (!$this->ProxiedForum)
		{
			return parent::getNodeTemplateRenderer($depth);
		}

		return [
			'template' => 'node_list_forum',
			'macro' => $depth <= 2 ? 'depth' . $depth : 'depthN'
		];
	}
}