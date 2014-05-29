<?php
/**
 * mysql option class file
 *
 * Copyright (c) 2011, Uoojee Corp. 
 * All rights reserved.
 *
 * PHP version 5
 *
 * @category CORE
 * @package  CORE
 * @author   Jason <bieru52@aliyun.com>
 * @version  1.0  
 * @license  Software Distribution
 * @link     http://dreameng.blog.51cto.com/
 */
class mysqlClass {
	private $host;  //host
	private $user;  //db user
	private $pwd;   //db password
	private $db;    //db name
	private $charset;  //set db char code
	private $db_connect_type;  //db connenct status
	private $is_show_error = false; //is show error
	private $is_show_run_tim = false;  //is show sql run time

	private $connection;
	//排序方式
	private $sortArr = array(
		'ASC', 'DESC' 
	);
			 
	function __construct($host, $user, $pwd, $db, $charset='utf8', $db_connect_type='npc') {
		$this -> host = $host;
		$this -> user = $user;
		$this -> pwd  = $pwd;
		$this -> db   = $db;
		$this -> charset = empty($charset) ? 'utf8' : $charset;
		$this -> db_connect_type = empty($db_connect_type) ? 'npc' : $db_connect_type; 
	}
	/**
	 * connect db and set db char code
	 * 
	 * @return void
	 */
	public function connect() {
		if ($this -> db_connect_type == 'pconnect') {
			$this -> connection = mysql_pconnect($this -> host, $this -> user, $this -> pwd);
		} else {
			$this -> connection = mysql_connect($this -> host, $this -> user, $this -> pwd);
		}

		if (!mysql_select_db($this->db, $this->connection)) {
			if ($this -> is_show_error) {
				$this -> showError("the database connect is wrong", $this->db);
				mysql_close($this->connection);	
			}

		}

		mysql_query("set names ".$this->charset);
	}
	/**
	 * insert data to database
	 * 
	 * @param string  $table  table's name
	 * @param array   $values insert data forexample：array('name'=>'zhl', 'age'=>'25');
	 * @param boolean $bool   will get boolean or id default is false ,if boole=true will get Id
	 * 
	 * @return mixed (boolean | int)
	 */
	public function insert($table, $values, $bool = false) {
		if (empty($table) || !is_array($values) || empty($values) ) {
			return false;
		}

		$tmpFields = array();
		$tmpValues = array();

		foreach ($values as $field => $val) {
			$tmpFields[] = '`'. htmlspecialchars($field).'`';
			$tmpValues[] = $this->_sqlInjectionAttack($val);
		}

		$strField = implode(', ', $tmpFields);
		$strValue = implode(', ', $tmpValues);

		$sql = sprintf(
			'INSERT INTO `%s` (%s) VALUES (%s)',
				$this->_sqlInjectionAttack($table, true),
				$strField,
				$strValue
			);

		if ($bool) {
			$this->query($sql);
			return $this->getPrevId();
		} else {
			return $this->query($sql);
		}

	}
	/**
	 * get the data's option id
	 * 
	 * @return integer
	 */
	public function getPrevId() {
		return mysql_insert_id();
	}
	/**
	 * delete data 
	 * 
	 * @param string $table     the table name
	 * @param array  $condition the condition of delete function
	 * 
	 * @return boolean
	 */
	public function delete($table, $condition=array()) {
		if (empty($table)) {
			return false;
		}

		$condition = $this->_combinCond($condition);
		if (empty($condition)) {
			$sql = sprintf(
				'DELETE FROM `%s`',
				$this->_sqlInjectionAttack($table, true)
			);
		} else {
			$con = implode(' AND ', $condition);
			$sql = sprintf(
				'DELETE FROM `%s` WHERE %s',
				$this->_sqlInjectionAttack($table, true),
				$con
			);
		}

		return $this->query($sql);

	}
	/**
	 * update data
	 * 
	 * @param string $table     the table name
	 * @param array  $values    the new value forexample:array('name'=>'zhl')
	 * @param array  $condition update condition forexample:array('eq' => array('name'=>'gavin'));	 * 
	 * 
	 * @return boolean
	 */
	public function modify($table, $values, $condition=array()) {
		if (empty($table) || !is_array($values) || empty($values)) {
			return false;
		}
		$tmpArr = array();
		foreach ($values as $field => $val) {
			$tmpArr[] = '`'.$this->_sqlInjectionAttack($field, true).'` = '.$this->_sqlInjectionAttack($val);
		}

		$tmpStr = implode(', ', $tmpArr);
		$con = $this->_combinCond($condition);
		if (empty($con)) {
			$sql = sprintf(
				'UPDATE `%s` SET %s',
				$this->_sqlInjectionAttack($table, true),
				$tmpStr
				);
		} else {
			$cond = implode(', ', $con);
			$sql = sprintf(
				'UPDATE `%s` SET %s WHERE %s',
				$this->_sqlInjectionAttack($table, true),
				$tmpStr,
				$cond
				);
		}

		return $this->query($sql);
	}
	/**
	 * get data 
	 * 
	 * @param  string  $table     the table name 
	 * @param  array   $fields    to get the data fields  forexample:array('title', 'content');
	 * @param  array   $condition it is condition of will get data  forexample:array('eq'=>array('group_id'=>3));
	 * @param  string  $order     sort field
	 * @param  string  $sort      sort of way
	 * @param  integer $limit     the number of the number of access to data
	 * @param  boolean $bool      is to get the data correlation subscript or Numbers
	 * 
	 * @return array
	 */
	public function find($table, $fields = array(), $condition=array(), $order='id', $sort='DESC', $limit=0, $bool=false) {
		$datas = array();
		$sql = $this->_combinSql($table, $fields, $condition, $order, $sort, $limit);
		$result = $this->query($sql);
		if ($bool) {
			while ($row = mysql_fetch_assoc($result)) {
				$datas[] = $row;
			}
		} else {
			while ($row = mysql_fetch_row($result)) {
				$datas[] = $row;
			}
		}
		return $datas;
	}
	/**
	 * get one data
	 * 
	 * @param  string  $table     the table name
	 * @param  array   $fields    to get the data fields  forexample:array('title', 'content');
	 * @param  array   $condition it is condition of will get data forexample:array('eq'=>array('group_id'=>3));
	 * @param  boolean $bool      is to get the data correlation subscript or Numbers
	 * 
	 * @return array
	 */
	public function findOne($table, $fields=array(), $condition=array(), $bool=false) {
		$sql = $this->_combinSql($table, $fields, $condition);
		$result = $this->query($sql);
		if ($bool) {
			$datas = mysql_fetch_assoc($result);
		} else {
			$datas = mysql_fetch_row($result);
		}

		return $datas;
	}
	/**
	 * The number of access to data
	 * 
	 * @param  [type] $table     the table name
	 * @param  array  $fields    to get the data fields，array('id', 'name')
	 * @param  array  $condition it is condition of will get data
	 * @param  string $groupby   group field
	 * 
	 * @return array
	 * 
	 * SELECT COUNT(*) FROM tablename is the optioimal choice；
	 * SELECT COUNT(*) FROM tablename WHERE COL = 'value' as far as possible to reduce use it；
	 * SELECT COUNT(COL) FROM tablename WHERE COL2 = 'value' don't use it。
	 */
	public function count($table, $fields=array(), $condition=array(), $groupby='') {
		if (empty($table)) {
			return array();
		}

		$strFields = '';
		if (!empty($fields)) {
			foreach ($fields as $field) {
				$tmpArrs[] = '`'.$this->_sqlInjectionAttack($field, true).'`';
			}
			$strFields = implode(', ', $tmpArrs);
		}
		$con = $this->_combinCond($condition);
		$sqlGroup = '';
		if (!empty($groupby)) {
			$sqlGroup = 'GROUP BY `'.$this->_sqlInjectionAttack($groupby, true).'`';
		}
		if (!empty($con)) {
			$cond = implode(', ', $con);
			if ($strFields) {
				$sql = sprintf(
					'SELECT %s, COUNT(*) FROM %s WHERE %s %s',
					$strFields,
					$this->_sqlInjectionAttack($table, true),
					$cond,
					$sqlGroup
				);
			} else {
				$sql = sprintf(
					'SELECT COUNT(*) FROM %s WHERE %s %s',
					$this->_sqlInjectionAttack($table, true),
					$cond,
					$sqlGroup
				);
			}
		} else {
			if ($strFields) {
				$sql = sprintf(
					'SELECT %s, COUNT(*) FROM `%s` %s',
					$strFields,
					$this->_sqlInjectionAttack($table, true),
					$sqlGroup
				);
			} else {
				$sql = sprintf(
					'SELECT COUNT(*) FROM `%s` %s',
					$this->_sqlInjectionAttack($table, true),
					$sqlGroup
				);
			}
		}

		$result = $this->query($sql);
		return mysql_fetch_row($result);
	}
	/**
	 * the sql
	 * 
	 * @param string $sql the sql
	 * 
	 * @return mixed
	 */
	public function query($sql) {
		if ($this->is_show_run_tim) {
			$starTime = microtime(true);
			$result = mysql_query($sql, $this->connection);
			$endTime = microtime(true);
			echo "<br />SQL:$sql<br />start time：$starTime    end time:endTime<br />spent time：".$starTime-$endTime." MS<br />";
		} else {
			$result = mysql_query($sql, $this->connection);
		}

		if (!$result) {
			if ($this->is_show_error) {
				$this->showError("SQL：".$sql);
			}
			return false;
		} else {
			return $result;
		}
	}

