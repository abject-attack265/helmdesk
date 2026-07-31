<?php

namespace App\Enums;

/**
 * knowledge_nodes.kind 节点种类，区分原文分段节点与 RAPTOR 摘要节点。
 */
enum KnowledgeNodeKind: string
{
    case Segment = 'segment';
    case Summary = 'summary';
}
