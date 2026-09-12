<?php

namespace App\Services\EliteAI;

use RuntimeException;

/** Reads the four local documents without changing or indexing them. */
class KnowledgeLoader
{
    public const FILES = ['hardware.md', 'buying-guide.md', 'compatibility.md', 'store.md'];

    public function __construct(private ?string $directory = null) {}

    /** @return array<int, array{source: string, document_title: string, document_context: string, section_heading: string, content: string}> */
    public function load(): array
    {
        $chunks = [];
        foreach (self::FILES as $source) {
            $path = ($this->directory ?? base_path('knowledge')).DIRECTORY_SEPARATOR.$source;
            if (! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException("Knowledge document is not readable: {$source}");
            }
            $markdown = file_get_contents($path);
            if ($markdown === false) {
                throw new RuntimeException("Knowledge document could not be loaded: {$source}");
            }
            array_push($chunks, ...$this->chunk($source, $markdown));
        }

        return $chunks;
    }

    /** H2 boundaries only: paragraphs, lists, links and nested headings stay intact. */
    public function chunk(string $source, string $markdown): array
    {
        $title = '';
        $intro = [];
        $sections = [];
        $heading = null;
        $body = [];
        $fence = null;
        $flush = function () use (&$sections, &$heading, &$body) {
            if ($heading !== null && trim(implode("\n", $body)) !== '') {
                $sections[] = ['section_heading' => $heading, 'content' => trim(implode("\n", $body))];
            }
            $body = [];
        };

        foreach (preg_split('/\R/u', $markdown) as $line) {
            // A heading-looking line inside fenced code is content, not a boundary.
            if ($fence !== null) {
                if (preg_match('/^ {0,3}'.preg_quote($fence[0], '/').'{'.strlen($fence).',}\s*$/', $line)) {
                    $fence = null;
                }
            } elseif (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $match)) {
                $fence = $match[1];
            } elseif (preg_match('/^#\s+(.+?)(?:\s+#+)?\s*$/u', $line, $match)) {
                if ($title !== '') {
                    throw new RuntimeException("Knowledge document must have one H1: {$source}");
                }
                $title = $match[1];

                continue;
            } elseif (preg_match('/^##\s+(.+?)(?:\s+#+)?\s*$/u', $line, $match)) {
                $flush();
                $heading = $match[1];

                continue;
            }

            if ($heading === null) {
                $intro[] = $line;
            } else {
                $body[] = $line;
            }
        }
        $flush();
        if ($title === '') {
            throw new RuntimeException("Knowledge document is missing its H1: {$source}");
        }

        return array_map(fn ($section) => array_merge([
            'source' => $source,
            'document_title' => $title,
            'document_context' => trim(implode("\n", $intro)),
        ], $section), $sections);
    }
}
