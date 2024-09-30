<?php

namespace Ox6d617474\Isolate\FilehashVisitor;

use PhpParser\Node;

class AutoloadStaticVisitor extends AbstractVisitor
{

    private bool $entered = false;

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\PropertyProperty and $node->name == 'files') {
            $this->transformFilehashArray($node->default);
        }
    }

    /**
     * Did we perform a transformation
     *
     * @return bool|null
     */
    public function didTransform(): ?bool
    {
        return $this->transformed;
    }
}
