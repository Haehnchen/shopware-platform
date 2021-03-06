<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Services;

use Shopware\Core\Framework\Snippet\SnippetEntity;

interface SnippetFlattenerInterface
{
    /**
     * Flattens a array with keys
     *
     * Example:
     * from:    [a => [b => [c => 1]]]
     * to:      [a.b.c => 1]
     *
     * @param array  $snippets
     * @param string $prefix
     *
     * @return array
     */
    public function flatten(array $snippets, string $prefix = ''): array;

    /**
     * Unflattens a flatten array (explode by ".")
     *
     * Example:
     * from:    [a.b.c => 1]
     * to:      [a => [b => [c => 1]]]
     *
     * @param SnippetEntity[] $snippets
     *
     * @return array
     */
    public function unflatten(array $snippets): array;
}