	public function showError($mess='', $sql='') {
		echo mysql_error();
		die;
	}
	/**
	 * close database connect and free memory
	 * 
	 * @return void
	 */
	public function __destruct() {
		if (!empty($this->result)) {
			$this->mysql_free_result();
		}
		@mysql_close($this->connection);
	}
	/**
	 * Combined the condition of SQL statements
	 * 
	 * @param array $condition the condition of SQL
	 * 
	 * @return array
	 */
	private function _combinCond($condition) {
		$cond = array();
		if (!is_array($condition) || empty($condition)) {
			return $cond;
		} 

		foreach ($condition as $key => $vals) {
			if (!is_array($vals)) {
				continue;
			}
			$key = strtolower($key);
			foreach ($vals as $field => $val) {
				if ($key == 'eq') {
					$cond[] = "`".$this->_sqlInjectionAttack($field, true) .'` = '.$this->_sqlInjectionAttack($val); 
				} else if ($key == 'like') {
					$cond[] = "`".$this->_sqlInjectionAttack($field, true) .'` like \'%'.$this->_sqlInjectionAttack($val, true).'%\''; 
				} else if ($key == 'in') {
					if (is_array($val) && !empty($val)) {
						$tmpVal = array();
						foreach ($val as $v) {
							$tmpVal[] = $this->_sqlInjectionAttack($v, true);
						}
						$tmpStrVal = implode(',', $tmpVal);
						$cond[] = "`".$this->_sqlInjectionAttack($field, true).'` in ('.$tmpStrVal.')';
					}
				} else if ($key == '>' || $key == 'gt') {
					$cond[] = "`".$this->_sqlInjectionAttack($field, true) .'` >'. $this->_sqlInjectionAttack($val);
				} else if ($key == '>=' || $key == 'gte') {
					$cond[] = "`".$this->_sqlInjectionAttack($field, true) .'` >='. $this->_sqlInjectionAttack($val);
				} else if ($key == '<' || $key == 'lt') {
					$cond[] = "`".$this->_sqlInjectionAttack($field, true) .'` < '. $this->_sqlInjectionAttack($val);
				} else if ($key == '<=' || $key == 'lte') {
					$cond[] = "`".$this->_sqlInjectionAttack($field, true) .'` <= '. $this->_sqlInjectionAttack($val);
				} else if ($key == '!=' || $key == 'neq') {
					$cond[] = "`".$this->_sqlInjectionAttack($field, true) .'` != '. $this->_sqlInjectionAttack($val);
				}
			}
		}

		return $cond;
	}
	/**
	 * combined the sql
	 * 
	 * @param  string  $table     the table name 
	 * @param  array   $fields    to get the data fields  forexample:array('title', 'content');
	 * @param  array   $condition it is condition of will get data  forexample:array('eq'=>array('group_id'=>3));
	 * @param  string  $order     sort field
	 * @param  string  $sort      sort of way
	 * @param  integer $limit     the number of the number of access to data
	 * 
	 * @return string|boolean
	 */


