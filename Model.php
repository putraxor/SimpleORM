<?php

class Model {

  public $qc = '/*MYSQLND_QC_ENABLE_SWITCH*/';
  public $pdo;
  public $pk;
  public $tableName;
  public $fields;
  public $data = [];
  public $has;
  public $belong_to;
  public $debug_data = [];
  public $debug = false;

  /**
   * Model Constructor
   * @param type $tableName name of table
   * @param type $pk primary key field
   * @param type $fields array of field to display
   */
  public function __construct($tableName = '', $pk = '', $fields = []) {
    $this->tableName = $tableName;
    $this->fields = $fields;
    $this->pk = $pk;
  }

  public function createInstance() {
    return $this;
  }

  public function setDSN($option) {
    if ($option) {
      $this->pdo = new PDO("mysql:host={$option["host"]};dbname={$option["db"]};charset=utf8", $option["user"], $option["pass"], array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    }
  }

  /**
   * Reset query before ...... whatever :D
   */
  protected function resetQuery() {
    $this->withParameter = [];
    $this->withCondition = '';
    $this->withOrder = '';
    $this->withLimit = '';
    $this->withGroup = '';
  }

  /**
   * Create Query select builder
   * @param type $query
   */
  public function select($query = null) {
    $this->resetQuery();
    if ($query) {
      $this->query = $query;
    } else {
      $this->query = "SELECT " . join(', ', $this->fields) . " FROM {$this->tableName}";
    }
    return $this;
  }

  /**
   * Add condition to select builder
   * @param type $conditions
   * @return \Model
   */
  public function where($conditions = null) {
    $this->withCondition.=" $conditions ";
    return $this;
  }

  /**
   * Add 'order by' to select builder
   * @param type $order
   * @return \Model
   */
  public function orderBy($order) {
    if (!$order) {
      $order = $this->pk;
    }
    $this->withOrder = "ORDER BY $order";
    return $this;
  }

  /**
   * Set limit of select builder
   * @param type $from
   * @param type $to
   * @return \Model
   */
  public function limit($from = 0, $to = 0) {
    $this->withLimit = "LIMIT $from, $to";
    return $this;
  }

  /**
   * Add group of select data
   * @param type $group
   * @return \Model
   */
  public function groupBy($group) {
    $this->withGroup = "GROUP BY $group";
    return $this;
  }

  /**
   * Add parameter of SELECT statement
   * @param type $param
   */
  public function params($param) {
    $this->withParameter = $param;
    return $this;
  }

  protected function getParamType($paramValue) {
    $paramType = gettype($paramValue);
    $paramTypeArray = [
        'string' => 's',
        'integer' => 'i',
        'double' => 'd',
        'blob' => 'b'
    ];
    return $paramTypeArray[$paramType];
  }

  /**
   * Fetch data (SELECT statement)
   * @is_include_child type $is_include_child wether fetch child data or not, default: true
   */
  public function fetch($fetchChildren = true) {
    if ($this->withCondition) {
      $this->withCondition = "WHERE " . $this->withCondition;
    }
    $preparedQuery = "{$this->qc}{$this->query} $this->withCondition $this->withGroup $this->withOrder $this->withLimit";

    if ($this->debug) {
      $this->debug_data['last_query'] = $this->getPreparedQueryString($preparedQuery, $this->withParameter);
      $this->debug_data['condition'] = $this->withCondition;
      $this->debug_data['parameter'] = $this->withParameter;
    }
    try {
      $stmt = $this->pdo->prepare($preparedQuery);
      $stmt->execute($this->withParameter);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $this->data = $rows;
      if ($this->has && $fetchChildren) {
        $this->getChildrenData();
      }
      return $this->data;
    } catch (PDOException $exc) {
      echo "\nWith Parameter" . join(',', $this->withParameter) . "\n";
      echo "\n$preparedQuery\n";
      echo $exc;
    }
  }

//end fetch

  /**
   * Get all children data if table has child relationships
   */
  public function getChildrenData() {
    for ($i = 0; $i < count($this->data); $i++) {
      $parent_pk_val = $this->data[$i][$this->pk];
      foreach ($this->has as $child) {
        $fk_field = $child->fk[$this->tableName];
        $child->select()
            ->where("$fk_field = ? ")
            ->params([$parent_pk_val])
            ->fetch();
        $this->data[$i][$child->tableName] = $child->data;
      }
    }
  }

  public function fetchBelongingData() {
    for ($i = 0; $i < count($this->data); $i++) {
      foreach ($this->belong_to as $parent) {
        $pk_parent = $parent->pk;
        $this_fk_name = $this->fk[$parent->tableName];
        $this_fk_val = $this->data[$i][$this_fk_name];
        $parent->select()
            ->where("$pk_parent = ? ")
            ->params([$this_fk_val])
            ->fetch(false);
        $this->data[$i][$parent->tableName] = $parent->data[0];
      }
    }
  }

  /**
   * Save data to database
   * @param type $data
   */
  public function save($data = []) {
    $this->resetQuery();
    $field_names = [];
    $field_qmarks = [];
    $field_types = [];
    $field_value = [];
    $on_duplicate_key_update = [];
    $update_types = [];
    $update_value = [];
    foreach ($data as $key => $value) {
      array_push($field_names, $key);
      array_push($field_qmarks, '?');
      array_push($field_types, gettype($value));
      array_push($field_value, $value);
      if ($key != $this->pk) {
        array_push($on_duplicate_key_update, "$key=?");
        array_push($update_types, gettype($value));
        array_push($update_value, $value);
      }
    }
    $preparedQuery = "{$this->qc}INSERT INTO $this->tableName (" . join(',', $field_names) . ") "
        . "VALUES(" . join(',', $field_qmarks) . ") ON DUPLICATE KEY UPDATE "
        . join(',', $on_duplicate_key_update);
    $stmt = $this->pdo->prepare($preparedQuery);
    $param_value = array_merge($field_value, $update_value);
    $stmt->execute($param_value);
    $affected_rows = $stmt->rowCount();

    if ($this->debug) {
      $this->debug_data['last_query'] = $this->getPreparedQueryString($preparedQuery, $param_value);
      $this->debug_data['condition'] = $this->withCondition;
      $this->debug_data['parameter'] = $param_value;
    }

    return ['status' => 'success', 'affected_rows' => $affected_rows, 'form_data' => $data, 'debug_data' => $this->debug_data];
  }

  /**
   * Delete data from database
   * Warning: if 'where' condition not specified, all data will remove
   * @return type
   */
  public function delete() {
    if ($this->withCondition) {
      $this->withCondition = "WHERE " . $this->withCondition;
    }
    $preparedQuery = "{$this->qc}DELETE FROM $this->tableName $this->withCondition";

    if ($this->debug) {
      $this->debug_data['last_query'] = $this->getPreparedQueryString($preparedQuery, $this->withParameter);
      $this->debug_data['condition'] = $this->withCondition;
      $this->debug_data['parameter'] = $this->withParameter;
    }

    $stmt = $this->pdo->prepare($preparedQuery);
    $stmt->execute($this->withParameter);
    $affected_rows = $stmt->rowCount();
    return ['status' => 'success', 'affected_rows' => $affected_rows, 'debug_data' => $this->debug_data];
  }

  /**
   * Check if data with specified condition ('where') is exist
   * @return type
   */
  public function exist() {
    if ($this->withCondition) {
      $this->withCondition = "WHERE " . $this->withCondition;
    }
    $preparedQuery = "{$this->qc}SELECT COUNT(*) AS count, " . join(',', $this->fields) . " FROM $this->tableName $this->withCondition";
    //echo $preparedQuery;
    $stmt = $this->pdo->prepare($preparedQuery);
    $stmt->execute($this->withParameter);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = $rows[0]['count'];
    $exist = $count > 0;
    if ($this->debug) {
      $this->debug_data['last_query'] = $this->getPreparedQueryString($preparedQuery, $this->withParameter);
      $this->debug_data['condition'] = $this->withCondition;
      $this->debug_data['parameter'] = $this->withParameter;
    }
    $result = ['status' => 'success', 'exist' => $exist, 'count' => $count, 'data' => $rows[0], 'debug_data' => $this->debug_data];
    return $result;
  }

  /**
   * This function returns prepared query with what query exactly 
   * executed by PDO.
   * This function for debugin purpose only
   * @param type $string
   * @param type $data
   * @return type
   */
  public function getPreparedQueryString($string, $data) {
    $indexed = $data == array_values($data);
    foreach ($data as $k => $v) {
      if (is_string($v))
        $v = "'$v'";
      if ($indexed)
        $string = preg_replace('/\?/', $v, $string, 1);
      else
        $string = str_replace(":$k", $v, $string);
    }
    return $string;
  }

  /**
   * Display class Model in Array
   * @ignore obsolete
   */
  public function describe() {
    $var = get_object_vars($this);
    foreach ($var as &$value) {
      if (is_object($value) && method_exists($value, 'getJsonData')) {
        $value = $value->getJsonData();
      }
    }
    return $var;
  }

}
