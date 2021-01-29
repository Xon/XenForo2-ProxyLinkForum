<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\ProxyLinkForum\XF\Repository;

use SV\ProxyLinkForum\XF\Entity\LinkForum as ExtendedLinkForumEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Entity\Node as NodeEntity;

/**
 * @since 2.1.5
 */
class Node extends XFCP_Node
{
    /** @var bool */
    public $shimmedProxyNodes = false;

    protected $injectProxiedSubNodesForSvProxyLinkForum = [];

    public function setInjectProxiedSubNodesForSvProxyLinkForum(string $listMethodName)
    {
        $this->injectProxiedSubNodesForSvProxyLinkForum[$listMethodName] = true;
    }

    public function clearInjectProxiedSubNodesForSvProxyLinkForum(string $listMethodName)
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

    public function injectProxiedSubNodesForSvProxyLinkForum(AbstractCollection $nodes): AbstractCollection
    {
        $shimmedNodes = [];
        $em = \XF::em();

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
                $realForum = $linkForum->ProxiedForum;
                if (!$realForum || !$realForum->canView())
                {
                    // don't bother including non-proxy link forum nodes
                    continue;
                }
                $realForumId = $linkForum->sv_proxy_node_id;
                $shimmedNodes[$nodeId] = $node;
                // generate fake nodes for any child-nodes of the target
                $realNode = $realForum->Node;
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
                        $this->shimmedProxyNodes = true;
                        $fakeNodeId = '_' . $realForumId;
                        // create a fake node
                        /** @var NodeEntity $fakeNode */
                        $fakeNode = $em->create('XF:Node');
                        $fakeNode->setTrusted('node_id', $fakeNodeId);
                        $fakeNode->setTrusted('parent_node_id', $nodeId);
                        $fakeNode->setTrusted('node_type_id', 'Forum');
                        $fakeNode->setTrusted('display_in_list', $childNode->display_in_list);
                        if ($childNode->isValidColumn('sv_aggregate_default'))
                        {
                            $fakeNode->setTrusted('sv_aggregate_allowed', $childNode->sv_aggregate_allowed);
                            $fakeNode->setTrusted('sv_aggregate_default', $childNode->sv_aggregate_default);
                        }
                        $fakeNode->hydrateRelation('Parent', $node);

                        $shimmedNodes[$fakeNodeId] = $fakeNode;
                    }
                }

                continue;
            }

            $shimmedNodes[$nodeId] = $node;
        }

        return $em->getBasicCollection($shimmedNodes);
    }

    /**
     * @var
     */
    protected $fullNodeTreeExtras;

    /**
     * @param $node
     * @param $extras
     * @return mixed
     */
    protected function getSvPLFProxiedNodeData($node, $extras) {
        $nodeId = $node->node_id;

        if(isset($extras[$nodeId])) {
            return $extras[$nodeId];
        }

        if(!$this->fullNodeTreeExtras) {
            $this->fullNodeTreeExtras = $this->getNodeListExtras($this->createNodeTree($this->getNodeList(), 0));
        }

        return $this->fullNodeTreeExtras[$nodeId];
    }

    /**
     * @param \XF\Tree $nodeTree
     * @return array
     */
    public function getNodeListExtras(\XF\Tree $nodeTree)
    {
        $finalOutput = parent::getNodeListExtras($nodeTree);

        $f = function(\XF\Entity\Node $node, array $children) use (&$f, &$finalOutput)
        {
            foreach ($children AS $id => $child)
            {
                /** @var \XF\SubTree $child */
                $childOutput[$id] = $f($child->record, $child->children());
            }

            $extras = $node->getNodeListExtras();
            $proxy = $extras['ProxiedNode'] ?? null;
            if($proxy) {
                $finalOutput[$node->node_id] = $this->mergeNodeListExtras($finalOutput[$node->node_id], [$this->getSvPLFProxiedNodeData($proxy->Node, $finalOutput)]);
            }
        };

        foreach ($nodeTree AS $id => $subTree)
        {
            $f($subTree->record, $subTree->children());
        }

        return $finalOutput;
    }
}
