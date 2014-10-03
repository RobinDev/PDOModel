<?php

namespace rOpenDev\PDOModel;
use \PDO;

class PDOModel Extends PDO {

	public $rows = '';
	public $tableInRows = array();

	protected $config = array(
		'dsn'=>'mysql',
		'user'=>userMysql,
		'password'=>passwordMysql,
		'host'=>'localhost',
		//'port'=>3307,
		'databasePath'=>'data/',
		'alwaysCreateTable'=> true,
	);

	private static $instance = null;

	protected $colParams = array(
		'mysql' => array(
			'NULL',
			'NOT NULL',
			'AUTO_INCREMENT',
			'PRIMARY KEY',
			'KEY',
			'UNIQUE',
			// references
			// default
		),
		'sqlite'=> array(
			'NULL',
			'NOT NULL',
			'UNIQUE',
			'PRIMARY KEY',
			// check
			// default

		)
	);

	public $_returnQuery = false;

	protected $prefix = '';

	 public static function instance($dbname, $checkIfDatabaseExist=true) {
		 $cls = get_called_class();
		 $intance_name = $dbname.$cls;
		if(!isset(self::$instance[$intance_name]))
			self::$instance[$intance_name] = new $cls($dbname, $checkIfDatabaseExist);
		return self::$instance[$intance_name];
	}

	public static function close_instance($dbname) {
		$cls = get_called_class();
		$intance_name = $dbname.$cls;
		self::$instance[$intance_name] = null;
		unset(self::$instance[$intance_name]);
	}

	function __construct($dbname = 'default', $checkIfDatabaseExist=true) {

		// Connexion to Mysql host or Sqlite File
		switch($this->config['dsn']) {
			case 'sqlite' : $dsn = $this->config['databasePath'].$dbname; break;
			case 'mysql'  : $dsn = 'mysql:host='.$this->config['host'].(isset($this->config['port']) ? ';port='.$this->config['port'] : ''); break;
		}

		parent::__construct($dsn, $this->config['user'], $this->config['password'],
			array(
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
			)
		);

		$this->dbname = $dbname;

		if($checkIfDatabaseExist) {
			$this->isDatabaseOk($dbname);
		}

	}

	/**
	 * Check if the database exist else create it.
	 *
	 */
	protected function isDatabaseOk($dbname) {

		switch($this->config['dsn']) {

			case 'sqlite' :
				if(empty($this->query('SELECT name FROM sqlite_master')->fetchAll())) {
					$this->createTable();
				}
				break;

			case 'mysql' :
				if($this->query('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME =  '.PDO::quote($dbname).';')->fetch(PDO::FETCH_NUM) === false) {
					$this->exec('CREATE DATABASE  `'.$dbname.'`; USE  `'.$dbname.'`');
					return $this->createTable();
				} else {
					$this->exec('USE `'.$dbname.'`');
				}
				break;
		}

		if($this->config['alwaysCreateTable'])
			$this->createTable();
	}

	static function normalizeType($type) {
		switch($type) {
			case 'slugify' : 	return 'varchar';
			case 'bool' : 		return 'bit';
			default :			return $type;
		}
	}

	function createTable() {
		$table = $this->table;
		if(!empty($table)) {
			foreach($table as $t => $c) {
				$cTable = 'CREATE TABLE IF NOT EXISTS `'.$this->prefix.$t.'` ('.CHR(10);
				foreach($c as $k => $v) {

					if($k[0] == '#') {
						if($k == '#INDEX#') {
							$cTable .= 'INDEX('.$v.')';
						}
						elseif(substr($k, 0, 3) == '#fk') {
							foreach($v as $kk => $vv) {
								$cTable .= self::createForeignKey($kk, $vv);
							}
						}
						elseif($k=='#pk') {
							$cTable .= self::createPrimaryKey($v);
						}
						$cTable .= ','.CHR(10);
					}
					elseif(isset($v['type'])) {
						$cTable .= '`'.$k.'` '.strtoupper(self::normalizeType($v['type'])).(isset($v['lenght']) ? '('.$v['lenght'].')' : '');
						$sV=$v; unset($v['type'], $v['lenght']);
						foreach($v as $vk => $vv) {
							if($vk === 'default') {
								$cTable .= ' DEFAULT '.$this->formatValue($vv, $sV);
							} elseif($vk === 'references') {
								$cTable .= ' REFERENCES '.$vv;
							}
							else {
								$cTable .= ' '.strtoupper($vv);
							}
						}
						$cTable .= ','.CHR(10);
					}

				}
				$cTable = trim($cTable, ','.CHR(10)).')'.(isset($table['#engine']) ? ' ENGINE='.$table['#engine'] : '').' DEFAULT CHARSET=utf8;';
				//dbv($cTable, false);
				$this->exec($cTable);
				if($this->errorInfo()[0] != '00000') {
					exit(str_replace(array(';'.chr(10), ';'), ';'.chr(10).$this->errorInfo()[0].' - '.$this->errorInfo()[2].chr(10), $cTable));
				}
			}
		}
	}

