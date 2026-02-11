<?php

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use NLD\Search\Searchable;
use NLD\Search\SearchStrategy;
use NLD\Search\Tests\Support\TestModel;

it('uses Search trait', function () {
    expect(Searchable::class)
        ->toBeIn(class_uses_recursive(TestModel::class));
});

it('has search scope', function () {
    expect(TestModel::search('search'))
        ->toBeInstanceOf(Builder::class);
});

it('calls getSearchFields() passing fields value', function () {
    $spy = Mockery::mock(TestModel::class)->makePartial();

    /** @disregard P1013 Undefined method */
    $spy->search('search', 'spy,fields');

    /** @disregard P1013 Undefined method */
    $spy
        ->shouldHaveReceived('getSearchFields')
        ->with('spy,fields');
});

it('returns all records if no search fields given', function () {
    $sql = TestModel::search('search', '')->toSql();

    expect($sql)
        ->toBe('select * from "test_models"');
});

it('returns all records for empty search', function () {
    $sql = TestModel::search('', 'name')->toSql();

    expect($sql)
        ->toBe('select * from "test_models"');
});

it('builds query for default START_OF_WORDS strategy', function () {
    $builder = TestModel::search('search words', 'first,second');
    $driver = TestModel::query()->getConnection()->getDriverName();

    if ($driver === 'pgsql') {
        expect($builder->toSql())
            ->toContain('~* ?')
            ->and($builder->getBindings())
            ->toBe([
                '\\ysearch',
                '\\ysearch',
                '\\ywords',
                '\\ywords',
            ]);

        return;
    }

    if (in_array($driver, ['mysql', 'mariadb'], true)) {
        expect($builder->toSql())
            ->toContain('regexp ?')
            ->and($builder->getBindings())
            ->toBe([
                '\\bsearch',
                '\\bsearch',
                '\\bwords',
                '\\bwords',
            ]);

        return;
    }

    expect($builder->toSql())
        ->toContain('like ?')
        ->not->toContain('regexp')
        ->and($builder->getBindings())
        ->toBe([
            '%search%',
            '%search%',
            '%words%',
            '%words%',
        ]);
});

it('builds query for IN_WORDS strategy', function () {
    $builder = TestModel::search('search words', [
        'first' => SearchStrategy::IN_WORDS,
        'second' => SearchStrategy::IN_WORDS,
    ]);

    expect($builder)
        ->toSql()
        ->toBe('select * from "test_models" where ("first" like ? or "second" like ?) and ("first" like ? or "second" like ?)')
        ->getBindings()
        ->toBe([
            '%search%',
            '%search%',
            '%words%',
            '%words%',
        ]);
});

it('builds query for START_OF_STRING strategy', function () {
    $builder = TestModel::search('search words', [
        'first' => SearchStrategy::START_OF_STRING,
        'second' => SearchStrategy::START_OF_STRING,
    ]);

    expect($builder)
        ->toSql()
        ->toBe('select * from "test_models" where ("first" like ? or "second" like ?) and ("first" like ? or "second" like ?)')
        ->getBindings()
        ->toBe([
            'search%',
            'search%',
            'words%',
            'words%',
        ]);
});

it('builds query for EXACT strategy', function () {
    $builder = TestModel::search('search words', [
        'first' => SearchStrategy::EXACT,
        'second' => SearchStrategy::EXACT,
    ]);

    expect($builder)
        ->toSql()
        ->toBe('select * from "test_models" where ("first" = ? or "second" = ?) and ("first" = ? or "second" = ?)')->getBindings()
        ->toBe([
            'search',
            'search',
            'words',
            'words',
        ]);
});

// Security fix tests

it('returns unmodified query for whitespace-only search', function () {
    $sql = TestModel::search('   ', 'name')->toSql();

    expect($sql)
        ->toBe('select * from "test_models"');
});

it('does not treat zero string as empty search', function () {
    $builder = TestModel::search('0', [
        'first' => SearchStrategy::EXACT,
    ]);

    expect($builder)
        ->toSql()
        ->toBe('select * from "test_models" where ("first" = ?)')
        ->getBindings()
        ->toBe(['0']);
});

it('normalizes tabs and multiple spaces into words', function () {
    $builder = TestModel::search("search\t\t  words", [
        'first' => SearchStrategy::EXACT,
    ]);

    expect($builder->getBindings())
        ->toBe(['search', 'words']);
});

it('caps word count at 10', function () {
    $search = implode(' ', range(1, 15));
    $builder = TestModel::search($search, [
        'first' => SearchStrategy::EXACT,
    ]);

    expect($builder->getBindings())
        ->toHaveCount(10);
});

it('rejects field names with SQL injection characters', function () {
    $builder = TestModel::search('test', [
        'name; DROP TABLE users' => SearchStrategy::EXACT,
    ]);

    expect($builder->toSql())
        ->not->toContain('DROP TABLE')
        ->and($builder->getBindings())
        ->toBeEmpty();
});

it('rejects field names with spaces', function () {
    $builder = TestModel::search('test', [
        'field name' => SearchStrategy::EXACT,
    ]);

    expect($builder->toSql())
        ->not->toContain('field name')
        ->and($builder->getBindings())
        ->toBeEmpty();
});

it('allows dot notation in field names', function () {
    $builder = TestModel::search('test', [
        'relation.field' => SearchStrategy::EXACT,
    ]);

    expect($builder->toSql())
        ->toBe('select * from "test_models" where ("relation"."field" = ?)');
});

it('escapes LIKE wildcards in IN_WORDS strategy', function () {
    $builder = TestModel::search('100%', [
        'first' => SearchStrategy::IN_WORDS,
    ]);

    expect($builder->getBindings())
        ->toBe(['%100\%%']);
});

it('escapes LIKE wildcards in START_OF_STRING strategy', function () {
    $builder = TestModel::search('test_value', [
        'first' => SearchStrategy::START_OF_STRING,
    ]);

    expect($builder->getBindings())
        ->toBe(['test\_value%']);
});

it('escapes regex metacharacters in START_OF_WORDS strategy', function () {
    $builder = TestModel::search('test.value', 'first');
    $driver = TestModel::query()->getConnection()->getDriverName();

    if ($driver === 'pgsql') {
        expect($builder->getBindings())
            ->toBe(['\\ytest\.value']);

        return;
    }

    if (in_array($driver, ['mysql', 'mariadb'], true)) {
        expect($builder->getBindings())
            ->toBe(['\\btest\.value']);

        return;
    }

    expect($builder->getBindings())
        ->toBe(['%test.value%']);
});

it('executes START_OF_WORDS search on sqlite without regexp function', function () {
    $connection = TestModel::query()->getConnection();

    if ($connection->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('SQLite specific test.');
    }

    $schema = $connection->getSchemaBuilder();
    $schema->dropIfExists('test_models');
    $schema->create('test_models', function (Blueprint $table) {
        $table->id();
        $table->string('first');
    });

    $connection->table('test_models')->insert([
        ['first' => 'alpha beta'],
        ['first' => 'beta'],
    ]);

    expect(TestModel::search('alp', 'first')->pluck('first')->all())
        ->toBe(['alpha beta']);
});
