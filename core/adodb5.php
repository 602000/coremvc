<?php
/**
 * adodb5 for CoreMVC 1.4.0 alpha 1
 *
 * 定义(define)
 */
class adodb5 {
	
	/**
	 * 连接数据库
	 * @param array $args
	 * @return dbh
	 */
	public static function connect($args) {
		$dsn = $args ['connect_dsn'];
		$type = $args ['connect_type'];
		$server = $args ['connect_server'];
		$username = $args ['connect_username'];
		$password = $args ['connect_password'];
		$new_link = $args ['connect_new_link'];
		$dbname = $args ['connect_dbname'];
		$charset = $args ['connect_charset'];
		$client_flags = $args ['connect_client_flags'];
		$GLOBALS['ADODB_CACHE_CLASS'] =  'ADODB_Cache_File';
		$dbh = ADONewConnection( $dsn );
		if ($server !== '') {
			if ($client_flags !== '' && preg_match ( '/msyql/i', $dsn )) {
				$dbh->clientFlags = $client_flags;
			}
			if ($type === 'persist') {
				$dbh->PConnect ( $server, $username, $password, $dbname );
			} elseif ($new_link) {
				$dbh->NConnect ( $server, $username, $password, $dbname );
			} else {
				$dbh->Connect ( $server, $username, $password, $dbname );
			}
			if ($charset !== '' && preg_match ( '/msyql/i', $dsn )) {
				$dbh->Execute ( 'SET NAMES ' . $charset );
			}
		}
		return $dbh;
	}
	
	/**
	 * 重连数据库
	 * @param dbh $dbh
	 * @param array $args
	 * @return dbh
	 */
	public static function reconnect($dbh, $args) {
		return $dbh;
	}
	
	/**
	 * 断开数据库
	 * @param dbh $dbh
	 * @param array $args
	 * @return bool
	 */
	public static function disconnect($dbh, $args) {
		if(is_object ( $dbh ) && preg_match ( '/^ADODB/i', get_class($dbh))){
			$dbh->Close ();
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * 执行语句
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @param array &$ref
	 * @return rs
	 */
	public static function execute($dbh, $args, $class, $sql, $param = null, &$ref = null) {

		$ref_flag = (func_num_args () > 5);

		// 是否强制参数转SQL
		if (isset ($args ['sql_format']) && $args ['sql_format']) {
			$sql = self::prepare ($dbh, $args, $class, $sql, $param, true);
			$param = null;
		}

		if (is_array( $param )) {
			$result = $dbh->Execute ( $sql, $param );
		} else {
			$result = $dbh->Execute ( $sql );
		}

		// 数据库调试开关
		if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
			if ($result === false) {
				$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
			} else {
				$extra = null;
			}
		}

		if ($ref_flag) {
			if ($result === false) {
				$ref = array('insert_id' => '','affected_rows' => 0,'num_fields' => 0,'num_rows' => 0);
			} else {
				$ref = array();
				$ref ['insert_id'] = $dbh->Insert_ID();
				$ref ['affected_rows'] = (int)$dbh->Affected_Rows();
				$ref ['num_fields'] = is_object($result)?$result->FieldCount():0;
				$ref ['num_rows'] = is_object($result)?$result->RecordCount():0;
			}
		}

		// 数据库调试开关
		if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
			call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
		}

		return $result;
	}
	
	/**
	 * 准备SQL语句
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @param bool $format
	 * @param bool $debug
	 * @param string $output
	 * @param string $errno
	 * @param string $error
	 * @return mix
	 */
	public static function prepare($dbh, $args, $class, $sql, $param = null, $format = null, $debug = null, $output = null, $errno = null, $error = null) {
		return call_user_func ( array($class,'prepare'), 'mysql_' . $sql, $param, $format, $debug, $output, $errno, $error );
	}
	
