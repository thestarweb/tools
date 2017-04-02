<?php
class dbhelper_tool{
	private static function found_structure($s,$name){
		//var_dump($s,$name);
		foreach($s as $k=>$v){
			if($v['Field']==$name){
				var_dump($k);
				return $k;
			}
		}
		return false;
	}
	private static function get_structure_string($name,$type,$null,$last_structure,$auto_increment){
		return $name.'`'.$type.' '.($null?'':'NOT NULL ').($auto_increment?'AUTO_INCREMENT ':'').($last_structure?('AFTER `'.$last_structure.'`;'):'FIRST');
	}
	private static function change_structure($db,$table_name,$old_name,$name,$type,$null,$last_structure,$auto_increment){
		$db->exec('ALTER TABLE `'.$table_name.'` CHANGE `'.$old_name.'` `'.self::get_structure_string($name,$type,$null,$last_structure,$auto_increment));
	}
	private static function get_primarykey($indexs){
		foreach($indexs as $v){
			if($v['Key_name']=='PRIMARY'){
				return $v['Column_name'];
			}
		}
		return false;
	}
	public static function update($db,$xml_path){
		$xml=new xml_tool($xml_path);
		$tablenum=$xml->how_many('/db/table');
		for($i=0;$i<$tablenum;$i++){
			$b='/db/table['.($i+1).']';
			$table_name=$xml->look_attributes('/db/table',$i,'name');
			//先不管三七二十一，保证有这么一张表。
			$db->exec('CREATE TABLE IF NOT EXISTS `'.$table_name.'`(`id` int)DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
			//分析这个表目前的状态
			$s=$db->exec('DESC `'.$table_name.'`');
			//获取现有索引
			$index=$db->exec('SHOW INDEX IN `'.$table_name.'`');
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
				if($k===false){
					$db->exec('ALTER TABLE `'.$table_name.'` ADD `'.self::get_structure_string($structure_name,$type,$null,$last_structure,$auto_increment));
				}else{
					$primarykey=self::get_primarykey($index);
					if($auto_increment&&$primarykey!=$structure_name){
						//var_dump($primarykey!=$structure_name,$primarykey,$structure_name);exit;
						if($primarykey){
							$db->exec('ALTER TABLE `'.$table_name.'` DROP PRIMARY KEY');
						}
						echo 'ALTER TABLE `'.$table_name.'` ADD PRIMARY KEY(`'.$structure_name.'`)';exit;
						$db->exec('ALTER TABLE `'.$table_name.'` ADD PRIMARY KEY(`'.$structure_name.'`)');
					}
					self::change_structure($db,$table_name,$old_name,$structure_name,$type,$null,$last_structure,$auto_increment);
				}
				$last_structure=$structure_name;
			}
			foreach($s as $v){
				$db->exec('ALTER TABLE `'.$table_name.'` DROP `'.$v['Field'].'`;');
			}
		}
	}
}