<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Normalises message content from the various tool formats into plain text.
 * Tools store content either as a string or as an array of typed blocks
 * (e.g. [{type: "text", text: "..."}, {type: "thinking", ...}]).
 */
final class ContentFlattener
{
    public static function flatten(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (!is_array($content)) {
            return is_scalar($content) ? (string) $content : '';
        }

        $parts = [];
        foreach ($content as $block) {
            if (is_string($block)) {
                $parts[] = $block;
            } elseif (is_array($block) && isset($block['text']) && is_string($block['text'])) {
                $parts[] = $block['text'];
            }
            // Non-text blocks (thinking, tool_use, images) are skipped
        }

        return implode("\n", $parts);
    }
}
