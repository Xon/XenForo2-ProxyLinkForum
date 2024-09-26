<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ProxyLinkForum\XF\Entity;

use SV\ProxyLinkForum\XF\Repository\Node as ExtendedNodeRepo;
use SV\StandardLib\Helper;
use XF\Entity\Category as CategoryEntity;
use XF\Entity\Forum as ForumEntity;
use XF\Entity\Node as NodeEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\Node as NodeRepo;
use XF\SubTree;
use function array_merge;

/**
 * @extends \XF\Entity\LinkForum
 *
 * COLUMNS
 *
 * @property int|null            $sv_proxy_node_id
 * @property bool                $sv_proxy_search
 * GETTERS
 * @property-read NodeEntity     $ProxiedNode
 * @property-read ForumEntity    $ProxiedForum
 * @property-read CategoryEntity $ProxiedCategory
 * RELATIONS
 * @property-read NodeEntity     $ProxiedNode_
 * @property-read ForumEntity    $ProxiedForum_
 * @property-read CategoryEntity $ProxiedCategory_
 */
class LinkForum extends XFCP_LinkForum
{
    public function canView(&$error = null)
    {
        $proxiedForum = $this->ProxiedForum;
        if ($proxiedForum !== null)
        {
            return $proxiedForum->canView();
        }

        $proxiedCategory = $this->ProxiedCategory;
        if ($proxiedCategory !== null)
        {
            return $proxiedCategory->canView();
        }

        return parent::canView($error);
    }

    /** @noinspection PhpUnnecessaryLocalVariableInspection */
    protected function addChildExtras(array $output, NodeEntity $node)
    {
        // has children, fetch stats for them
        if ($node->lft >= $node->rgt)
        {
            return $output;
        }

        /** @var ExtendedNodeRepo $repo */
        $repo = Helper::repository(NodeRepo::class);
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


        $f = function (NodeEntity $node, array $children) use ($repo, &$f, &$finalOutput) {
            $childOutput = [];
            foreach ($children as $id => $child)
            {
                /** @var SubTree $child */
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
        if ($proxiedForum !== null)
        {
            $output = $proxiedForum->getNodeListExtras() ?: [];
            $output['ProxiedNode'] = $proxiedForum;

            return $this->addChildExtras($output, $proxiedForum->Node);
        }

        $proxiedCategory = $this->ProxiedCategory;
        if ($proxiedCategory !== null)
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
        $with = parent::getListedWith();
        $visitor = \XF::visitor();
        $userId = (int)$visitor->user_id;
        $with = array_merge($with, [
            'ProxiedNode',
            'ProxiedForum.LastPostUser', 'ProxiedForum.LastThread',
            'ProxiedCategory',
        ]);

        if ($userId !== 0)
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
        if ($proxiedForum !== null)
        {
            return [
                'template' => 'node_list_forum',
                'macro'    => $depth <= 2 ? 'depth' . $depth : 'depthN'
            ];
        }

        $proxiedCategory = $this->ProxiedCategory;
        if ($proxiedCategory !== null)
        {
            return [
                'template' => 'node_list_category',
                'macro'    => $depth <= 2 ? 'depth' . $depth : 'depthN'
            ];
        }

        return parent::getNodeTemplateRenderer($depth);
    }

    protected function getProxiedForum(): ?ForumEntity
    {
        if ($this->sv_proxy_node_id === null)
        {
            return null;
        }

        return $this->ProxiedForum_;
    }

    protected function getProxiedCategory(): ?CategoryEntity
    {
        if ($this->sv_proxy_node_id === null)
        {
            return null;
        }

        return $this->ProxiedCategory_;
    }

    protected function getProxiedNode(): ?NodeEntity
    {
        if ($this->sv_proxy_node_id === null)
        {
            return null;
        }

        return $this->ProxiedNode_;
    }

    public function purgePermissions(): void
    {
        $db = \XF::db();

        $db->query('DELETE FROM xf_permission_entry_content WHERE content_type = ? AND content_id = ?', [
            'node',
            $this->node_id,
        ]);
        $db->query('DELETE FROM xf_permission_cache_content WHERE content_type = ? AND content_id = ?', [
            'node',
            $this->node_id,
        ]);
    }

    protected function _preSave()
    {
        if ($this->sv_proxy_node_id === 0)
        {
            $this->sv_proxy_node_id = null;
        }

        $proxiedForum = $this->ProxiedForum;
        if ($proxiedForum !== null && $this->link_url !== '')
        {
            $this->link_url = \XF::app()->router('public')->buildLink('canonical:forums', $proxiedForum);
        }

        $proxiedCategory = $this->ProxiedCategory;
        if ($proxiedCategory !== null && $this->link_url !== '')
        {
            $this->link_url = \XF::app()->router('public')->buildLink('canonical:categories', $proxiedCategory);
        }

        parent::_preSave();
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isChanged('sv_proxy_node_id') && $this->sv_proxy_node_id !== null)
        {
            $this->purgePermissions();
        }
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
            'entity'     => 'XF:Node',
            'type'       => Entity::TO_ONE,
            'conditions' => [['node_id', '=', '$sv_proxy_node_id']],
            'primary'    => true
        ];

        $structure->getters['ProxiedForum'] = ['getter' => 'getProxiedForum', 'cache' => false];
        $structure->getters['ProxiedCategory'] = ['getter' => 'getProxiedCategory', 'cache' => false];
        $structure->getters['ProxiedNode'] = ['getter' => 'getProxiedNode', 'cache' => false];

        return $structure;
    }
}
