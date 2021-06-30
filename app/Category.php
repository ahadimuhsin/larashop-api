<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Book;

class Category extends Model
{
    //
    use SoftDeletes;
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'name', 'slug', 'image', 'status'
    ];

    public function books()
    {
        return $this->belongsToMany(Book::class, 'book_category',
        'category_id', 'book_id');
    }
}
