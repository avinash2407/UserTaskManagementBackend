<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|

*/
Route::options(
    '/{any:.*}',
    [
        'middleware' => 'cors',
        function () {
            return response(['status' => 'success']);
        }
    ]
);
$router->group(['prefix' => 'api/users','middleware' => 'cors'], function () use ($router) {
    $router->post('login', 'UserController@login');
    $router->post('signup', 'UserController@signup');
    $router->get('forgotpassword', 'UserController@sendmail');
});
$router->group(['prefix' => 'api/users','middleware' => ['cors','jwt']], function () use ($router) {
    $router->post('create', 'UserController@create');
    $router->post('delete', 'UserController@delete');
    $router->post('rolechange', 'UserController@roleChange');
    $router->get('userlist', 'UserController@listUsers');
    $router->get('user/{id}', 'UserController@profile');
});
$router->group(['middleware' => ['cors','reset']], function () use ($router) {
    $router->post('api/users/passwordreset', 'UserController@passReset');
});
$router->group(['prefix' => 'api/tasks' , 'middleware' => ['cors' ,'jwt']], function () use ($router) {
    $router->post('create', 'TasksController@createTask');
    $router->get('list', 'TasksController@getTaskList');
    $router->post('update', 'TasksController@updateTask');
    $router->post('updatestatus', 'TasksController@updateStatus');
    $router->post('delete', 'TasksController@deleteTask');
    $router->get('dashboardstats', 'TasksController@sendstats');
    $router->get('tasksondashboard', 'TasksController@dashboardtasks');
});