	private function _combinSql($table, $fields = array(), $condition=array(), $order='', $sort='', $limit=0) {
		if (empty($table)) {
			return false;
		}

		$getFields = " * ";
		if (!empty($fields)) {
			foreach ($fields as $field) {
				$tmpFields[] = '`'.$this->_sqlInjectionAttack($field, true).'`';
			}
			$getFields = implode(', ', $tmpFields);
		}

		$con = $this->_combinCond($condition);
		$sqlOrder = '';
		if (!empty($order) && !empty($sort) && in_array(strtoupper($sort), $this->sortArr)) {
			$sqlOrder = ' ORDER BY `'.htmlspecialchars($order).'` '.$sort;
		}

		$sqlLimit = '';
		$limit = (int)$limit;
		if ($limit) {
			$sqlLimit = ' LIMIT '.$limit;
		}
		if (empty($con)) {
			$sql = sprintf(
				'SELECT %s FROM `%s` %s %s',
				$getFields,
				$this->_sqlInjectionAttack($table, true),
				$sqlOrder,
				$sqlLimit
			);
		} else {
			$cond = implode(' AND ', $con);
			$sql = sprintf(
				'SELECT %s FROM `%s` WHERE %s %s %s',
				$getFields,
				$this->_sqlInjectionAttack($table, true),
				$cond,
				$sqlOrder,
				$sqlLimit
			);
		}
		return $sql;

	}
	/**
	 * To prevent SQL injection
	 * get_magic_quotes_gpc functions was abandoned in PHP5.4
	 * 
	 * @param string  $value get value's fields
	 * @param boolean $bool  Identify whether or not to use quotation marks
	 * 
	 * @return mixed
	 */
	private function _sqlInjectionAttack($value, $bool=false) {
		if (empty($value)) {
			return $value;
		}

		if (!is_numeric($value)) {
			if ($bool) {
				$value = mysql_real_escape_string($value);
			} else {
				$value = "'". mysql_real_escape_string($value) ."'";	
			}
		}

		return $value;
	}
}
?>
