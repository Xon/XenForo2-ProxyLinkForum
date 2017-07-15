<?php

namespace SV\ProxyLinkForum;

use XF\Mvc\Entity\Entity;

class Listener
{
	public static function linkProxyEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
	{
		$structure->columns['sv_proxy_node_id'] = [
			'type' => Entity::UINT,
			'nullable' => true,
			'default' => null
		];

		$structure->relations['ProxiedForum'] = [
			'entity' => 'XF:Forum',
			'type' => Entity::TO_ONE,
			'conditions' => [['node_id', '=', '$sv_proxy_node_id']],
			'defaultWith' => 'node',
			'primary' => true
		];

		$structure->defaultWith[] = 'ProxiedForum';
	}

	public static function linkProxyEntityPreSave(\XF\Mvc\Entity\Entity $entity)
	{
		if ($entity->sv_proxy_node_id && !$entity->link_url)
		{
			$entity->link_url = $entity->app()->router('public')->buildLink('canonical:forums', $entity->ProxiedForum);
		}
	}
}