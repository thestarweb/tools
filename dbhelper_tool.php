<?php
class dbhelper_tool{
	/**
		此函数用于查找一个结构（列）的下标
		@s array 通过desc获取到的表结构数组
		@name string 结构（列）名称
		return mix false->没有找到 int->找到的下标
	*/
	private static function found_structure($s,$name){
		//var_dump($s,$name);
		foreach($s as $k=>$v){
			if($v['Field']==$name){
				return $k;
			}
		}
		return false;
	}
	/**
		拼接结构（列）语句
		@name string 列名
		@type string 结构（列）类型
		@attributes string 结构（列）属性
		@null bool 是否允许空
		@last_structure null/string 前一个结构（列）的名称
		@default string 默认值
		@auto_increment bool 是否拼接设置自增长
		return string
	*/
	private static function get_structure_string($name,$type,$attributes,$null,$last_structure,$default,$auto_increment,$table_exists=true){
		return '`'.$name.'`'.$type.' '.$attributes.' '.($null?'':'NOT NULL ').($auto_increment?'AUTO_INCREMENT ':'').($default===false?'':('DEFAULT \''.str_replace('\'','\\\'', $default).'\' ')).($table_exists?($last_structure?('AFTER `'.$last_structure.'`;'):'FIRST'):'');
	}
	/**
		拼接执行修改列的语句
		@db object(pdo_mysql) 数据库连接
		@table_name string 表名
		@old_name string 结构原名称
		@dbstring string 目标结构语句（一般由get_structure_string得到）
		return void
	*/
	private static function change_structure($db,$table_name,$old_name,$dbstring){
		$db->exec('ALTER TABLE `'.$table_name.'` CHANGE `'.$old_name.'` '.$dbstring);
	}
	/**
		获取整理好的表索引
		@db object(pdo_mysql)
		@table string 需要获取的表
	*/
	private static function get_indexs($db,$table_name){
		$index=$db->exec('SHOW INDEX IN `'.$table_name.'`');
		$indexs=[];
		//整理索引
		foreach($index as $v){
			//var_dump($v);exit;
			if(isset($indexs[$v['Key_name']])){
				//var_dump($indexs[$v['Key_name']]);
				$indexs[$v['Key_name']]['value'].=','.$v['Column_name'];
			}else{
				$type=($v['Index_type']=='BTREE'||$v['Index_type']=="HASH")?($v['Key_name']=='PRIMARY'?'PRIMARY':($v['Non_unique']?'INDEX':'UNIQUE')):$v['Index_type'];
				//var_dump($type);
				$indexs[$v['Key_name']]=['value'=>$v['Column_name'],'type'=>$type];
			}
		}
		return $indexs;
	}
	/**
		查找表的主键名称
		@indexs array 通过show index in返回的数据
		return string->找到的结构（列）名 false->没有找到主键
	*/
	private static function get_primarykey($indexs){
		foreach($indexs as $v){
			if($v['Key_name']=='PRIMARY'){
				return $v['Column_name'];
			}
		}
		return false;
	}
	/**
		int
	*/
	private static $index_add=0;//记录删除的索引数量
	/**
		向表增加一个索引
		@db object(pdo_mysql)
		@table_name string
		@type string
		@indexname string 索引名字
		@index_structure string 索引的列（可用逗号分隔）
		return void
	*/
	private static function add_index($db,$table_name,$type,$indexname,$index_structure){
		//var_dump($table_name,$type,$indexname,$index_structure);echo '<br/>';return;
		self::$index_add++;
		if($type=='PRIMARY'){
			$db->exec('ALTER TABLE `'.$table_name.'` ADD PRIMARY KEY(`'.$index_structure.'`)');
			return;
		}
		$index_structure=str_replace(',','`,`',$index_structure);
		$db->exec('ALTER TABLE `'.$table_name.'` ADD '.$type.'  `'.$indexname.'`( `'.$index_structure.'` ) ');
	}
	/**
		int
	*/
	private static $index_remove=0;//记录移出的索引数量。
	/**
		移除一个索引
		@db object(pdo_mysql)
		@table_name string
		@indexname string
		return void
	*/
	private static function remove_index($db,$table_name,$index_name){
		self::$index_remove++;
		$db->exec('DROP INDEX `'.$index_name.'` ON `'.$table_name.'`');
	}
	/**
		通过xml更新数据库
		@db object(pdo_mysql)
		@xml_path string xml文件所在路径
		return void
	*/
	public static function update($db,$xml_path){
		//初始化
		self::$index_add=0;self::$index_remove=0;
		$xml=new xml_tool($xml_path);
		$tablenum=$xml->how_many('/db/table');
		$log='';$succ=true;
		for($i=0;$i<$tablenum;$i++){
			$b='/db/table['.($i+1).']';
			$table_name='@%_'.$xml->look_attributes('/db/table',$i,'name');
			//var_dump($table_name);exit;
			$engine=$xml->look_attributes('/db/table',$i,'engine');
			//获取表的基本信息
			$table_status=$db->exec('SHOW TABLE STATUS WHERE name="'.$table_name.'"');
			if($table_status){
				$log.='检查表`'.$table_name."`\n";
				if($engine&&$engine!==$table_status[0]['Engine']){
					$log.='表`'.$table_name.'`的存储引擎为'.$table_status[0]['Engine'].'，但需要的引擎为'.$engine."\n";
					try{
						$db->exec('alter table `'.$table_name.'` engine='.$engine);
						$log.='成功修改了表`'.$table_name.'`的存储引擎为'.$engine."\n";
					}catch (Exception $e){
						$succ=false;
						$log.='修改表`'.$table_name.'`的存储引擎失败：'.$e->getMessage()."\n";
					}
				}
				$table_exists=true;
			}else{
				// try{
				// 	$db->exec('CREATE TABLE IF NOT EXISTS `'.$table_name.'`(`id` int)'.($engine?' ENGINE='.$engine:'').' DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
				// 	$log.='创建表`'.$table_name.'`成功'."\n";
				// }catch (Exception $e){
				// 	$log.='创建表`'.$table_name.'`失败：'.$e->getMessage()."\n";
				// }
				$table_exists=false;
				$creat_structure='';
			}



			//分析这个表目前的状态
			$s=$table_exists?$db->exec('DESC `'.$table_name.'`'):[];
			//获取现有索引
			$index=$table_exists?$db->exec('SHOW INDEX IN `'.$table_name.'`'):[];
			//修改结构
			$structure_path=$b.'/structure';
			$c_nu=$xml->how_many($structure_path);
			$last_structure=null;
			//以下用来记录自动自增长字段的数据
			$ai_structure=false;
			$ai_structure_string='';

			for($j=0;$j<$c_nu;$j++){
				$structure_name=$xml->look_attributes($structure_path,$j,'name');
				//获取当在前表中的名字
				if(($k=self::found_structure($s,$structure_name))!==false){
					$old_name=$structure_name;
				}else{
					$olds=explode(',', $xml->look_attributes($structure_path,$j,'old_name'));
					foreach($olds as $v){
						if(($k=self::found_structure($s,$v))!==false){
							$old_name=$v;
							break;
						}
					}
				}
				//获取各种属性
				$auto_increment=$xml->look_attributes($structure_path,$j,'auto_increment')=="true"&&!$ai_structure;
				$type=$xml->look_attributes($structure_path,$j,'type');
				$null=$xml->look_attributes($structure_path,$j,'null')=="true";
				$attributes=$xml->look_attributes($structure_path,$j,'attributes');
				$default=$xml->look_attributes($structure_path,$j,'default',true);
				$dbstring=self::get_structure_string($structure_name,$type,$attributes,$null,$last_structure,$default,false,$table_exists);
				if($auto_increment){
					$ai_structure=$structure_name;
					$ai_structure_string=self::get_structure_string($structure_name,$type,$attributes,$null,$last_structure,$default,true,$table_exists);
				}
				if($k===false){
					if($table_exists){
						try{
							$db->exec('ALTER TABLE `'.$table_name.'` ADD '.$dbstring);
							$log.='成功增加表结构`'.$table_name.'`.`'.$structure_name."`\n";
						}catch(Exception $e){
							$succ=false;
							$log.='增加表结构`'.$table_name.'`.`'.$structure_name.'`失败：'.$e->getMessage()."\n";
						}
					}else{
						if($creat_structure!='')$creat_structure.=',';
						$creat_structure.=$dbstring;
					}
				}else{
					$t=$s[$k];
					$temp=explode(' ',$t['Type'],2);
					$t_type=$temp[0];$t_attributes=isset($temp[1])?$temp[1]:'';
					if(stristr($t_type,$type)==$t_type&&strtolower($t_attributes)==strtolower($attributes)&&($t['Null']!='NO')==$null&&
						(($default===false&&$t['Default']===null)||($t['Default']===$default))
					){
						if(($t['Extra']=='auto_increment')==$auto_increment){
							unset($s[$k]);
							if($auto_increment)$ai_structure_string='';//自增长数据一致的不用更新自增长字段
							continue;//匹配成功 无须操作
						}
					}
					unset($s[$k]);
					try{
						self::change_structure($db,$table_name,$old_name,$dbstring);
						$log.='成功更新结构`'.$table_name.'`.`'.$structure_name."`\n";
					}catch(Exception $e){
						$succ=false;
						$log.='结构`'.$table_name.'`.`'.$structure_name.'`更新失败：'.$e->getMessage()."\n";
					}

				}
				$last_structure=$structure_name;
			}

			if($table_exists){
				//删除多余的结构
				foreach($s as $v){
					try{
						$db->exec('ALTER TABLE `'.$table_name.'` DROP `'.$v['Field'].'`;');
						$log.='删除无用结构`'.$table_name.'`.`'.$v['Field'].'`成功'."\n";
					}catch (Exception $e){
						$log.='删除无用结构`'.$table_name.'`.`'.$v['Field'].'`失败：'.$e->getMessage()."\n";
					}
				}
			}else{
				try{
					$db->exec('CREATE TABLE `'.$table_name.'`('.$creat_structure.')'.($engine?' ENGINE='.$engine:'').' DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
					$log.='创建表`'.$table_name.'`成功'."\n";
				}catch (Exception $e){
					$succ=false;
					$log.='创建表`'.$table_name.'`失败：'.$e->getMessage()."\n";
					continue;//表都没有建立成功 后面的索引就不用建立了
				}
			}

			if($ai_structure&&$ai_structure_string){
				$primarykey=self::get_primarykey($index);
				if($primarykey!=$ai_structure){
					//var_dump($primarykey!=$structure_name,$primarykey,$structure_name);exit;
					if($primarykey){
						try{
							$db->exec('ALTER TABLE `'.$table_name.'` DROP PRIMARY KEY');
							$log.='成功移除表`'.$table_name.'`的主键为`'.$ai_structure."`\n";
						}catch(Exception $e){
							$log.='移除表`'.$table_name.'`的主键为`'.$ai_structure.'`失败：'.$e->getMessage()."\n";
						}
					}
					try{
						$db->exec('ALTER TABLE `'.$table_name.'` ADD PRIMARY KEY(`'.$ai_structure.'`)');
						$log.='成功设置表`'.$table_name.'`的主键为`'.$ai_structure."`\n";
					}catch(Exception $e){
						$log.='设置表`'.$table_name.'`的主键为`'.$ai_structure.'`失败：'.$e->getMessage()."\n";
					}
				}
				try{
					self::change_structure($db,$table_name,$ai_structure,$ai_structure_string);
					$log.='成功更新自增长`'.$table_name.'`.`'.$ai_structure."`\n";
				}catch(Exception $e){
					$succ=false;
					$log.='自增长`'.$table_name.'`.`'.$ai_structure.'`更新失败：'.$e->getMessage()."\n";
				}
			}




			//修改索引
			$indexs=self::get_indexs($db,$table_name);
			$index_path=$b.'/index';
			$c_nu=$xml->how_many($index_path);
			for($j=0;$j<$c_nu;$j++){
				$type=$xml->look_attributes($index_path,$j,'type');
				$name=$xml->look_attributes($index_path,$j,'name');
				$value=$xml->look($index_path,$j);
				if(isset($indexs[$name])){
					if($indexs[$name]['value']==$value){
						unset($indexs[$name]);
						continue;
					}else{
						unset($indexs[$name]);
						try{
							self::remove_index($db,$table_name,$name);
							$log.='成功移除错误索引`'.$table_name.'`'.$name.':'.$indexs[$anme]['value'].'=>'.$value."\n";
						}catch (Exception $e){
							$log.='移除错误索引`'.$table_name.'`'.$name.':'.$indexs[$anme]['value'].'=>'.$value.'失败：'.$e->getMessage()."\n";
						}
					}
				}elseif($type=='PRIMARY'&&isset($indexs['PRIMARY'])){
					if($indexs['PRIMARY']['value']==$value){
						continue;
					}else{
						$db->exec('ALTER TABLE `'.$table_name.'` DROP PRIMARY KEY');
					}
				}
				try{
					self::add_index($db,$table_name,$type,$name,$value);
					$log.='成功建立索引`'.$table_name.'`'.$name."\n";
				}catch (Exception $e){
					$succ=false;
					$log.='建立索引`'.$table_name.'`'.$name.'失败：'.$e->getMessage()."\n";
				}
			}
			foreach($indexs as $k=>$v){
				if($k!='PRIMARY'){
					try{
						self::remove_index($db,$table_name,$k);
						$log.='成功移除无效索引`'.$table_name.'`'.$k."\n";
					}catch (Exception $e){
						$log.='移除无效索引`'.$table_name.'`'.$k.'失败：'.$e->getMessage()."\n";
					}
				}
			}
		}
		return ['index_add'=>self::$index_add,'index_remove'=>self::$index_remove,'succ'=>$succ,'log'=>$log];
	}
	/**
		根据数据库中的内容创建xml文件（如果存在则会覆盖）
		@db object(pdo_mysql)
		@xml_path string xml文件所在路径
		return void
	*/
	public static function export($db,$xml_path,$db_prefix=''){
		$prefix_len=strlen($db_prefix);
		$xml=new xml_tool($xml_path,'db',true);
		$tables=$db->exec('SHOW TABLES',PDO::FETCH_NUM);
		$i=0;
		foreach ($tables as $v) {
			if($db_prefix&&strpos($v[0],$db_prefix)!==0) continue;
			else $table_name=substr($v[0],$prefix_len);
			$xml->add('/db',0,'table');
			$xml->add_attributes('/db/table',$i,'name',$table_name);
			//获取表结构
			$table_info=$db->exec('DESC `'.$v[0].'`');
			$structure_path='/db/table['.($i+1).']/structure';
			$j=0;
			foreach ($table_info as $v2) {
				$xml->add('/db/table',$i,'structure');
				$xml->add_attributes($structure_path,$j,'name',$v2['Field']);
				$type=explode(' ',$v2['Type'],2);
				$xml->add_attributes($structure_path,$j,'type',$type[0]);
				if(isset($type[1])) $xml->add_attributes($structure_path,$j,'attributes',$type[1]);
				if($v2['Null']=='YES') $xml->add_attributes($structure_path,$j,'null','true');
				if($v2['Default']!==null) $xml->add_attributes($structure_path,$j,'default',$v2['Default']);
				if($v2['Extra']=='auto_increment') $xml->add_attributes($structure_path,$j,'auto_increment','true');
				$j++;
			}
			//获取索引
			$index_info=self::get_indexs($db,$v[0]);
			$index_path='/db/table['.($i+1).']/index';
			$j=0;
			foreach ($index_info as $k=>$v2) {
				$xml->add('/db/table',$i,'index',$v2['value']);
				$xml->add_attributes($index_path,$j,'name',$k);
				$xml->add_attributes($index_path,$j,'type',$v2['type']);
				$j++;
			}
			$i++;
		}
	}
}
