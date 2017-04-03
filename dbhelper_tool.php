<?php
class dbhelper_tool{
	private static function found_structure($s,$name){
		//var_dump($s,$name);
		foreach($s as $k=>$v){
			if($v['Field']==$name){
				return $k;
			}
		}
		return false;
	}
	private static function get_structure_string($name,$type,$attributes,$null,$last_structure,$auto_increment){
		return '`'.$name.'`'.$type.' '.$attributes.' '.($null?'':'NOT NULL ').($auto_increment?'AUTO_INCREMENT ':'').($last_structure?('AFTER `'.$last_structure.'`;'):'FIRST');
	}
	private static function change_structure($db,$table_name,$old_name,$name,$type,$attributes,$null,$last_structure,$auto_increment){
		$db->exec('ALTER TABLE `'.$table_name.'` CHANGE `'.$old_name.'` '.self::get_structure_string($name,$type,$attributes,$null,$last_structure,$auto_increment));
	}
	private static function get_primarykey($indexs){
		foreach($indexs as $v){
			if($v['Key_name']=='PRIMARY'){
				return $v['Column_name'];
			}
		}
		return false;
	}
	private static $index_add=0;//记录删除的索引数量
	private static function add_index($db,$table_name,$type,$indexname,$index_structure){
		self::$index_add++;
		$index_structure=str_replace(',','`,`',$index_structure);
		if($type=='UNIQUE')$type.=' KEY';
		$db->exec('ALTER TABLE `'.$table_name.'` ADD '.$type.'  `'.$indexname.'`( `'.$index_structure.'` ) ');
	}
	private static $index_remove=0;//记录移出的索引数量。
	private static function remove_index($db,$table_name,$index_name){
		self::$index_remove++;
		$db->exec('DROP INDEX `'.$index_name.'` ON `'.$table_name.'`');
	}
	public static function update($db,$xml_path){
		//初始化
		self::$index_add=0;self::$index_remove=0;
		$xml=new xml_tool($xml_path);
		$tablenum=$xml->how_many('/db/table');
		for($i=0;$i<$tablenum;$i++){
			$b='/db/table['.($i+1).']';
			$table_name='@%_'.$xml->look_attributes('/db/table',$i,'name');
			//var_dump($table_name);exit;
			//先不管三七二十一，保证有这么一张表。
			$db->exec('CREATE TABLE IF NOT EXISTS `'.$table_name.'`(`id` int)DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
			//分析这个表目前的状态
			$s=$db->exec('DESC `'.$table_name.'`');
			//获取现有索引
			$index=$db->exec('SHOW INDEX IN `'.$table_name.'`');
			//修改结构
			$structure_path=$b.'/structure';
			$c_nu=$xml->how_many($structure_path);
			$last_structure=null;
			for($j=0;$j<$c_nu;$j++){
				$structure_name=$xml->look_attributes($structure_path,$j,'name');
				//获取当在前表中的名字
				if(($k=self::found_structure($s,$structure_name))!==false){
					$old_name=$structure_name;
					unset($s[$k]);
				}else{
					$olds=explode(',', $xml->look_attributes($structure_path,$j,'old_name'));
					foreach($olds as $v){
						if(($k=self::found_structure($s,$v))!==false){
							$old_name=$v;
							unset($s[$k]);
							break;
						}
					}
				}
				//获取各种属性
				$auto_increment=$xml->look_attributes($structure_path,$j,'auto_increment')=="true";
				$type=$xml->look_attributes($structure_path,$j,'type');
				$null=$xml->look_attributes($structure_path,$j,'null')=="true";
				$attributes=$xml->look_attributes($structure_path,$j,'attributes');
				if($k===false){
					$db->exec('ALTER TABLE `'.$table_name.'` ADD '.self::get_structure_string($structure_name,$type,$attributes,$null,$last_structure,$auto_increment));
				}else{
					$primarykey=self::get_primarykey($index);
					if($auto_increment&&$primarykey!=$structure_name){
						//var_dump($primarykey!=$structure_name,$primarykey,$structure_name);exit;
						if($primarykey){
							$db->exec('ALTER TABLE `'.$table_name.'` DROP PRIMARY KEY');
						}
						$db->exec('ALTER TABLE `'.$table_name.'` ADD PRIMARY KEY(`'.$structure_name.'`)');
					}
					self::change_structure($db,$table_name,$old_name,$structure_name,$type,$attributes,$null,$last_structure,$auto_increment);
				}
				$last_structure=$structure_name;
			}
			//删除多余的结构
			foreach($s as $v){
				$db->exec('ALTER TABLE `'.$table_name.'` DROP `'.$v['Field'].'`;');
			}
			//修改索引
			$index=$db->exec('SHOW INDEX IN `'.$table_name.'`');
			$indexs=[];
			//整理索引
			foreach($index as $v){
				//var_dump($v);exit;
				if(isset($indexs[$v['Key_name']])){
					var_dump($indexs[$v['Key_name']]);
					$indexs[$v['Key_name']]['value'].=','.$v['Column_name'];
				}else{
					$type=$v['Index_type']=='BTREE'?($v['Key_name']=='PRIMARY'?'PRIMARY':($v['Non_unique']?'INDEX':'UNIQUE')):$v['Index_type'];
					var_dump($type);
					$indexs[$v['Key_name']]=['value'=>$v['Column_name'],'type'=>$type];
				}
			}
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
						self::remove_index($db,$table_name,$name);
					}
				}
				self::add_index($db,$table_name,$type,$name,$value);
			}
			foreach($indexs as $k=>$v){
				if($k!='PRIMARY'){
					self::remove_index($db,$table_name,$k);
				}
			}
		}
		return ['index_add'=>self::$index_add,'index_remove'=>self::$index_remove];
	}
}