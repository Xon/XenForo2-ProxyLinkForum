<?php

namespace SV\ProxyLinkForum\XF\Search\Data;

use SV\ProxyLinkForum\XF\Entity\LinkForum as ExtendedLinkForumEntity;
use XF\Mvc\Entity\AbstractCollection;

/**
 * Extends \XF\Search\Data\Post
 */
class Post extends XFCP_Post
{
    /**
     * @var bool
     */
    protected $armSearchNodeHacks = false;

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
                $realForumId = $linkForum->sv_proxy_node_id;
                $realForum = $realForumId ? $linkForum->ProxiedForum : null;
                if (!$realForum)
                {
                    // don't bother including non-proxy link forum nodes
                    continue;
                }
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
                        $fakeNodeId = '_' . $realForumId;
                        // create a fake node
                        /** @var \XF\Entity\Node $node */
                        $fakeNode = $em->create('XF:Node');
                        $fakeNode->setTrusted('node_id', $fakeNodeId);
                        $fakeNode->setTrusted('parent_node_id', $nodeId);
                        $fakeNode->setTrusted('node_type_id', 'Forum');

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

    public function applyTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array &$urlConstraints)
    {
        $this->armSearchNodeHacks = !$request->filter('c.thread', 'uint') &&
                                    $request->filter('c.nodes', 'array-uint') &&
                                    $request->filter('c.child_nodes', 'bool');
        try
        {
            parent::applyTypeConstraintsFromInput($query, $request, $urlConstraints);

            if ($this->armSearchNodeHacks)
            {
                $constraints = $query->getMetadataConstraints();
                foreach ($constraints as $constraint)
                {
                    if ($constraint->getKey() === 'node')
                    {
                        $change = false;
                        $shimmedNodeIds = [];
                        $nodeIds = $constraint->getValues();
                        foreach ($nodeIds as $nodeId)
                        {
                            // patch nodes with '_' ids
                            if (is_string($nodeId) && $nodeId[0] === '_')
                            {
                                $change = true;
                                $nodeId = (int)\substr($nodeId, 1);
                            }

                            $shimmedNodeIds[$nodeId] = $nodeId;
                        }

                        if ($change)
                        {
                            // ensure this is an json integer list, and not a json object of string values
                            $constraint->setValues(\array_values($shimmedNodeIds));
                        }
                    }
                }
            }
        }
        finally
        {
            $this->armSearchNodeHacks = false;
        }
    }
}