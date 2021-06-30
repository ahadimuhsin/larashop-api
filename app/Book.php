<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    //
    use SoftDeletes;
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'title', 'slug', 'description', 'author',
        'publisher', 'cover', 'weight', 'stock',
        'status'
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
}
