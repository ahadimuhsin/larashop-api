<?php

namespace App\Http\Controllers;

use App\Book;
use App\Http\Resources\Book as ResourcesBook;
use App\Http\Resources\Books as BooksCollection;
use Illuminate\Http\Request;

class BookController extends Controller
{

   public function index()
   {
       $criteria = Book::paginate(6);

       return new BooksCollection($criteria);
   }

   public function top($count)
   {
       $criteria = Book::select('*')
       ->orderBy('views', 'desc')
       ->limit($count)
       ->get();

       return new BooksCollection($criteria);
   }

   public function slug($slug)
   {
       $criteria = Book::where('slug', $slug)->firstOrFail();
       $criteria->views += 1;
       $criteria->save();
       return new ResourcesBook($criteria);
   }

   public function search ($keyword)
   {
       $criteria = Book::select('*')
       ->where('title', 'LIKE', '%'.$keyword.'%')
       ->orderBy('views', 'DESC')
       ->get();

       return new BooksCollection($criteria);
   }
}
