<?php

use Illuminate\Database\Eloquent\Model;
use NLD\Search\Searchable;
use NLD\Search\SearchStrategy;

it('returns empty array when no model properties or fields are given', function () {
    $model = new class extends Model
    {
        use Searchable;
    };

    $fields = invokePrivateMethod($model, 'getSearchFields');

    expect($fields)
        ->toBe([]);
});

it('returns fields array from model properties', function () {
    $model = new class extends Model
    {
        use Searchable;

        protected $searchable = [
            'model' => SearchStrategy::EXACT,
            'second' => SearchStrategy::IN_WORDS,
        ];
    };

    $fields = invokePrivateMethod($model, 'getSearchFields');

    expect($fields)->toBe([
        'model' => SearchStrategy::EXACT,
        'second' => SearchStrategy::IN_WORDS,
    ]);
});

it('returns received fields possibly overriding model properties', function () {
    $model = new class extends Model
    {
        use Searchable;

        protected $searchable = 'model';
    };

    $fields = invokePrivateMethod($model, 'getSearchFields', [
        'first' => SearchStrategy::EXACT,
        'second' => SearchStrategy::IN_WORDS,
    ]);

    expect($fields)->toBe([
        'first' => SearchStrategy::EXACT,
        'second' => SearchStrategy::IN_WORDS,
    ]);
});

it('builds fields array from string using default strategy', function () {
    $model = new class extends Model
    {
        use Searchable;
    };

    $fields = invokePrivateMethod($model, 'getSearchFields', ' first , second');

    expect($fields)->toBe([
        'first' => SearchStrategy::START_OF_WORDS,
        'second' => SearchStrategy::START_OF_WORDS,
    ]);
});

it('applies default strategy to fields with no strategy', function () {
    $model = new class extends Model
    {
        use Searchable;
    };

    $fields = invokePrivateMethod($model, 'getSearchFields', [
        'default',
        'exact' => SearchStrategy::EXACT,
    ]);

    expect($fields)->toBe([
        'default' => SearchStrategy::START_OF_WORDS,
        'exact' => SearchStrategy::EXACT,
    ]);
});

it('uses default strategy from model property', function () {
    $model = new class extends Model
    {
        use Searchable;

        protected $searchStrategy = SearchStrategy::IN_WORDS;
    };

    $fields = invokePrivateMethod($model, 'getSearchFields', [
        'first' => SearchStrategy::EXACT,
        'second',
    ]);

    expect($fields)->toBe([
        'first' => SearchStrategy::EXACT,
        'second' => SearchStrategy::IN_WORDS,
    ]);
});
