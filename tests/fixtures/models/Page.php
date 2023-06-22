<?php

namespace Winter\Blocks\Tests\Fixtures\Models;

use Winter\Storm\Database\Model;

class Page extends Model
{
    public $table = 'winter_blocks_tests_pages';

    public $jsonable = [
        'content',
    ];
}
