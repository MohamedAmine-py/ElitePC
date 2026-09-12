<?php

namespace App\Services\EliteAI;

use InvalidArgumentException;

/** Small deterministic lexical search over local knowledge sections. */
class KnowledgeRetriever
{
    public const MAX_RESULTS = 10;

    private const STOP_WORDS = 'a an and are as at be been but by can could did do does for from had has have how i if in into is it its me my of on or our should that the their them there these they this those to us was we were what when where which who why will with would you your about please tell explain know enough prioritize work works offer';

    // Normalize equivalent terms, not entire questions or source-specific answers.
    private const ALIASES = [
        '/\bgraphics? cards?\b/u' => 'gpu',
        '/\bpower (?:supply|supplies)\b/u' => 'psu',
        '/\bshopping carts?\b/u' => 'cart',
        '/\bwork(?:s)? (?:with|in)\b/u' => 'compatible',
        '/\b(?:ram|memory)\b/u' => 'ram',
        '/\b(?:cpus?|processors?)\b/u' => 'cpu',
        '/\bgpus?\b/u' => 'gpu',
        '/\bpsus?\b/u' => 'psu',
        '/\b(?:ssds?|storage|solid state drives?)\b/u' => 'storage',
        '/\bqhd\b/u' => '1440p',
        '/\b(?:compatibility|compatible)\b/u' => 'compatible',
    ];

    public function __construct(private KnowledgeLoader $loader) {}

    /** Results retain all chunk metadata plus score, coverage and matched normalized terms. */
    public function retrieve(string $query, int $limit = 3): array
    {
        if ($limit < 1 || $limit > self::MAX_RESULTS) {
            throw new InvalidArgumentException('Result limit must be between 1 and 10.');
        }
        $terms = $this->terms($query);
        if ($terms === []) {
            return [];
        }
        $chunks = $this->loader->load();
        $index = [];
        $frequency = [];
        foreach ($chunks as $chunk) {
            $fields = [
                'heading' => $this->terms($chunk['section_heading']),
                'title' => $this->terms($chunk['document_title']),
                'body' => $this->terms($chunk['content']),
            ];
            foreach (array_unique(array_merge(...array_values($fields))) as $term) {
                $frequency[$term] = ($frequency[$term] ?? 0) + 1;
            }
            $index[] = $fields;
        }

        $results = [];
        foreach ($chunks as $i => $chunk) {
            $fields = $index[$i];
            $matched = [];
            $score = 0.0;
            foreach ($terms as $term) {
                $weight = (in_array($term, $fields['heading'], true) ? 6 / sqrt(max(1, count($fields['heading']))) : 0)
                    + (in_array($term, $fields['title'], true) ? 2 : 0)
                    + (in_array($term, $fields['body'], true) ? 1 : 0);
                if ($weight > 0) {
                    $matched[] = $term;
                    $score += $weight * (1 + log(1 + count($chunks) / (1 + $frequency[$term])));
                }
            }
            $coverage = count($matched) / count($terms);
            // Coverage discourages a generic "gaming" hit from beating a specific capacity answer.
            $score *= $coverage ** 2;
            if ($coverage >= 0.5 && $score >= 3.0) {
                $results[] = array_merge($chunk, [
                    'score' => round($score, 3),
                    'coverage' => round($coverage, 3),
                    'matched_terms' => $matched,
                ]);
            }
        }
        usort($results, fn ($a, $b) => ($b['score'] <=> $a['score'])
            ?: strcmp($a['source'], $b['source'])
            ?: strcmp($a['section_heading'], $b['section_heading']));

        // Do not pad the result list with much weaker matches.
        $threshold = ($results[0]['score'] ?? 0) * 0.6;

        return array_slice(array_values(array_filter($results, fn ($result) => $result['score'] >= $threshold)), 0, $limit);
    }

    private function terms(string $text): array
    {
        // Link labels are useful; URL paths and hostnames should not drive ranking.
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $text);
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = preg_replace('/\b(\d+)\s*(gb|tb)\b/u', '$1$2', $text);
        $text = preg_replace(array_keys(self::ALIASES), array_values(self::ALIASES), $text);
        $stopWords = explode(' ', self::STOP_WORDS);
        $terms = [];
        foreach (preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            if (in_array($term, $stopWords, true)) {
                continue;
            }
            // Modest plural normalization, not a general-purpose stemmer.
            if (strlen($term) > 3 && str_ends_with($term, 's') && ! str_ends_with($term, 'ss')) {
                $term = substr($term, 0, -1);
            }
            $terms[] = $term;
        }

        return array_values(array_unique($terms));
    }
}
