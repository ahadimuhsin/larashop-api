<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\Categories as CategoryCollection;
use App\Category;
use App\Http\Resources\Category as ResourceCategory;

class CategoryController extends Controller
{
    //menampilkan seluruh data kategori, tapi per halaman cuma 6
    public function index(){
        $criteria = Category::paginate(6);
        return new CategoryCollection($criteria);
    }
    //menampilkan data kategori secara random
    public function random ($count)
    {
        $criteria = Category::select('*')
        ->inRandomOrder()
        ->limit($count)
        ->get();

        return new CategoryCollection($criteria);
    }

    // Fungsi unutuk menampilkan data detail category berdasarkan slugnya serta data books
    //termasuk kategori tersebut
    public function slug($slug){
        $criteria = Category::where('slug', $slug)->first();

        return new ResourceCategory($criteria);
    }
}
