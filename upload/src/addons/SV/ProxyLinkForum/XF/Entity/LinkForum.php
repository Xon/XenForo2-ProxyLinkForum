<?php

namespace SV\ProxyLinkForum\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property \XF\Entity\Forum ProxiedForum
 * @property \XF\Entity\Forum ProxiedForum_
 * @property int|null sv_proxy_node_id
 */
class LinkForum extends XFCP_LinkForum
{
    /** @noinspection PhpMissingReturnTypeInspection */
    public function getNodeListExtras()
    {
        $proxiedForum = $this->ProxiedForum;
        if ($proxiedForum)
        {
            $output = $proxiedForum->getNodeListExtras();
            $output = $output ?: [];
            $output['ProxiedForum'] = $proxiedForum;

            return $output;
        }

        return parent::getNodeListExtras();
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public static function getListedWith()
    {
        $visitor = \XF::visitor();
        $userId = $visitor->user_id;
        $isXF21 = \XF::$versionId > 2010070;
        $with = $isXF21 ? ['ProxiedForum', 'ProxiedForum.Node', 'ProxiedForum.LastPostUser', 'ProxiedForum.LastThread'] : ['ProxiedForum', 'ProxiedForum.Node'];

        if ($userId)
        {
            $with[] = "ProxiedForum.Read|{$userId}";
            if ($isXF21)
            {
                $with[] = "ProxiedForum.LastThread.Read|{$userId}";
            }
        }

        return $with;
    }

    /** @noinspection PhpMissingReturnTypeInspection */
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


    /**
     * @return \XF\Entity\Forum|null
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function getProxiedForum()
    {
        if (!$this->sv_proxy_node_id)
        {
            return null;
        }

        return $this->ProxiedForum_;
    }

    protected function _preSave()
    {
        $proxiedForum = $this->ProxiedForum;

        if ($proxiedForum && !$this->link_url)
        {
            $this->link_url = $this->app()->router('public')->buildLink('canonical:forums', $proxiedForum);
        }

        parent::_preSave();
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['sv_proxy_node_id'] = ['type' => Entity::UINT, 'nullable' => true, 'default' => null];

        $structure->relations['ProxiedForum'] = [
            'entity'      => 'XF:Forum',
            'type'        => Entity::TO_ONE,
            'conditions'  => [['node_id', '=', '$sv_proxy_node_id']],
            'defaultWith' => 'node',
            'primary'     => true
        ];
        $structure->getters['ProxiedForum'] = ['getter' => 'getProxiedForum', 'cache' => false];

        return $structure;
    }
}
