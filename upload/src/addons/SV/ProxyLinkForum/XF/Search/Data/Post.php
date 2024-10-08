<?php

namespace SV\ProxyLinkForum\XF\Search\Data;

use SV\ProxyLinkForum\XF\Repository\Node as ExtendedNodeRepo;
use SV\StandardLib\Helper;
use XF\Entity\LinkForum as LinkForumEntity;
use XF\Entity\Node as NodeEntity;
use XF\Http\Request;
use XF\Repository\Node as NodeRepo;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;
use XF\Tree;
use function array_values;
use function count;
use function is_callable;
use function is_string;
use function reset;
use function substr;

/**
 * @extends \XF\Search\Data\Post
 */
class Post extends XFCP_Post
{
    /**
     * @var bool
     */
    protected $armSearchNodeHacks = false;

    /**
     * @return array
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getSearchFormData()
    {
        $structure = Helper::getEntityStructure(LinkForumEntity::class);
        $this->armSearchNodeHacks = $structure->relations['ProxiedForum'] ?? false;
        try
        {
            return parent::getSearchFormData();
        }
        finally
        {
            $this->armSearchNodeHacks = false;

        }
    }

    /**
     * @return Tree
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function getSearchableNodeTree()
    {
        /** @var ExtendedNodeRepo $nodeRepo */
        $nodeRepo = Helper::repository(NodeRepo::class);

        if ($this->armSearchNodeHacks)
        {
            $nodeRepo->setInjectProxiedSubNodesForSvProxyLinkForum('getNodeList');
        }

        try
        {
            return parent::getSearchableNodeTree();
        }
        finally
        {
            $nodeRepo->clearInjectProxiedSubNodesForSvProxyLinkForum('getNodeList');
        }
    }

    protected function rewriteQueryProxiedNodes(Query $query, MetadataConstraint $constraint): bool
    {
        $change = false;
        $shimmedNodeIds = [];
        $nodeIds = $constraint->getValues();
        foreach ($nodeIds as $nodeId)
        {
            if (is_string($nodeId) && $nodeId[0] === '_')
            {
                // patch nodes with '_' ids, these are pseudo-forums under the existing parent
                // proxied forums are automatically children of the search node roots, so they should always be included
                $change = true;
                $nodeId = (int)substr($nodeId, 1);
            }

            $node = Helper::findCached(NodeEntity::class, $nodeId);
            if ($node === null || $node->node_type_id === 'LinkForum')
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
            // no nodes, but there is a constraint, use an impossible node id to force this query to fail
            if (count($shimmedNodeIds) === 0)
            {
                if (is_callable([$query, 'setIsImpossibleQuery']))
                {
                    // instead of throwing, mark as impossible and throw later
                    $query->setIsImpossibleQuery();
                }

                $constraint->setValues([-1]);
                return true;
            }

            // ensure this is a json integer list, and not a json object of string values
            $shimmedNodeIds = array_values($shimmedNodeIds);
            $constraint->setValues($shimmedNodeIds);
        }

        return $change;
    }

    public function applyTypeConstraintsFromInput(Query $query, Request $request, array &$urlConstraints)
    {
        $searchNodeRoots = $this->searchNodeRoots ?? $request->filter('c.nodes', 'array-uint');
        $this->armSearchNodeHacks = !$request->filter('c.thread', 'uint') &&
                                    ($searchNodeRoots && reset($searchNodeRoots)) &&
                                    $request->filter('c.child_nodes', 'bool');

        /** @var ExtendedNodeRepo $nodeRepo */
        $nodeRepo = Helper::repository(NodeRepo::class);
        $nodeRepo->shimmedProxyNodes = false;
        $structure = Helper::getEntityStructure(LinkForumEntity::class);
        if ($this->armSearchNodeHacks && ($structure->relations['ProxiedForum'] ?? false))
        {
            $nodeRepo->setInjectProxiedSubNodesForSvProxyLinkForum('getFullNodeListWithTypeData');
        }

        try
        {
            parent::applyTypeConstraintsFromInput($query, $request, $urlConstraints);

            if ($nodeRepo->shimmedProxyNodes ?? false)
            {
                $constraints = $query->getMetadataConstraints();
                foreach ($constraints as $constraint)
                {
                    if ($constraint->getKey() === 'node')
                    {
                        $this->rewriteQueryProxiedNodes($query, $constraint);
                    }
                }
            }
        }
        finally
        {
            $nodeRepo->shimmedProxyNodes = false;
            $this->armSearchNodeHacks = false;

            $nodeRepo->clearInjectProxiedSubNodesForSvProxyLinkForum('getFullNodeListWithTypeData');
        }
    }
}
