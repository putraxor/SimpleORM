<?php

ob_start();
require_once 'Model.php';

class SimpleORM {

  public $pdo;

  public function __construct($host = '127.0.0.1', $user = 'root', $pass = '12345', $db = 'mysql') {
    $this->pdo = new PDO("mysql:host={$host};dbname={$db}", $user, $pass, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
  }

  /**
   * This initialize class model from defined 
   * database schema
   * @param type $schema
   */
  public function initSchema($schema) {
    $tables = $schema['tables'];
    $relationships_has = $schema['relationships']['has'];
    $relationships_belong = $schema['relationships']['belong_to'];
    //init table from schema
    foreach ($tables as $key => $value) {
      $table = $key;
      $pk = $value['pk'];
      $fields = $value['fields'];
      $fk = $value['fk'];

      $this->{$table} = new Model("$table", "$pk", $fields);
      $this->{$table}->pdo = $this->pdo;
      $this->{$table}->debug = false;
      foreach ($fk as $key => $value) {
        $this->{$table}->fk[$key] = $value;
      }
    }
    //init relationship from schema
    foreach ($relationships_has as $parent => $child) {
      $this->{$parent}->has = [];
      foreach ($child as $childClass) {
        $this->{$parent}->{$childClass} = $this->{$childClass};
        array_push($this->{$parent}->has, $this->{$childClass});
      }
    }
    foreach ($relationships_belong as $parent => $child) {
      $this->{$parent}->belong_to = [];
      foreach ($child as $childClass) {
        $this->{$parent}->{$childClass} = $this->{$childClass};
        array_push($this->{$parent}->belong_to, $this->{$childClass});
      }
    }
  }

}
