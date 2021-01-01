<?php

namespace SV\ProxyLinkForum\XF\Search\Data;

use SV\ProxyLinkForum\XF\Entity\LinkForum as ExtendedLinkForumEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Search\Query\MetadataConstraint;

/**
 * Extends \XF\Search\Data\Post
 */
class Post extends XFCP_Post
{
    /**
     * @var bool
     */
    protected $armSearchNodeHacks = false;

    /** @var bool */
    protected $shimmedProxyNodes = false;

    protected function injectProxiedSubNodes(AbstractCollection $nodes): AbstractCollection
    {
        $shimmedNodes = [];
        $em = \XF::em();

        $nodes = $nodes->toArray();
        $nodes = \array_values($nodes);
        $nodeCount = count($nodes);

        /** @var \XF\Entity\Node[] $nodes */
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
                        /** @var \XF\Entity\Node $fakeNode */
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
     * @return \XF\Tree
     *
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function getSearchableNodeTree()
    {
        if (!$this->armSearchNodeHacks)
        {
            return parent::getSearchableNodeTree();
        }

        $structure = \XF::em()->getEntityStructure('XF:LinkForum');
        $hasProxyForum = $structure->relations['ProxiedForum'] ?? false;
        if (!$hasProxyForum)
        {
            return parent::getSearchableNodeTree();
        }

        /** @var \XF\Repository\Node $nodeRepo */
        $nodeRepo = \XF::repository('XF:Node');
        $nodes = $this->injectProxiedSubNodes($nodeRepo->getNodeList());
        $nodeTree = $nodeRepo->createNodeTree($nodes);

        // only list nodes that are forums or contain forums
        $nodeTree = $nodeTree->filter(null, function ($id, $node, $depth, $children, $tree) {
            /** @var \XF\Entity\Node $node */
            return ($children || $node->node_type_id === 'Forum');
        });

        return $nodeTree;
    }

    protected function rewriteProxiedNodes(MetadataConstraint $constraint): bool
    {
        $em = \XF::em();
        $change = false;
        $shimmedNodeIds = [];
        $nodeIds = $constraint->getValues();
        foreach ($nodeIds as $nodeId)
        {
            /** @var \XF\Entity\Node $node */
            $node = $em->findCached('XF:Node', $nodeId);
            if (!$node)
            {
                if (is_string($nodeId) && $nodeId[0] === '_')
                {
                    // patch nodes with '_' ids, these are pseudo-forums under the existing parent
                    // proxied forums are automatically children of the search node roots, so they should always be included
                    $change = true;
                    $nodeId = (int)\substr($nodeId, 1);
                }
                $shimmedNodeIds[$nodeId] = $nodeId;
            }
            else if ($node->node_type_id === 'LinkForum')
            {
                // A link-proxy which is not a proxy forum, skip
                $change = true;
            }
            else
            {
                $shimmedNodeIds[$nodeId] = $nodeId;
            }
        }

        if ($change)
        {
            // ensure this is an json integer list, and not a json object of string values
            $shimmedNodeIds = \array_values($shimmedNodeIds);
            $constraint->setValues($shimmedNodeIds);
        }

        return $change;
    }

    public function applyTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array &$urlConstraints)
    {
        $this->shimmedProxyNodes = false;
        $searchNodeRoots = $this->searchNodeRoots ?? $request->filter('c.nodes', 'array-uint');
        $this->armSearchNodeHacks = !$request->filter('c.thread', 'uint') &&
                                    ($searchNodeRoots && reset($searchNodeRoots)) &&
                                    $request->filter('c.child_nodes', 'bool');
        try
        {
            parent::applyTypeConstraintsFromInput($query, $request, $urlConstraints);

            if ($this->armSearchNodeHacks && $this->shimmedProxyNodes)
            {
                $constraints = $query->getMetadataConstraints();
                foreach ($constraints as $constraint)
                {
                    if ($constraint->getKey() === 'node')
                    {
                        $this->rewriteProxiedNodes($constraint);
                    }
                }
            }
        }
        finally
        {
            $this->shimmedProxyNodes = false;
            $this->armSearchNodeHacks = false;
        }
    }
}