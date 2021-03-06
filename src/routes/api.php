<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
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

/**
 * @var Router $router
 */

$router
    ->group([
        'namespace' => 'V1',
        'prefix' => 'v1'
    ],function(Router $router){

        $router->group([
            'prefix' => 'auth'
        ],function(Router $router){

            $router->post('login','AuthController@login');
        });

        $router->group([
            'prefix' => 'admin',
            'namespace'=>'Admin'
        ],function(Router $router){
            $router->resource("movies","MovieController",[
                "only" => ["index","store","update","detail","show","destroy"]
            ])->middleware("role:admin");
        });
        $router->resource("movies","MovieController",[
            "only" => ["index","show"]
        ]);
        $router->post("movies/{movie}/like","LikeController@store")
            ->middleware('role:user|admin')
            ->name('movies.like.store');
        $router->delete("movies/{movie}/like","LikeController@destroy")
            ->middleware('role:user|admin')
            ->name('movies.like.destroy');
        $router->post("movies/{movie}/buy","MovieController@buy")
            ->middleware('auth')
            ->name("movie.buy");
        $router->post("movies/{movie}/rent","MovieController@rent")
            ->middleware('auth')
            ->name("movie.rent");

        $router->post("movies/{movie}/return","MovieController@return")
            ->middleware('auth')
            ->name("movie.return");
    });

/*Route::group([

    'middleware' => 'api',
    'prefix' => 'auth'

], function ($router) {

    Route::post('login', 'AuthController@login');
    Route::post('logout', 'AuthController@logout');
    Route::post('refresh', 'AuthController@refresh');
    Route::post('me', 'AuthController@me');

});*/
