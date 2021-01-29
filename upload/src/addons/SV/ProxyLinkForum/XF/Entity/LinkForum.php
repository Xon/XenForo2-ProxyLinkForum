<?php

namespace SV\ProxyLinkForum\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property \XF\Entity\Forum ProxiedForum
 * @property \XF\Entity\Forum ProxiedForum_
 * @property \XF\Entity\Category ProxiedCategory
 * @property \XF\Entity\Category ProxiedCategory_
 * @property int|null sv_proxy_node_id
 */
class LinkForum extends XFCP_LinkForum
{
    /** @noinspection PhpMissingReturnTypeInspection */
    public function getNodeListExtras()
    {
        $proxiedForum = $this->ProxiedForum;
        $output = [];
        if ($proxiedForum && $proxiedForum->canView())
        {
            $output['ProxiedNode'] = $proxiedForum;
            return $output;
        }

        $proxiedCategory = $this->ProxiedCategory;
        if ($proxiedCategory && $proxiedCategory->canView())
        {
            $output['ProxiedNode'] = $proxiedCategory;
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
        $with = $isXF21 ? ['ProxiedForum.LastPostUser', 'ProxiedForum.LastThread'] : [];
        $with = array_merge($with, ['ProxiedForum', 'ProxiedForum.Node', 'ProxiedCategory', 'ProxiedCategory.Node']);

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
        $proxiedForum = $this->ProxiedForum;
        if ($proxiedForum && $proxiedForum->canView()) {
            return [
                'template' => 'node_list_forum',
                'macro'    => $depth <= 2 ? 'depth' . $depth : 'depthN'
            ];
        }

        $proxiedCategory = $this->ProxiedCategory;
        if ($proxiedCategory && $proxiedCategory->canView()) {
            return [
                'template' => 'node_list_category',
                'macro'    => $depth <= 2 ? 'depth' . $depth : 'depthN'
            ];
        }

        return parent::getNodeTemplateRenderer($depth);
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

    /**
     * @return \XF\Entity\Category|null
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function getProxiedCategory()
    {
        if (!$this->sv_proxy_node_id)
        {
            return null;
        }

        return $this->ProxiedCategory_;
    }

    protected function _preSave()
    {
        $proxiedForum = $this->ProxiedForum;
        if ($proxiedForum && !$this->link_url)
        {
            $this->link_url = $this->app()->router('public')->buildLink('canonical:forums', $proxiedForum);
        }


        $proxiedCategory = $this->ProxiedCategory;
        if ($proxiedCategory && !$this->link_url)
        {
            $this->link_url = $this->app()->router('public')->buildLink('canonical:categories', $proxiedCategory);
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
        $structure->relations['ProxiedCategory'] = [
            'entity'      => 'XF:Category',
            'type'        => Entity::TO_ONE,
            'conditions'  => [['node_id', '=', '$sv_proxy_node_id']],
            'defaultWith' => 'node',
            'primary'     => true
        ];

        $structure->getters['ProxiedForum'] = ['getter' => 'getProxiedForum', 'cache' => false];
        $structure->getters['ProxiedCategory'] = ['getter' => 'getProxiedCategory', 'cache' => false];

        return $structure;
    }
}
