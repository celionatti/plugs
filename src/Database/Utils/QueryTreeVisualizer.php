<?php

declare(strict_types=1);

namespace Plugs\Database\Utils;

class QueryTreeVisualizer
{
    public static function visualize(array $queries): string
    {
        $tree = self::buildTree($queries);
        return self::renderTree($tree);
    }

    private static function buildTree(array $queries, ?string $parentId = null): array
    {
        $branch = [];
        foreach ($queries as $query) {
            if ($query['parent_id'] === $parentId) {
                $children = self::buildTree($queries, $query['id']);
                if ($children) {
                    $query['children'] = $children;
                }
                $branch[] = $query;
            }
        }
        return $branch;
    }

    private static function renderTree(array $tree, int $indent = 0): string
    {
        $output = "";
        $padding = str_repeat("  ", $indent);
        foreach ($tree as $node) {
            $output .= "{$padding}↳ [{$node['id']}] {$node['sql']} (" . number_format($node['time'] * 1000, 2) . "ms)\n";
            if (!empty($node['children'])) {
                $output .= self::renderTree($node['children'], $indent + 1);
            }
        }
        return $output;
    }
}
