<?php

require_once 'vendor/autoload.php';
require_once 'vendor/SimpleORM/SimpleORM.php';

use \Slim\Middleware\HttpBasicAuthentication\MysqliAuthenticator;

//membuat dan mengkonfigurasi slim app
$host = '127.0.0.1';
$user = 'root';
$pass = '12345';
$db = 'family';


$app = new \Slim\app;

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "path" => "/admin",
    "realm" => "Protected",
    "error" => function ($request, $response, $arguments) {
      $data = [];
      $data["status"] = "error";
      $data["message"] = $arguments["message"];
      return $response->write(json_encode($data, JSON_UNESCAPED_SLASHES));
    },
        "authenticator" => new MysqliAuthenticator([
            "dbhost" => $host,
            "dbuser" => $user,
            "dbpass" => $pass,
            "db" => $db,
            "table" => "users",
            "user" => "username",
            "hash" => "hash_password"
            ])
    ]));

    $orm = new SimpleORM($host, $user, $pass, $db);

//mendefinisikan route app untuk home
    $app->get('/', function() {
      echo "Family REST API\n";
    });

    $app->post('/login', function() use($app) {
      $result = $app->authenticator->authenticate('admin', password_hash("12345", PASSWORD_DEFAULT));
      if ($result->isValid()) {
        $app->redirect('/');
      } else {
        $messages = $result->getMessages();
        $app->flashNow('error', $messages[0]);
      }
    });

    $app->get('/logout', function () use ($app) {
      print_r($app->authenticator);
      //$app->authenticator->logout();
      //$app->redirect('/');
    });

//get data father
    $app->get('/father', function($req, $res, $args) use($app, $orm) {
      $orm->father->select()->fetch();
      $orm->father->getChildrenData();
      $data = ['data' => $orm->father->data];
      return $res->withJson($data);
    });

    //get data father
    $app->get('/children', function($req, $res, $args) use($app, $orm) {
      $orm->children->select()->fetch();
      $data = ['data' => $orm->children->data];
      return $res->withJson($data);
    });

    //get data father
    $app->get('/user', function($req, $res, $args) use($app, $orm) {
      $orm->user->select()->fetch();
      $data = ['data' => $orm->user->data];
      return $res->withJson($data);
    });

//test data
    $app->get('/test', function($req, $res, $args) use($app, $orm) {
      $orm->test();
    });


//run App
    $app->run();
    