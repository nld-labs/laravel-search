# Laravel Search

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nld-labs/laravel-search.svg?style=flat-square)](https://packagist.org/packages/nld-labs/laravel-search)

A search trait for Laravel Eloquent models with multiple search strategies. Splits the search term into words, matches each word against every specified field, and supports per-field strategy selection with database-aware query building.

## Requirements

- PHP ≥ 8.2
- Laravel 11 or 12

## Installation

```bash
composer require nld-labs/laravel-search
```

## Quick Start

Add the `Searchable` trait to any Eloquent model:

```php
use NLD\Search\Searchable;
use NLD\Search\SearchStrategy;

class Post extends Model
{
    use Searchable;
}
```

Then call the `search` scope:

```php
Post::search('laravel auth', 'title,body')->get();
```

## Search Strategies

| Strategy          | Behaviour                                                                                                                                       | SQL                     |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------- |
| `START_OF_WORDS`  | Matches the term at the beginning of any word in the field. Uses `~*` on PostgreSQL, `REGEXP` on MySQL/MariaDB, falls back to `LIKE` on SQLite. | `"field" ~* '\yterm'`   |
| `IN_WORDS`        | Matches the term anywhere inside the field value.                                                                                               | `"field" LIKE '%term%'` |
| `START_OF_STRING` | Matches the term at the very beginning of the field value.                                                                                      | `"field" LIKE 'term%'`  |
| `EXACT`           | Matches the term exactly.                                                                                                                       | `"field" = 'term'`      |

The default strategy is `START_OF_WORDS`.

## Specifying Fields

### Comma-separated string

All fields use the default strategy (`START_OF_WORDS`, or the model's `$searchStrategy` property):

```php
Post::search('term', 'title,body')->get();
```

### Array with explicit strategies

```php
Post::search('term', [
    'title' => SearchStrategy::START_OF_WORDS,
    'body'  => SearchStrategy::IN_WORDS,
    'slug'  => SearchStrategy::START_OF_STRING,
    'code'  => SearchStrategy::EXACT,
])->get();
```

### Mixed array

Fields without a key use the default strategy:

```php
Post::search('term', [
    'title',                              // uses default strategy
    'body' => SearchStrategy::IN_WORDS,   // explicit strategy
])->get();
```

### Dot notation

Dot-notation field names are wrapped as qualified column references:

```php
Post::search('term', [
    'posts.title' => SearchStrategy::IN_WORDS,
])->get();
```

## Model Configuration

You can set default searchable fields and a default strategy directly on the model:

```php
use NLD\Search\Searchable;
use NLD\Search\SearchStrategy;

class Post extends Model
{
    use Searchable;

    protected array $searchable = [
        'title' => SearchStrategy::START_OF_WORDS,
        'body'  => SearchStrategy::IN_WORDS,
    ];

    // Optional: override the default strategy for fields passed without one
    protected SearchStrategy $searchStrategy = SearchStrategy::IN_WORDS;
}
```

When `$searchable` is defined, calling `search` without fields uses it automatically:

```php
Post::search('term')->get();
```

Fields passed explicitly always take precedence over the model property.

## How It Works

1. The search term is split into words (whitespace-separated, max 10 words).
2. Each word produces a `WHERE` clause (ANDed together — every word must match).
3. Inside each clause, every field produces an `OR` condition — matching any field is enough.
4. Field names are validated against `[a-zA-Z_][a-zA-Z0-9_.]*`; invalid names are silently skipped.
5. LIKE wildcards (`%`, `_`, `\`) and regex metacharacters are properly escaped.

## Testing

```bash
./vendor/bin/pest
```

## License

MIT © [Vitauts Stočka](mailto:vs@vits.lv). See [LICENSE](LICENSE) for details.
