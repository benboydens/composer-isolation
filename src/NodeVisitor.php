<?php

namespace Ox6d617474\Isolate;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitorAbstract;

final class NodeVisitor extends NodeVisitorAbstract
{
    /**
     * Namespace prefix
     *
     * @var string
     */
    private string $prefix;

    /**
     * Namespace checker
     *
     * @var NamespaceChecker
     */
    private NamespaceChecker $checker;

    /**
     * Did we perform a transform?
     *
     * @var bool
     */
    private bool $transformed;

    /**
     * Current namespace
     *
     * @var bool
     */
    private $namespace;

    /**
     * Active aliases
     *
     * @var array
     */
    private array $aliases;

    /**
     * Class constructor
     *
     * @param string $prefix
     * @param NamespaceChecker $checker
     */
    public function __construct(string $prefix, NamespaceChecker $checker)
    {
        $this->prefix = $prefix;
        $this->checker = $checker;
        $this->transformed = false;
        $this->namespace = '__global__';
        $this->aliases = [];
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Namespace_) {
            // No name means global namespace, so leave it alone
            if (isset($node->name)) {
                // Keep track of the current namespace
                $this->namespace = implode('\\', $node->name->getParts());

                $node->name->name = join('\\', $this->transformNamespace($node->name->getParts()));
            }
        } elseif ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                if (!empty($use->alias)) {
                    // Keep track of any aliases being used
                    $this->aliases[] = $use->alias;
                }
                if (count($use->name->getParts()) > 1) { // Single part means global, so ignore
                    // Split off the classname and transform the namespace
                    $ns = $use->name->getParts();
                    $i =  count($ns) - 1;
                    $ns = array_slice($ns, 0, $i);
                    $ns = $this->transformNamespace($ns);

                    // Put the classname back on and override
                    $ns[] = $use->name->getParts()[$i];
                    $use->name->name = join('\\', array_filter($ns));
                }
            }
        } elseif ($node instanceof String_) {
            $transform = false;
            if ($this->checker->shouldTransform($node->value)) {
                $transform = true;
            } else {
                // If it's a fully qualified classname, it won't match a namespace
                // Pull the classname off and try again
                $ns = implode('\\', array_slice(explode('\\', $node->value), 0, -1));
                $ns = sprintf('%s\\', trim($ns, '\\')); // Has to end in a \ or it won't match
                if ($this->checker->shouldTransform($ns)) {
                    $transform = true;
                }
            }

            if ($transform) {
                $node->value = sprintf('%s\\%s', $this->prefix, ltrim($node->value, '\\'));
                $this->transformed = true;
            }
        } elseif ($node instanceof Node\Name) {
            if ($node->isFullyQualified() || $this->namespace == '__global__') {
                if (count($node->getParts()) > 1) { // Single part means global, so ignores
                    // If the first part is aliased, then we don't need to transform
                    // The alias should already be transformed properly
                    $aliased = false;
                    foreach ($this->aliases as $alias) {
                        if ($node->getParts()[0] == $alias) {
                            $aliased = true;
                            break;
                        }
                    }

                    if (!$aliased) {
                        // Split off the classname and transform the namespace
                        $ns = $node->getParts();
                        $i = count($ns) - 1;
                        $ns = array_slice($ns, 0, $i);
                        $ns = $this->transformNamespace($ns);

                        // Put the classname back on and override
                        $ns[] = $node->getParts()[$i];
                        $node->name = join('\\', array_filter($ns));
                    }
                }
            }
        }
    }

    /**
     * Perform a transformation on a namespace if we should
     *
     * @param array $parts
     *
     * @return array
     */
    private function transformNamespace(array $parts): array
    {
        // Build the exploded namespace into a string with a slash at the end
        $string = sprintf('%s\\', trim(implode('\\', $parts), '\\'));

        // Prepend the prefix
        if ($this->checker->shouldTransform($string)) {
            array_unshift($parts, $this->prefix);
            $this->transformed = true;
        }

        return $parts;
    }

    /**
     * Did we perform a transformation
     *
     * @return bool
     */
    public function didTransform(): bool
    {
        return $this->transformed;
    }
}