	/**
	 * 自增序列
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $tablename
	 * @param int $start_index
	 * @return int
	 */
	public static function sequence($dbh, $args, $class, $tablename, $start_index) {
		$return = $dbh->GenID ( $tablename, $start_index );
		if($start_index>$return){
			$dbh->Execute('UPDATE '.$tablename.' SET id='.$start_index);
			$return = $start_index;
		}
		return $return;
	}
	/**
	 * 对象选择
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @param array $ref
	 * @return array
	 */
	public static function selects($dbh, $args, $class, $sql, $param, $ref) {
		$page = &$ref['page'];
		$class_arr = $ref['class_arr'];
		$classkey = $ref['classkey'];
		$classkey_arr = $ref['classkey_arr'];
		$classname = $ref['classname'];
		$calledclass = $ref['calledclass'];
		if($page !== null){
			if($page['count'] === null){
				$sql = preg_replace('/SELECT/i','SELECT SQL_CALC_FOUND_ROWS',$sql,1);
			}
			$limit = 'LIMIT '.($page['size']*($page['page']-1)).','.$page['size'];
			if(isset($page['limit'])){
				$sql = preg_replace('/(.*)'.$page['limit'].'/i','$1'.$limit,$sql,1);
			} else {
				$sql .= ' '.$limit;
			}
		}

		// 是否强制参数转SQL
		if (isset ($args ['sql_format']) && $args ['sql_format']) {
			$sql = self::prepare ($dbh, $args, $class, $sql, $param, true);
			$param = null;
		}

		$data_key = array ();
		foreach($class_arr as $value){
			if($value!==null && $value!=='' && !in_array($value,$data_key,true)){
				$data_key[] = $value;
			}
		}
		// 数据
		$data_all = array ();
		if($data_key!==array()){
			$mode = $dbh->setFetchMode(ADODB_FETCH_BOTH);
			$sth = $dbh->Execute($sql,$param);
			$result = (bool)$sth;

				// 数据库调试开关
			if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
				if ($result === false) {
					$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
				} else {
					$extra = null;
				}
				call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
			}

			while ( $obj = $sth->FetchRow (  ) ) {
				$obj_arr = array();
				foreach($data_key as $value){
					if(array_key_exists($value,$obj)){
						$obj_arr[$value] = $obj[$value];
					}
				}
				$data_all[] = $obj_arr ;
			}
			$dbh->setFetchMode($mode);
		}
		$data_arr = array ();
		switch ($classkey) {
			case 'assoc' :
				$mode = $dbh->setFetchMode(ADODB_FETCH_ASSOC);
				$result = $data_arr = $dbh->GetALL($sql,$param);

				// 数据库调试开关
				if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
					if ($result === false) {
						$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
					} else {
						$extra = null;
					}
					call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
				}

				$dbh->setFetchMode($mode);
				break;
			case 'num' :
				$mode = $dbh->setFetchMode(ADODB_FETCH_NUM);
				$result = $data_arr = $dbh->GetALL($sql,$param);

