<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ProxyLinkForum\XF\Repository;

use SV\ProxyLinkForum\XF\Entity\LinkForum as ExtendedLinkForumEntity;
use XF\Entity\AbstractNode;
use XF\Mvc\Entity\AbstractCollection;
use XF\Entity\Node as NodeEntity;
use SV\ProxyLinkForum\XF\Entity\Node as ExtendedNodeEntity;
use function assert;
use function count;

/**
 * @since 2.1.5
 */
class Node extends XFCP_Node
{
    /** @var bool */
    public $shimmedProxyNodes = false;

    /** @var array<string,bool> */
    protected $injectProxiedSubNodesForSvProxyLinkForum = [];

    public function setInjectProxiedSubNodesForSvProxyLinkForum(string $listMethodName): void
    {
        $this->injectProxiedSubNodesForSvProxyLinkForum[$listMethodName] = true;
    }

    public function clearInjectProxiedSubNodesForSvProxyLinkForum(string $listMethodName): void
    {
        unset($this->injectProxiedSubNodesForSvProxyLinkForum[$listMethodName]);
    }

    public function isInjectingProxiedSubNodesForSvProxyLinkForum(string $listMethodName): bool
    {
        return $this->injectProxiedSubNodesForSvProxyLinkForum[$listMethodName] ?? false;
    }

    /**
     * @param NodeEntity|null $withinNode
     * @return AbstractCollection
     */
    public function getNodeList(NodeEntity $withinNode = null)
    {
        $nodeList = parent::getNodeList($withinNode);

        if ($this->isInjectingProxiedSubNodesForSvProxyLinkForum(__FUNCTION__))
        {
            $nodeList = $this->injectProxiedSubNodesForSvProxyLinkForum($nodeList);
        }

        return $nodeList;
    }

    /**
     * @param NodeEntity|null $withinNode
     * @param null            $with
     * @return AbstractCollection
     */
    public function getFullNodeListWithTypeData(NodeEntity $withinNode = null, $with = null)
    {
        $nodeList = parent::getFullNodeListWithTypeData($withinNode, $with);

        if ($this->isInjectingProxiedSubNodesForSvProxyLinkForum(__FUNCTION__))
        {
            $nodeList = $this->injectProxiedSubNodesForSvProxyLinkForum($nodeList);
        }

        return $nodeList;
    }

    /**
     * @param NodeEntity   $node
     * @param AbstractNode $data
     * @param NodeEntity   $parent
     * @return NodeEntity
     */
    protected function cloneAsFake(NodeEntity $node, AbstractNode $data, ?NodeEntity $parent): NodeEntity
    {
        // copy the attributes, except for overrides
        $arr = [
            'node_id'        => '_' . $node->node_id,
            'parent_node_id' => $parent->node_id ?? 0,
            'node_type_id'   => 'Forum',
        ];

        foreach ($node->structure()->columns as $key => $null)
        {
            if (isset($arr[$key]))
            {
                continue;
            }
            $arr[$key] = $node->getValueSourceEncoded($key);
        }
        // create the fake node & setup relationships
        $fakeNode = $this->app()->em()->instantiateEntity('XF:Node', $arr);
        assert($fakeNode instanceof ExtendedNodeEntity);
        $fakeNode->hydrateRelation('Parent', $parent);
        $fakeNode->setData($data);
        $fakeNode->setReadOnly(true);

        return $fakeNode;
    }

    public function injectProxiedSubNodesForSvProxyLinkForum(AbstractCollection $nodes): AbstractCollection
    {
        $shimmedNodes = [];
        $nodes = $nodes->toArray();
        $nodes = \array_values($nodes);
        $nodeCount = count($nodes);

        /** @var NodeEntity[] $nodes */
        foreach ($nodes as $node)
        {
            $nodeId = $node->node_id;
            if ($node->node_type_id === 'LinkForum')
            {
                /** @var ExtendedLinkForumEntity $linkForum */
                $linkForum = $node->Data;
                if ($realForum = $linkForum->ProxiedForum)
                {
                    if (!$realForum->canView())
                    {
                        continue;
                    }
                    $realNode = $realForum->Node;
                    $realData = $realForum;
                }
                else if ($realCategory = $linkForum->ProxiedCategory)
                {
                    if (!$realCategory->canView())
                    {
                        continue;
                    }
                    $realNode = $realCategory->Node;
                    $realData = $realCategory;
                }
                else
                {
                    // don't bother including non-proxy link forum nodes
                    continue;
                }

                $this->shimmedProxyNodes = true;
                $linkedForum = $this->cloneAsFake($realNode, $realData, $node->Parent);
                $shimmedNodes[$linkedForum->node_id] = $linkedForum;

                // generate fake nodes for any child-nodes of the target
                $lft = $realNode->lft;
                $rgt = $realNode->rgt;
                for ($i = 0; $i < $nodeCount; $i++)
                {
                    $childNode = $nodes[$i];
                    // todo nested link proxy forum handling?
                    if ($childNode->lft >= $lft &&
                        $childNode->rgt <= $rgt &&
                        $childNode->node_type_id === 'Forum')
                    {
                        $fakeNode = $this->cloneAsFake($childNode, $childNode->Data, $linkedForum);

                        $shimmedNodes[$fakeNode->node_id] = $fakeNode;
                    }
                }

                continue;
            }

            $shimmedNodes[$nodeId] = $node;
        }

        return $this->app()->em()->getBasicCollection($shimmedNodes);
    }
}
