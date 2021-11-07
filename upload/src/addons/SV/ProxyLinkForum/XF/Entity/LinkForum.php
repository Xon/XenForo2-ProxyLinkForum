<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ProxyLinkForum\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null sv_proxy_node_id
 *
 * GETTERS
 * @property \XF\Entity\Node ProxiedNode
 * @property \XF\Entity\Forum ProxiedForum
 * @property \XF\Entity\Category ProxiedCategory
 *
 * RELATIONS
 * @property \XF\Entity\Node ProxiedNode_
 * @property \XF\Entity\Forum ProxiedForum_
 * @property \XF\Entity\Category ProxiedCategory_
 */
class LinkForum extends XFCP_LinkForum
{
    /**
     * @return array|bool[]
     */
    public function getNodeListExtras()
    {
        $proxiedForum = $this->ProxiedForum;
        if ($proxiedForum && $proxiedForum->canView())
        {
            $output = $proxiedForum->getNodeListExtras() ?: [];
            $output['ProxiedNode'] = $proxiedForum;
            return $output;
        }

        $proxiedCategory = $this->ProxiedCategory;
        if ($proxiedCategory && $proxiedCategory->canView())
        {
            $output = $proxiedCategory->getNodeListExtras() ?: [];
            $output['ProxiedNode'] = $proxiedCategory;
            return $output;
        }

        return parent::getNodeListExtras();
    }

    /**
     * @return string[]
     */
    public static function getListedWith()
    {
        $visitor = \XF::visitor();
        $userId = $visitor->user_id;
        $with = ['ProxiedForum.LastPostUser', 'ProxiedForum.LastThread', 'ProxiedForum', 'ProxiedForum.Node', 'ProxiedCategory', 'ProxiedCategory.Node'];

        if ($userId)
        {
            $with[] = 'ProxiedForum.Read|' . $userId;
            $with[] = 'ProxiedForum.LastThread.Read|' . $userId;
        }

        return $with;
    }

    /**
     * @param int $depth
     * @return string[]
     */
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
     */
    protected function getProxiedCategory()
    {
        if (!$this->sv_proxy_node_id)
        {
            return null;
        }

        return $this->ProxiedCategory_;
    }

    /**
     * @return \XF\Entity\Node|null
     */
    protected function getProxiedNode()
    {
        if (!$this->sv_proxy_node_id)
        {
            return null;
        }

        return $this->ProxiedNode_;
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

    /**
     * @param Structure $structure
     * @return Structure
     */
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
        $structure->relations['ProxiedNode'] = [
            'entity'      => 'XF:Node',
            'type'        => Entity::TO_ONE,
            'conditions'  => [['node_id', '=', '$sv_proxy_node_id']],
            'primary'     => true
        ];

        $structure->getters['ProxiedForum'] = ['getter' => 'getProxiedForum', 'cache' => false];
        $structure->getters['ProxiedCategory'] = ['getter' => 'getProxiedCategory', 'cache' => false];
        $structure->getters['ProxiedNode'] = ['getter' => 'getProxiedNode', 'cache' => false];

        return $structure;
    }
}
