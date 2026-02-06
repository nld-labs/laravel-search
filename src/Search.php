<?php

declare(strict_types=1);

namespace NLD\Search;

trait Search
{
    /**
     * Scope a query to search for a term across specified fields.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $search  The search term to look for
     * @param  array<string, SearchStrategy>|string  $fields  Fields to search in with optional strategies
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, ?string $search, array|string $fields = [])
    {
        if ($search === null || trim($search) === '') {
            return $query;
        }

        $fields = $this->getSearchFields($fields);
        if (empty($fields)) {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();
        $grammar = $query->getGrammar();
        $words = array_slice(
            preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY),
            0,
            10
        );

        foreach ($words as $word) {
            $query = $query->where(function ($query) use ($word, $fields, $driver, $grammar) {
                foreach ($fields as $field => $strategy) {
                    if (! preg_match('/\A[a-zA-Z_][a-zA-Z0-9_.]*\z/', $field)) {
                        continue;
                    }

                    match ($strategy) {
                        SearchStrategy::IN_WORDS => $query->orWhere($field, 'like', '%'.$this->escapeLike($word).'%'),
                        SearchStrategy::START_OF_WORDS => $this->addStartOfWordsCondition(
                            $query,
                            $field,
                            $word,
                            $driver,
                            $grammar
                        ),
                        SearchStrategy::START_OF_STRING => $query->orWhere($field, 'like', $this->escapeLike($word).'%'),
                        SearchStrategy::EXACT => $query->orWhere($field, $word),
                    };
                }
            });
        }

        return $query;
    }

    /**
     * Return array of searchable fields with search strategies.
     *
     * Converts field configuration into a normalized array mapping field names to search strategies.
     * Falls back to model's $searchFields property if no fields are provided.
     *
     * @param  array<string, SearchStrategy>|string  $fields  Fields to search in with optional strategies
     * @return array<string, SearchStrategy> Normalized array of field names to search strategies
     */
    protected function getSearchFields(string|array $fields = []): array
    {
        if (empty($fields) && property_exists($this, 'searchFields') && ! empty($this->searchFields)) {
            $fields = $this->searchFields;
        }

        if (empty($fields)) {
            return [];
        }

        $strategy = property_exists($this, 'searchStrategy')
            ? $this->searchStrategy
            : SearchStrategy::START_OF_WORDS;

        if (is_string($fields)) {
            $fields = array_filter(
                array_values(array_map('trim', explode(',', $fields)))
            );
        }

        $fields = array_reduce(array_keys($fields), function ($carry, $key) use ($fields, $strategy) {
            if (is_string($key)) {
                $carry[$key] = $fields[$key];
            } else {
                $carry[$fields[$key]] = $strategy;
            }

            return $carry;
        }, []);

        return $fields;
    }

    /**
     * Escape special characters for LIKE queries.
     *
     * @param  string  $value  The value to escape
     * @return string The escaped value safe for LIKE queries
     */
    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    /**
     * Add a database-specific condition for matching the start of words.
     *
     * Uses regex for PostgreSQL and MySQL/MariaDB, falls back to LIKE for SQLite.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $field  The field to search in
     * @param  string  $word  The word to search for
     * @param  string  $driver  The database driver name
     * @param  \Illuminate\Database\Query\Grammars\Grammar  $grammar  The query grammar instance
     */
    private function addStartOfWordsCondition($query, string $field, string $word, string $driver, $grammar): void
    {
        if ($driver === 'pgsql') {
            $query->orWhereRaw($grammar->wrap($field).' ~* ?', ['\y'.preg_quote($word, '/')]);

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query->orWhere($field, 'regexp', '\\b'.preg_quote($word, '/'));

            return;
        }

        $query->orWhere($field, 'like', '%'.$this->escapeLike($word).'%');
    }
}
