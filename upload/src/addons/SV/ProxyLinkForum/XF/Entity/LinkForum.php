<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ProxyLinkForum\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $sv_proxy_node_id
 * @property bool $sv_proxy_search
 *
 * GETTERS
 * @property-read \XF\Entity\Node $ProxiedNode
 * @property-read \XF\Entity\Forum $ProxiedForum
 * @property-read \XF\Entity\Category $ProxiedCategory
 *
 * RELATIONS
 * @property-read \XF\Entity\Node $ProxiedNode_
 * @property-read \XF\Entity\Forum $ProxiedForum_
 * @property-read \XF\Entity\Category $ProxiedCategory_
 */
class LinkForum extends XFCP_LinkForum
{
    protected function addChildExtras(array $output,\XF\Entity\Node $node)
    {
        // has children, fetch stats for them
        if ($node->lft >= $node->rgt)
        {
            return $output;
        }

        /** @var \SV\ProxyLinkForum\XF\Repository\Node $repo */
        $repo = $this->repository('XF:Node');
        $tree = $repo->svLinkForumsNodeTree ?? null;
        if ($tree === null)
        {
            return $output;
        }

        // avoid recursion
        $nodeId = $node->node_id;
        if (isset($repo->svLinkForumNodesSeen[$nodeId]))
        {
            return $output;
        }
        $repo->svLinkForumNodesSeen[$nodeId] = true;


        $f = function(\XF\Entity\Node $node, array $children) use ($repo, &$f, &$finalOutput)
        {
            $childOutput = [];
            foreach ($children AS $id => $child)
            {
                /** @var \XF\SubTree $child */
                $childOutput[$id] = $f($child->record, $child->children());
            }

            return $repo->mergeNodeListExtras($node->getNodeListExtras(), $childOutput);
        };

        $childOutput = [$f($node, $tree->children($nodeId))];
        $output = $repo->mergeNodeListExtras($output, $childOutput);

        return $output;
    }

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
            return $this->addChildExtras($output, $proxiedForum->Node);
        }

        $proxiedCategory = $this->ProxiedCategory;
        if ($proxiedCategory && $proxiedCategory->canView())
        {
            $output = $proxiedCategory->getNodeListExtras() ?: [];
            $output['ProxiedNode'] = $proxiedCategory;
            return $this->addChildExtras($output, $proxiedCategory->Node);
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
        if ($this->sv_proxy_node_id === null)
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
        if ($this->sv_proxy_node_id === null)
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
        if ($this->sv_proxy_node_id === null)
        {
            return null;
        }

        return $this->ProxiedNode_;
    }

    protected function _preSave()
    {
        if ($this->sv_proxy_node_id === 0)
        {
            $this->sv_proxy_node_id = null;
        }

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
        $structure->columns['sv_proxy_search'] = ['type' => Entity::BOOL, 'default' => true];

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
