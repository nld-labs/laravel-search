<?php

namespace NLD\Search\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use NLD\Search\Searchable;

class TestModel extends Model
{
    use Searchable;

    protected $table = 'test_models';

    protected $fillable = [
        'field1',
        'field2',
        'field3',
    ];
}
