<?php
	class pdo_mysql{
		public $system;
		public $pdo=null;
		/**
			@host string 主机
			@username string 用户名
			@password staring 密码
			@db string 所选数据库
			@prefix string 表前缀
			@charset string 字符集
		*/
		public function __construct($host,$username,$password,$db,$prefix='',$charset='utf8'){
			try {
				$this->pdo=new PDO('mysql:host='.$host.';dbname='.$db,$username,$password);
				$this->pdo->query('set names '.$charset);
				$this->prefix=$prefix;
			} catch (PDOException $e) {
				echo 'Connection failed: ' . $e->getMessage();exit;
			}
		}
		//执行sql
		/**
			@sql string 要执行的sql语句
		*/
		public function exec($sql,$FetchMode=PDO::FETCH_ASSOC){
			$sql=str_replace('@%_',$this->prefix,$sql);
			list($doing)=explode(' ',$sql);
			$doing=strtoupper($doing);
			if($doing=='SELECT'||$doing=='SHOW'||$doing=='DESC'){
				$res=$this->pdo->query($sql);
				$error=$this->pdo->errorInfo();
				if($error[1]){
					throw new Exception('mysql_tool error:'.$error[1].'故障信息'.$error[2].'　SQL语句：'.$sql,512);
				}
				if(is_object($res)){
					$res->setFetchMode($FetchMode);
					return $res->fetchAll();
				}else{
					if($res===false){
						var_dump($this->pdo->errorInfo());
					}
					return $res;
				}
			}else{

				$res=$this->pdo->exec($sql);
				$error=$this->pdo->errorInfo();
				if($error[1]){
					throw new Exception('mysql_tool error:'.$error[1].'故障信息'.$error[2].'　SQL语句：'.$sql,512);
				}
				return $res;
			}
		}
		//上面函数的旧名称 强烈不建议使用 且会在未来版本中废除
		public function do_SQL($sql){
			return $this->exec($sql);
		}

		//预编译一个sql语句
		/**
			@sql string 需要预编译的sql语句
		*/
		public function prepare($sql){
			return $this->pdo->prepare(str_replace('@%_',$this->prefix,$sql));
		}

		//支持有用户输入参数过滤的sql语句执行方法
		/**
			有用户输入时的sql执行方案（使用了预编译机制防止sql注入）
			@sql string 进行预编译的语句
			@arr array 用于替换sql中“？”的数组
			@fetch_type int 返回数组的格式
			return mix
				查询语句 array 检索出的数据
				影响行数
		*/
		public function u_exec($sql,$arr,$fetch_type=PDO::FETCH_ASSOC){
			$sth=$this->prepare($sql);
			$sth->execute($arr);
			$error=$sth->errorInfo();
			if($error[1]){
				throw new Exception('mysql_tool error:'.$error[1].'语句'.$sql.'故障信息'.$error[2],512);
			}
			$sth->setFetchMode(PDO::FETCH_ASSOC);
			$res=$sth->fetchAll($fetch_type);
			list($doing)=explode(' ',$sql);
			$doing=strtoupper($doing);
			if($doing=='SELECT'||$doing=='SHOW'||$doing=='DESC'){
				return $res;
			}else{
				return $sth->rowCount();
			}
		}
		//上面函数的旧名称 强烈不建议使用
		public function u_do_SQL($sql,$arr,$fetch_type=PDO::FETCH_ASSOC,$fetch_style=''){
			return $this->u_exec($sql,$arr,$fetch_type);
		}

		/**
			尝试更新数据，如果返回行数为0，则插入这条数据
			@table_name string 表名
			@where array 键为条件名称值为条件值
			@data array 键为需要更新的列，值为列对应的值
			return void

			*表名和列名不会进行过滤
			**必须填写where条件，此外，where和data提供字段以外的值必须有默认值或允许为空，否则无法插入
		*/
		public function update_or_insert($table_name,$where,$data){
			$sql='UPDATE `'.$table_name.'` SET ';
			$flag=false;
			$values=[];
			foreach ($data as $key => $value) {
				if($flag) $sql.=',';
				$flag=true;
				$sql.='`'.$key.'`=?';
				$values[]=$value;
			}
			$flag=false;
			$sql.=' WHERE ';
			foreach ($where as $key => $value) {
				if($flag) $sql.=' AND ';
				$flag=true;
				$sql.='`'.$key.'`=?';
				$values[]=$value;
			}
			if(!$this->u_do_SQL($sql,$values)){
				$placeholder='';
				$keys='';
				foreach ($data as $key => $value) {
					$keys.='`'.$key.'`,';
					$placeholder.='?,';
				}
				$flag=false;
				foreach ($where as $key => $value) {
					if($flag){
						$keys.=',';
						$placeholder.=',';
					}
					$flag=true;
					$keys.='`'.$key.'`';
					$placeholder.='?';
				}
				$this->u_do_SQL('INSERT INTO `'.$table_name.'`('.$keys.') VALUE('.$placeholder.')',$values);
			}
		}

		public function get_insert_id(){
			return $this->pdo->lastInsertId();
		}
	}
?>
