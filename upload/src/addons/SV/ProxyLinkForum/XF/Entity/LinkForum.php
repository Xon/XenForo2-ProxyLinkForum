<?php

namespace SV\ProxyLinkForum\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property \XF\Entity\ForumRead[] Read
 * @property \XF\Entity\Forum ProxiedForum
 * @property int|null sv_proxy_node_id
 */
class LinkForum extends XFCP_LinkForum
{
    public function getNodeListExtras()
    {
        if ($this->sv_proxy_node_id && isset($this->ProxiedForum))
        {
            $ProxiedForum = $this->ProxiedForum;
            $output = $ProxiedForum->getNodeListExtras();
            $output['ProxiedForum'] = $ProxiedForum;
            return $output;
        }

        return parent::getNodeListExtras();
    }

    public static function getListedWith()
    {
        $visitor = \XF::visitor();
        $with = [];
        $with[] = 'ProxiedForum';
        $with[] = 'ProxiedForum.Node';

        if ($visitor->user_id)
        {
            $with[] = "ProxiedForum.Read|{$visitor->user_id}";
        }

        return $with;
    }

    public function getNodeTemplateRenderer($depth)
    {
        if (!$this->ProxiedForum)
        {
            return parent::getNodeTemplateRenderer($depth);
        }

        return [
            'template' => 'node_list_forum',
            'macro'    => $depth <= 2 ? 'depth' . $depth : 'depthN'
        ];
    }

    public function _preSave()
    {
        if ($this->sv_proxy_node_id && !$this->link_url)
        {
            $this->link_url = $this->app()->router('public')->buildLink('canonical:forums', $this->ProxiedForum);
        }
        parent::_preSave();
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_proxy_node_id'] = [
            'type'     => Entity::UINT,
            'nullable' => true,
            'default'  => null
        ];

        $structure->relations['ProxiedForum'] = [
            'entity'      => 'XF:Forum',
            'type'        => Entity::TO_ONE,
            'conditions'  => [['node_id', '=', '$sv_proxy_node_id']],
            'defaultWith' => 'node',
            'primary'     => true
        ];

        return $structure;
    }
}
