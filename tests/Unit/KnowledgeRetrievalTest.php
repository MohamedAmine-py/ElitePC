<?php

namespace Tests\Unit;

use App\Services\EliteAI\KnowledgeLoader;
use App\Services\EliteAI\KnowledgeRetriever;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class KnowledgeRetrievalTest extends TestCase
{
    private function loader(): KnowledgeLoader
    {
        return new KnowledgeLoader(dirname(__DIR__, 2).'/knowledge');
    }

    public function test_corpus_loads_h2_sections_with_context_and_unchanged_contents(): void
    {
        $before = [];
        foreach (KnowledgeLoader::FILES as $source) {
            $before[$source] = hash_file('sha256', dirname(__DIR__, 2).'/knowledge/'.$source);
        }
        $chunks = $this->loader()->load();
        $this->assertCount(34, $chunks);
        $counts = array_count_values(array_column($chunks, 'source'));
        $this->assertSame(['hardware.md' => 7, 'buying-guide.md' => 11, 'compatibility.md' => 8, 'store.md' => 8], $counts);
        foreach ($chunks as $chunk) {
            foreach (['source', 'document_title', 'document_context', 'section_heading', 'content'] as $field) {
                $this->assertNotSame('', $chunk[$field]);
            }
        }
        $ram = array_values(array_filter($chunks, fn ($c) => $c['source'] === 'hardware.md' && $c['section_heading'] === 'RAM'))[0];
        $this->assertSame('PC Hardware Reference', $ram['document_title']);
        $this->assertStringContainsString('16 GB', $ram['content']);
        $this->assertStringContainsString('general hardware principles', $ram['document_context']);
        (new KnowledgeRetriever($this->loader()))->retrieve('memory for gaming');
        foreach ($before as $source => $hash) {
            $this->assertSame($hash, hash_file('sha256', dirname(__DIR__, 2).'/knowledge/'.$source));
        }
    }

    public function test_chunker_preserves_paragraphs_nested_headings_links_and_fenced_code(): void
    {
        $markdown = <<<'MD'
# Reference

Document context.

## CPU notes

First paragraph.

### Details

Second paragraph with a [link](https://example.test/reference).

```text
## This is code, not a section
```

## C#

Another section.
MD;
        $chunks = $this->loader()->chunk('fixture.md', str_replace("\n", "\r\n", $markdown));
        $this->assertCount(2, $chunks);
        $this->assertSame('Document context.', $chunks[0]['document_context']);
        $this->assertSame('CPU notes', $chunks[0]['section_heading']);
        $this->assertSame("First paragraph.\n\n### Details\n\nSecond paragraph with a [link](https://example.test/reference).\n\n```text\n## This is code, not a section\n```", $chunks[0]['content']);
        $this->assertSame('C#', $chunks[1]['section_heading']);
        $this->assertSame('Another section.', $chunks[1]['content']);
    }

    public function test_missing_source_is_reported_instead_of_silently_omitting_knowledge(): void
    {
        $this->expectException(RuntimeException::class);
        (new KnowledgeLoader(sys_get_temp_dir().'/absent-knowledge-'.uniqid()))->load();
    }

    public function test_missing_document_title_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->loader()->chunk('invalid.md', "## Section\n\nContent.");
    }

    public static function questions(): array
    {
        return [
            ['Is 16GB enough for gaming?', 'hardware.md', 'RAM'],
            ['Can DDR5 RAM work in a DDR4 motherboard?', 'compatibility.md', 'RAM Compatibility'],
            ['What should I prioritize for 1440p?', 'buying-guide.md', '1440p Gaming'],
            ['How does the guest cart work?', 'store.md', 'Guest and Account Carts'],
            ['Can I download an invoice?', 'store.md', 'Viewing Orders and Invoices'],
            ['What is an NVMe SSD?', 'hardware.md', 'Storage'],
            ['How do I know if a PSU is compatible?', 'compatibility.md', 'PSU Compatibility'],
            ['Do you offer a warranty?', null, null],
        ];
    }

    #[DataProvider('questions')]
    public function test_expected_knowledge_areas(string $query, ?string $source, ?string $section): void
    {
        $results = (new KnowledgeRetriever($this->loader()))->retrieve($query);
        if ($source === null) {
            $this->assertSame([], $results);

            return;
        }
        $this->assertNotEmpty($results);
        $this->assertLessThanOrEqual(3, count($results));
        $this->assertSame($source, $results[0]['source']);
        $this->assertSame($section, $results[0]['section_heading']);
        $this->assertGreaterThanOrEqual(3.0, $results[0]['score']);
        $this->assertNotEmpty($results[0]['matched_terms']);
    }

    public static function terminology(): array
    {
        return [
            ['RAM', 'memory'],
            ['GPU', 'graphics card'],
            ['CPU', 'processor'],
            ['SSD', 'storage'],
            ['PSU', 'power supply'],
            ['1440p', 'QHD'],
            ['guest cart', 'guest shopping cart'],
            ['Is 16GB enough for gaming?', 'IS 16 GB ENOUGH FOR GAMING!!!'],
        ];
    }

    #[DataProvider('terminology')]
    public function test_explicit_terminology_and_normalization(string $first, string $second): void
    {
        $retriever = new KnowledgeRetriever($this->loader());
        $results = $retriever->retrieve($first);
        $this->assertNotEmpty($results);
        $this->assertSame($results, $retriever->retrieve($second));
    }

    public function test_deterministic_bounded_results_do_not_pad_with_weak_matches(): void
    {
        $retriever = new KnowledgeRetriever($this->loader());
        $first = $retriever->retrieve('NVMe SSD');
        $this->assertSame($first, $retriever->retrieve('NVMe SSD'));
        $this->assertSame($first, (new KnowledgeRetriever($this->loader()))->retrieve('NVMe SSD'));
        $this->assertSame(array_slice($first, 0, 1), $retriever->retrieve('NVMe SSD', 1));
        $this->assertCount(1, $retriever->retrieve('Is 16GB enough for gaming?'));
        $this->assertCount(1, $retriever->retrieve('What should I prioritize for 1440p?', 10));
        foreach (['', 'the and is', 'volcano astronomy telescope', 'Do you offer a warranty?'] as $query) {
            $this->assertSame([], $retriever->retrieve($query));
        }
    }

    public function test_heading_then_title_outweigh_body_and_ties_are_stable(): void
    {
        $chunk = fn ($source, $title, $heading, $content) => [
            'source' => $source, 'document_title' => $title, 'document_context' => '',
            'section_heading' => $heading, 'content' => $content,
        ];
        $loader = $this->createMock(KnowledgeLoader::class);
        $loader->method('load')->willReturn([
            $chunk('body.md', 'Guide', 'Overview', 'processor'),
            $chunk('title.md', 'Processor', 'Overview', 'Details'),
            $chunk('b.md', 'Guide', 'CPU', 'Details'),
            $chunk('a.md', 'Guide', 'CPU', 'Details'),
        ]);
        $results = (new KnowledgeRetriever($loader))->retrieve('processor', 10);
        $this->assertSame(['a.md', 'b.md'], array_column($results, 'source'));
        $loader = $this->createMock(KnowledgeLoader::class);
        $loader->method('load')->willReturn([
            $chunk('body.md', 'Guide', 'Overview', 'processor'),
            $chunk('title.md', 'Processor', 'Overview', 'Details'),
        ]);
        $this->assertSame('title.md', (new KnowledgeRetriever($loader))->retrieve('CPU')[0]['source']);
    }

    public function test_link_targets_are_not_indexed_as_body_words(): void
    {
        $loader = $this->createMock(KnowledgeLoader::class);
        $loader->method('load')->willReturn([[
            'source' => 'fixture.md', 'document_title' => 'Guide', 'document_context' => '',
            'section_heading' => 'CPU', 'content' => '[CPU reference](https://example.test/galacticbananas)',
        ]]);
        $this->assertSame([], (new KnowledgeRetriever($loader))->retrieve('galacticbananas'));
    }

    public function test_invalid_result_limit_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new KnowledgeRetriever($this->loader()))->retrieve('RAM', 0);
    }
}
