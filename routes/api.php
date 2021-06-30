<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v2')->group(function(){
    // Auth route
    Route::post('login', 'AuthController@login');
    Route::post('register', 'AuthController@register');
    // Category Route
    Route::get('categories/random/{count}', 'CategoryController@random');
    Route::get('categories', 'CategoryController@index');
    Route::get('categories/slug/{slug}', 'CategoryController@slug');
    // Book Route
    Route::get('books/top/{count}', 'BookController@top');
    Route::get('books', 'BookController@index');
    Route::get('books/slug/{slug}', 'BookController@slug');
    Route::get('books/search/{keyword}', 'BookController@search');

    //ambil data provinsi dan kota
    Route::get('provinces', 'ShopController@provinces');
    Route::get('cities', 'ShopController@cities');
    // Private route
    Route::group(['middleware' => ['auth:api']], function () {
        Route::post('logout', 'AuthController@logout');
        Route::post('shipping', 'ShopController@shipping');
        Route::post('services', 'ShopController@services');
        Route::post('payment', 'ShopController@payment');
        Route::get("my-order", "ShopController@myOrder");
    });

    //ambil data kurir
    Route::get('couriers', 'ShopController@couriers');

});
