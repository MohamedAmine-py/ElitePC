<?php

namespace App\Console\Commands;

use App\Services\EliteAI\KnowledgeRetriever;
use Illuminate\Console\Command;
use RuntimeException;

class RetrieveKnowledge extends Command
{
    protected $signature = 'elite-ai:retrieve {query : Knowledge question} {--limit=3 : Maximum results (1-10)}';

    protected $description = 'Inspect local knowledge relevance without calling Gemini or changing store data';

    public function handle(KnowledgeRetriever $retriever): int
    {
        $limit = (string) $this->option('limit');
        if (! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > KnowledgeRetriever::MAX_RESULTS) {
            $this->error('Result limit must be between 1 and 10.');

            return self::INVALID;
        }
        $query = (string) $this->argument('query');
        $this->line('Query: '.$query);
        try {
            $results = $retriever->retrieve($query, (int) $limit);
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        if ($results === []) {
            $this->line('No sufficiently relevant knowledge found.');
        }
        foreach ($results as $i => $chunk) {
            $this->newLine();
            $this->line(($i + 1).'. '.$chunk['source']);
            $this->line('   Section: '.$chunk['section_heading']);
            $this->line('   Score: '.number_format($chunk['score'], 3, '.', ''));
            $this->line('   Matched terms: '.implode(', ', $chunk['matched_terms']));
        }

        return self::SUCCESS;
    }
}