				// 数据库调试开关
				if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
					if ($result === false) {
						$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
					} else {
						$extra = null;
					}
					call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
				}

				$dbh->setFetchMode($mode);
				break;
			case 'both' :
				$mode = $dbh->setFetchMode(ADODB_FETCH_BOTH);
				$result = $data_arr = $dbh->GetALL($sql,$param);

				// 数据库调试开关
				if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
					if ($result === false) {
						$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
					} else {
						$extra = null;
					}
					call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
				}

				$dbh->setFetchMode($mode);
				break;
			case 'array' :
				$mode = $dbh->setFetchMode(ADODB_FETCH_BOTH);
				$result = $data_arr = $dbh->GetALL($sql,$param);

				// 数据库调试开关
				if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
					if ($result === false) {
						$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
					} else {
						$extra = null;
					}
					call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
				}

				if ( is_array ($data_arr) && is_array ($classname) ) {
					$data_arr = call_user_func ( array($class,'struts'), $data_arr, $classname );
				}
				$dbh->setFetchMode($mode);
				break;
			case 'column' :
				$mode = $dbh->setFetchMode(ADODB_FETCH_BOTH);
				$sth = $dbh->Execute($sql,$param);
				$result = (bool)$sth;

				// 数据库调试开关
				if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
					if ($result === false) {
						$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
					} else {
						$extra = null;
					}
					call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
				}

				while ( $obj = $sth->FetchRow (  ) ) {
					if (isset ( $obj [$classname] )) {
						$data_arr[] = $obj [$classname] ;
					} else {
						$data_arr[] = null ;
					}
				}
				$dbh->setFetchMode($mode);
				break;
			default :
			case 'class' :
				if ( isset($classkey_arr) && in_array('classtype',$classkey_arr) ) {
					$mode = $dbh->setFetchMode(ADODB_FETCH_ASSOC);
					$sth = $dbh->Execute($sql,$param);
					$result = (bool)$sth;

					// 数据库调试开关
					if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
						if ($result === false) {
							$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
						} else {
							$extra = null;
						}
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
					}

					while ( $obj = $sth->FetchRow (  ) ) {
						$obj_classname = $classname;
						foreach($obj as $key=>$obj_classname){
							unset($obj[$key]);
							break;
						}
						if(preg_match ( '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $obj_classname ) && class_exists($obj_classname)){
							$clone = new $obj_classname ();
						}elseif(class_exists ( $classname )){
							$clone = new $classname ();
						}else{
							$clone = new $calledclass ();
						}
						foreach($obj as $key=>$value){
							$clone->$key = $value;
						}
						$data_arr[] = $clone ;
					}
					$dbh->setFetchMode($mode);
				} else {
					if (class_exists ( $classname )) {
						$obj_classname = $classname;
					}else{
						$obj_classname = $calledclass;
					}
					$mode = $dbh->setFetchMode(ADODB_FETCH_ASSOC);
					$sth = $dbh->Execute($sql,$param);
					$result = (bool)$sth;

					// 数据库调试开关
					if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
						if ($result === false) {
							$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
						} else {
							$extra = null;
						}
						call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
					}

					while ( $obj = $sth->FetchRow (  ) ) {
						$clone = new $obj_classname ;
						foreach($obj as $key=>$value){
							$clone->$key = $value;
						}
						$data_arr[] = $clone ;
					}
					$dbh->setFetchMode($mode);
				}
				break;
			case 'clone' :
				if (is_object ( $classname )) {
					$obj_classname = $classname;
				}else{
					$obj_classname = new $calledclass ( );
				}
				$mode = $dbh->setFetchMode(ADODB_FETCH_ASSOC);
				$sth = $dbh->Execute($sql,$param);
				$result = (bool)$sth;

				// 数据库调试开关
				if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
					if ($result === false) {
						$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
					} else {
						$extra = null;
					}
					call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
				}

				while ( $obj = $sth->FetchRow (  ) ) {
					$clone = clone $obj_classname ;
					foreach($obj as $key=>$value){
						$clone->$key = $value;
					}
					$data_arr[] = $clone ;
				}
				$dbh->setFetchMode($mode);
				break;
		}
		if($page !== null){
			if($page['count'] === null){
				$page ['count'] = $dbh->GetOne("SELECT FOUND_ROWS()");
			}
			$page ['total'] = (int)ceil ( $page ['count'] / $page ['size'] );
		}
		return array($data_arr,$data_all);
	}
	
	/**
	 * 对象插入
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @return int
	 */
	public static function inserts($dbh, $args, $class, $sql, $param) {

		// 是否强制参数转SQL
		if (isset ($args ['sql_format']) && $args ['sql_format']) {
			$sql = self::prepare ($dbh, $args, $class, $sql, $param, true);
			$param = null;
		}

		if (is_array ($param)) {
			$result = $dbh->Execute ( $sql, $param );
		} else {
			$result = $dbh->Execute ( $sql );
		}

		// 数据库调试开关
		if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
			if ($result === false) {
				$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
			} else {
				$extra = null;
			}
			call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
		}

		return (int)$dbh->Affected_Rows();
	}
	
	/**
	 * 对象修改
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @return int
	 */
	public static function updates($dbh, $args, $class, $sql, $param) {

		// 是否强制参数转SQL
		if (isset ($args ['sql_format']) && $args ['sql_format']) {
			$sql = self::prepare ($dbh, $args, $class, $sql, $param, true);
			$param = null;
		}

		if (is_array ($param)) {
			$result = $dbh->Execute ( $sql, $param );
		} else {
			$result = $dbh->Execute ( $sql );
		}

		// 数据库调试开关
		if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
			if ($result === false) {
				$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
			} else {
				$extra = null;
			}
			call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
		}

		return (int)$dbh->Affected_Rows();
	}
	
	/**
	 * 对象删除
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @return int
	 */
	public static function deletes($dbh, $args, $class, $sql, $param) {

		// 是否强制参数转SQL
		if (isset ($args ['sql_format']) && $args ['sql_format']) {
			$sql = self::prepare ($dbh, $args, $class, $sql, $param, true);
			$param = null;
		}

		if (is_array ($param)) {
			$result = $dbh->Execute ( $sql, $param );
		} else {
			$result = $dbh->Execute ( $sql );
		}

		// 数据库调试开关
		if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
			if ($result === false) {
				$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
			} else {
				$extra = null;
			}
			call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
		}

		return (int)$dbh->Affected_Rows();
	}
	
	/**
	 * 对象更新
	 * @param dbh $dbh
	 * @param array $args
	 * @param string $class
	 * @param string $sql
	 * @param array $param
	 * @return int
	 */
	public static function replaces($dbh, $args, $class, $sql, $param) {

		// 是否强制参数转SQL
		if (isset ($args ['sql_format']) && $args ['sql_format']) {
			$sql = self::prepare ($dbh, $args, $class, $sql, $param, true);
			$param = null;
		}

		if (is_array ($param)) {
			$result = $dbh->Execute ( $sql, $param );
		} else {
			$result = $dbh->Execute ( $sql );
		}

		// 数据库调试开关
		if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
			if ($result === false) {
				$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
			} else {
				$extra = null;
			}
			call_user_func ( array($class,'prepare'), $sql, $param, null, true, $args ['debug_file'], $extra );
		}

		return (int)$dbh->Affected_Rows();
	}
	
	/**
	 * 实例选择
	 * @param dbh $dbh
	 * @param array $args
	 * @param object $that
	 * @param string $tablename
	 * @param int $primary_index
	 * @param array $params
	 * @return bool
	 */
	public static function select($dbh, $args, $that, $tablename, $primary_index, $params) {
		extract($params);
		if ($primary_name !== null) {
			$sql = 'SELECT * FROM ' . $tablename . ' WHERE ' . $primary_name . '=? LIMIT 1';
			$paramvars = array ($primary_value );
		} else {
			$sql = 'SELECT * FROM ' . $tablename . ' LIMIT 1';
			$paramvars = null;
		}

		// 是否强制参数转SQL
		if (isset ($args ['sql_format']) && $args ['sql_format']) {
			$sql = self::prepare ($dbh, $args, get_class($that), $sql, $paramvars, true);
			$paramvars = null;
		}

		if (is_array ($paramvars)) {
			$rs = $dbh->Execute ( $sql, $paramvars );
		} else {
			$rs = $dbh->Execute ( $sql );
		}
		$result = ( bool ) $rs;

		// 数据库调试开关
		if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
			if ($result === false) {
				$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
			} else {
				$extra = null;
			}
			call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'], $extra );
		}

		if (! $rs) {
			return false;
		}
		if ($rs->RecordCount() == 0) {
			$rs->Close ();
			return false;
		}
		$row = $rs->GetRowAssoc ( 2 );
		$rs->Close ();
		foreach ( $row as $key => $value ) {
			$that->$key = $value;
		}
		return true;
	}
	
	/**
	 * 实例插入
	 * @param dbh $dbh
	 * @param array $args
	 * @param object $that
	 * @param string $tablename
	 * @param int $primary_index
	 * @param array $params
	 * @return bool
	 */
	public static function insert($dbh, $args, $that, $tablename, $primary_index, $params) {
		extract($params);
		$sql = 'INSERT INTO ' . $tablename . ' (' . $fieldname . ') VALUES (' . $valuename . ')';

		// 是否强制参数转SQL
		if (isset ($args ['sql_format']) && $args ['sql_format']) {
			$sql = self::prepare ($dbh, $args, get_class($that), $sql, $paramvars, true);
			$paramvars = null;
		}

		if (is_array ($paramvars)) {
			$result = ( bool ) $dbh->Execute ( $sql, $paramvars );
		} else {
			$result = ( bool ) $dbh->Execute ( $sql );
		}

		// 数据库调试开关
		if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
			if ($result === false) {
				$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
			} else {
				$extra = null;
			}
			call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'], $extra );
		}

		if ($result && $primary_name !== null) {
			$that->$primary_name = $dbh->Insert_ID ();
		}
		return $result;
	}
	
	/**
	 * 实例修改
	 * @param dbh $dbh
	 * @param array $args
	 * @param object $that
	 * @param string $tablename
	 * @param int $primary_index
	 * @param array $params
	 * @return bool
	 */
	public static function update($dbh, $args, $that, $tablename, $primary_index, $params) {
		extract($params);
		if ($primary_name !== null) {
			$sql = 'UPDATE ' . $tablename . ' SET ' . $valuename . ' WHERE ' . $primary_name . '=? LIMIT 1';
		} else {
			$sql = 'UPDATE ' . $tablename . ' SET ' . $valuename . ' LIMIT 1';
		}

		// 是否强制参数转SQL
		if (isset ($args ['sql_format']) && $args ['sql_format']) {
			$sql = self::prepare ($dbh, $args, get_class($that), $sql, $paramvars, true);
			$paramvars = null;
		}

		if (is_array ($paramvars)) {
			$result = ( bool ) $dbh->Execute ( $sql, $paramvars );
		} else {
			$result = ( bool ) $dbh->Execute ( $sql );
		}

		// 数据库调试开关
		if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
			if ($result === false) {
				$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
			} else {
				$extra = null;
			}
			call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'], $extra );
		}

		if($result && $dbh->Affected_Rows()==0){
			return false;
		}
		return $result;
	}
	
	/**
	 * 实例删除
	 * @param dbh $dbh
	 * @param array $args
	 * @param object $that
	 * @param string $tablename
	 * @param int $primary_index
	 * @param array $params
	 * @return bool
	 */
	public static function delete($dbh, $args, $that, $tablename, $primary_index, $params) {
		extract($params);
		if ($primary_name !== null) {
			$sql = 'DELETE FROM ' . $tablename . ' WHERE ' . $primary_name . '=? LIMIT 1';
			$paramvars = array ($primary_value );
		} else {
			$sql = 'DELETE FROM ' . $tablename . ' LIMIT 1';
			$paramvars = null;
		}

		// 是否强制参数转SQL
		if (isset ($args ['sql_format']) && $args ['sql_format']) {
			$sql = self::prepare ($dbh, $args, get_class($that), $sql, $paramvars, true);
			$paramvars = null;
		}

		if (is_array ($paramvars)) {
			$result = ( bool ) $dbh->Execute ( $sql, $paramvars );
		} else {
			$result = ( bool ) $dbh->Execute ( $sql );
		}

		// 数据库调试开关
		if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
			if ($result === false) {
				$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
			} else {
				$extra = null;
			}
			call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'], $extra );
		}

		if($result && $dbh->Affected_Rows()==0){
			return false;
		}
		return  $result;
	}
	
	/**
	 * 实例更新
	 * @param dbh $dbh
	 * @param array $args
	 * @param object $that
	 * @param string $tablename
	 * @param int $primary_index
	 * @param array $params
	 * @return bool
	 */
	public static function replace($dbh, $args, $that, $tablename, $primary_index, $params) {
		extract($params);
		$sql = 'REPLACE INTO ' . $tablename . ' (' . $fieldname . ') VALUES (' . $valuename . ')';

		// 是否强制参数转SQL
		if (isset ($args ['sql_format']) && $args ['sql_format']) {
			$sql = self::prepare ($dbh, $args, get_class($that), $sql, $paramvars, true);
			$paramvars = null;
		}

		if (is_array ($paramvars)) {
			$result = ( bool ) $dbh->Execute ( $sql, $paramvars );
		} else {
			$result = ( bool ) $dbh->Execute ( $sql );
		}

		// 数据库调试开关
		if (isset ($args ['debug_enable']) && $args ['debug_enable']) {
			if ($result === false) {
				$extra = array('errno'=>$dbh->ErrorNo(),'error'=>$dbh->ErrorMsg());
			} else {
				$extra = null;
			}
			call_user_func ( array(get_class($that),'prepare'), $sql, $paramvars, null, true, $args ['debug_file'], $extra );
		}

		if ($result && $primary_name !== null) {
			$that->$primary_name = $dbh->Insert_ID ();
		}
		return $result;
	}
	
}

/**
 * 执行(execute)
 */

// 载入类文件
if (! class_exists ( 'ADOConnection' , false ) ) {
	require_once dirname(__FILE__) . '/' . basename(__FILE__,'.php') . '/adodb.inc.php';
}

?>