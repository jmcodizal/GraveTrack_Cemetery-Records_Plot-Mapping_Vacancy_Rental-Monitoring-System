<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Login::index');
$routes->post('/login/auth', 'Login::auth');
$routes->get('/login', 'Login::index');
$routes->get('/login/logout', 'Login::logout');

$routes->get('/dashboard', 'Dashboard::index');

$routes->get('/burial-records', 'BurialRecords::index');

$routes->get('/vacancy', 'Vacancy::index');

$routes->get('/add-burial', 'AddBurial::index');
$routes->post('/add-burial/save', 'AddBurial::save');
$routes->get('/add-burial/getVacantPlots', 'AddBurial::getVacantPlots');
