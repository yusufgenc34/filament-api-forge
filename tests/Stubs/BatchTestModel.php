<?php

namespace YusufGenc34\FilamentApiForge\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

class BatchTestModel extends Model
{
    protected $table = 'batch_test_models';
    protected $fillable = ['title', 'status', 'priority'];
}