	static function createForeignKey($refTable, $params) {
		// FOREIGN KEY [id] (index_col_name, ...)
		//	REFERENCES tbl_name (index_col_name, ...)
		// 	[ON DELETE {CASCADE | SET NULL | NO ACTION | RESTRICT}]
		//	[ON UPDATE {CASCADE | SET NULL | NO ACTION | RESTRICT}]
		return 'FOREIGN KEY ('.implode(',',$params[0]).') REFERENCES '.$refTable.'('.implode(',',$params[1]).') '.strtoupper($params[2]);
	}

	static function createPrimaryKey($columns) {
		// PRIMARY KEY [index_type] (index_col_name,...)
		return 'PRIMARY KEY ('.implode(',',$columns).')';
	}

	/**
	 * Compatibility with the MySQL/MariaDB STRICT_TRANS_TABLES sql-mode
	 * // case 'varchar' : case 'char' :  case 'text' : case 'tinytext' : case 'mediumtext' : case 'longtext' : case 'binary' : case 'varbinary' : case 'tinyblob' : case 'blob' : case 'mediumblob' : case 'longblob' : case 'timestamp' :
	 */
	function formatValue($var, $params) {
		if(isset($params['type'])) {
			if($var === '') {
				if(in_array('auto_increment', $params)) return 'null';
				if(isset($params['default'])) return $params['default'] === '' ? '""' : $this->formatValue($params['default'], $params);
			}
			elseif($var === null) {
				if(in_array('not null', $params)) return "''";
				if(isset($params['default'])) return $params['default'] === '' ? '""' : $this->formatValue($params['default'], $params);
				return 'null';
			}
			switch($params['type']) { //case 'timestamp' :
				case 'bool' : 		$var = $var ? 1 : 0; 										break;
				case 'datetime' : 	$var = '"'.date('Y-m-d H:i:s', strtotime($var)).'"'; 	break;
				case 'date' :		$var = '"'.date('Y-m-d', strtotime($var)).'"'; 			break;
				case 'integer' : case 'int' : case 'tinyint' : case 'smallint' : case 'bigint' :
					$var = (int) $var;
					break;
				case 'float' : case 'decimal' :  case 'double' : case 'real' : case 'double precision' :
					$var = floatval($var);
					break;
				case 'slugify' : 	$var = '"'.self::slugify($var).'"'; 					break;
				case 'binary' : 	$var = 'x\''.bin2hex($var).'\''; 						break;
				default :
					// Découpe la variable si elle est plus longue que prévue dans le schéma de la table
					if(isset($params['lenght']) && strlen($var)>$params['lenght']) {
						$var = substr($var, 0, $params['lenght']);
					}
					$var = PDO::QUOTE($var);
				break;
			}
		}
		return $var;
	}

	function formatUpdateSet($keys, $data) {
		$str = '';
		foreach($keys as $k => $v) {
			// Garde les colonnes && les colonnes où il y a une donnée à mettre à jour
			if(strpos($k, '#') === false && isset($data[$k])) {
				$str .= '`'.$k.'` = '.$this->formatValue($data[$k], $v).',';
			}
		}
		return rtrim($str, ',');
	}

	function formatInsertValues($keys, $data) {
		$str = '';
		foreach($keys as $k => $v) {
			if(strpos($k, '#') === false) {
				$str .= $this->formatValue( (isset($data[$k]) ? $data[$k] : null) , $v).',';
			}
		}
		return rtrim($str, ',');
	}


	function formatIn($arr, $params) {
		foreach($arr as $a)
			$str = $this->formatValue($a, $params).',';
		return '('.rtrim(',').')';
	}

	/**
	 * Persiter une ligne
	 *
	 * @param string $table La table dans laquelle les données seront persistées
	 * @param array $data   Les données
	 *
	 * @return integer Nombre de lignes modifiées
	 */
	function edit($table, $data) {
		if(isset($this->primaryKey[$table])) {
			$pKey = $this->primaryKey[$table];
			if(
				(in_array('auto_increment', $this->table[$table][$pKey]) && isset($data[$pKey]))
				 || (!in_array('auto_increment', $this->table[$table][$pKey]) && $this->rowExist($table, $pKey, $data[$pKey]))
			  ) {
				return $this->update($table, $data, $pKey.' = '.PDO::QUOTE($data[$pKey]));
			}
		}

		return $this->insert($table, $data, 0);


	}

