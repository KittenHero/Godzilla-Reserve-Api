<?php

Route::post('/init', 'AuthController@init');
Route::post('/login', 'AuthController@login');

Route::match(['get', 'post'], '/nextWeek', 'AppController@nextWeek');
Route::match(['get', 'post'], '/previousWeek', 'AppController@previousWeek');