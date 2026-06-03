<?php

namespace YusufGenc34\FilamentApiForge\Tests\Stubs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    use HasFactory;

    protected $table = 'test_models';

    protected $fillable = ['title', 'body', 'status'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