	/*
	 *
	 * @param string $table
	 * @param array  $data
	 * @param int    $replace 0:Insert, 1:Replace, 2:Insert Ignore
	 */
	function insert($table, $data, $replace = 0) {
		$query = ($replace===1?'REPLACE':'INSERT').($replace===2?' IGNORE':'').' INTO `'.$this->prefix.$table.'` VALUES('.$this->formatInsertValues($this->table[$table], $data).')';
		//echo $query; echo chr(10);
		return $this->_returnQuery ? $query : $this->exec($query);
	}

	function update($table, $data, $where) {
		$query = 'UPDATE `'.$this->prefix.$table.'` SET '.$this->formatUpdateSet($this->table[$table], $data).' WHERE '.$where;
		//echo $query;
		return $this->_returnQuery ? $query : $this->exec($query);
	}

	function rowExist($table, $key, $value) {
		$res = $this->query('SELECT COUNT(*) FROM `'.$this->prefix.$table.'` WHERE '.$key.' =  '.PDO::QUOTE($value).'');
		if ($res && $res->fetchColumn() > 0) {
			return true;
		}
		return false;
	}

	/**
	 * Prépare dans la variable $this->rows une série de requête
	 *
	 * @param string $table
	 * @param array  $data
	 * @param int    $replace
	 */
	function massEdit($table, $data, $replace=0) {//$tableName = 'index', $forceInsert = false, $whereUpdate = '') {
		$this->_returnQuery = true;
		if($replace>0)
			$this->rows .= $this->insert($table, $data, $replace).';'.CHR(10);
		else
			$this->rows .= $this->edit($table, $data).';'.CHR(10);
		$this->tableInRows[$table] = 1;
		$this->_returnQuery = false;
	}

	/**
	 * Prépare dans la valriable $this->rows, une requête insérant plusieurs lignes
	 *
	 * @param string $table
	 * @param array  $datas   [!]
	 * @param int    $replace
	 */
	function massInsert($table, $data, $replace) {

		$query = ($replace===1?'REPLACE':'INSERT').($replace===2?' IGNORE':'').' INTO `'.$this->prefix.$table.'` VALUES';

		foreach($data as $d) {
			$query .= '('.$this->formatInsertValues($this->table[$table], $d).'),';
		}

		$this->tableInRows[$table] = 1;
		$this->rows .= trim($query, ',').';';
	}

	/**
	 * Execute sql queries prepared in $this->rows
	 *
	 * @param bool   $lock Lock the table requested
	 * @param bool   $disabledSQLChecked Disabled unique check and foreign check
	 *
	 * @return integer Nombre de lignes modifiées
	 */
	function execRows($lock = false, $disabledSQLChecked=false) {
		if(empty($this->rows))
			return 0;

		$query = $disabledSQLChecked ? 'SET @@session.unique_checks = 0; SET @@session.foreign_key_checks = 0;':'';
		$query .= 'BEGIN;';
		$query .= $lock ? 'LOCK TABLES '.implode(',',array_keys($this->tableInRows[$table])).';'.chr(10) : '';
		$query .= $this->rows;
		$query .= $lock ? 'UNLOCK TABLES;'.chr(10) : '';
		$query .= 'COMMIT;';
		$query = $disabledSQLChecked ? 'SET @@session.unique_checks = 1; SET @@session.foreign_key_checks = 1;':'';
		$this->rows = '';
		return $this->exec($query);
	}

	// Benchmark the two way (prev & current) : exec vs prepare,execute
	function execMassEdit($lockDB=false) {
		$this->rows = trim($this->rows);
		if(!empty($this->rows)) {
			if($lockDB !== false) $this->exec('LOCK TABLES '.$lockDB.';');
			$s = $this->exec($this->rows);
			//$s = $this->prepare($this->rows);
			//$s->execute();
			//$s->closeCursor();
			if($lockDB !== false) $this->exec('UNLOCK TABLES;');
			if($this->errorInfo()[0] != '00000') {
				dbv($this->rows, false);
				dbv('function execMassEdit', false);
				dbv($this->errorInfo());
			}
			$this->rows = '';
			return $s; //return $s->rowCount();
		}
	}

	function select($table, $columns='*', $whereOrderLimit='') {
		$q = 'SELECT '.$columns.' FROM `'.$this->prefix.$table. '` ' .$whereOrderLimit;
		if($this->_returnQuery) {
			return $q;
		} else {
			$r = $this->query($q);
			return $r ? $r->fetchAll() : array();
		}
	}

	function countRow($table = 'index', $where) {
		return (int) $this->queryOne('SELECT COUNT(1) FROM `'.$this->prefix.$table. '` ' .$where);
	}

	function queryOne($query) {
		$query = $this->query($query);
		if(is_object($query)) {
			$result = $query->fetch();
			if($result !== false && count($result) == 1) // Si par exemple query = 'SELECT tagada FROM pourquoi_pas' renvoie string (tagada)
				return array_shift($result);
			elseif($result !== false)
				return $result;
		}
	}

	/***** DEPRECATED ******/
	function optimize() {
		$dbs = $this->query('SELECT TABLE_SCHEMA, TABLE_NAME FROM information_schema.TABLES  WHERE TABLE_TYPE=\'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);
		foreach($dbs as $t) {
				$r = $this->query('OPTIMIZE TABLE `'.$t[0].'`.`'.$t[1].'`')->fetch() or die($t[1]);
				$this->query('ALTER TABLE `'.$t[0].'`.`'.$t[1].'`');
				echo $t[0].'.'.$t[1].' : '.$r['Msg_text'].'<br>';
			}
	}

	/***** DEPRECATED ******/
	function repair($debug = true) {
		$toAlter = array();
		foreach($this->table as $t => $v) {
			$it = $t;
			//$t = (isset($this->prefix) && !empty($this->prefix) ? $this->prefix : '').$t;
			$check = $this->query('CHECK TABLE `'.$t.'`')->fetchAll();
			if($debug) echo 'CHECK TABLE `'.$t.'` ==> '.$check[0]['Msg_text'].CHR(10);
			/**/
			$dbSize = (int) $this->query('SELECT round(((data_length + index_length) / 1024 / 1024), 0) FROM information_schema.TABLES WHERE table_schema = '.PDO::quote($this->dbname).' AND table_name = '.PDO::QUOTE($t))->fetchColumn();
			if($dbSize<400) { /// TMP . PUT 1000
				$sql = 'ALTER TABLE `'.$t.'` ENGINE = InnoDB';
				$check = $this->exec($sql);
				if($debug) echo $sql.CHR(10);
			} elseif(strpos(serialize($v), 'primary key') !== false) {
				$cmd = 'pt-online-schema-change --host "localhost"  --user "'.$this->user.'"  --password "'.$this->password.'" --alter "ENGINE=InnoDB" --execute D='.$this->dbname.',t='.$t;
				exec("sh -c \" $cmd 2>&1 \"");
				if($debug) echo $cmd.CHR(10);
			} /**
			else {
				$this->createTable(array($it.'_new'=>$v));
				$this->exec('LOCK TABLES `'.$t.'` WRITE, `'.$t.'_new` WRITE;');
				while(1<2) {
					$q = $this->query('SELECT * FROM `'.$t.'` LIMIT 0, 1000;');
					if(!$q) break;
					$q = $q->fetchAll();
					if(empty($q)) break;
					$this->massInsert($q, $it, true);
					//$this->rows = str_replace('`'.$t.'`', '`'.$t.'_new`', $this->rows);
					$this->rows = substr_replace($this->rows, '`'.$t.'_new`', strpos($this->rows, '`'.$t.'`'), strlen('`'.$t.'`'));
					foreach($q as $r)
						$this->rows .= 'DELETE FROM `'.$t.'` WHERE '.$this->formatUpdateSet($v, $r, ' AND ').' LIMIT 1;'.CHR(10);
					//dbv($this->rows, false);
					$this->executeMassEdit();
					$this->exec('FLUSH TABLE `'.$t.'`');
					$this->exec('FLUSH TABLE `'.$t.'_new`');
				}
				$this->execError('DROP TABLE `'.$t.'`');
				$this->execError('RENAME TABLE `'.$this->dbname.'`.`'.$t.'_new` TO `'.$this->dbname.'`.`'.$t.'` ;');
				if($debug) echo 'ALTER Line Per Line '.$t.'  ==> OK'.CHR(10);
			} /**/
		}

	}

	static function slugify($str, $authorized = '[^a-z0-9/\.]') {
		$str = str_replace(array('\'', '"'),'',$str);
		if($str !== mb_convert_encoding(mb_convert_encoding($str,'UTF-32','UTF-8'),'UTF-8','UTF-32'))
			$str = mb_convert_encoding($str,'UTF-8');
		$str = htmlentities($str,ENT_NOQUOTES,'UTF-8');
		$str = preg_replace('`&([a-z]{1,2})(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig);`i','$1',$str);
		$str = preg_replace(array('`'.$authorized.'`i','`[-]+`'),'-',$str);
		return strtolower(trim($str,'-'));
	}

	static function truncate($string, $max_length = 90, $replacement = '...', $trunc_at_space = false) {
		$max_length -= strlen($replacement);
		$string_length = strlen($string);
		if($string_length <= $max_length)
			return $string;
		if( $trunc_at_space && ($space_position = strrpos($string, ' ', $max_length-$string_length)) )
			$max_length = $space_position;
		return substr_replace($string, $replacement, $max_length);
	}
}
