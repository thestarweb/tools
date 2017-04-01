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
	private static function change_structure($db,$table_name,$old_name,$name,$type,$null,$last_structure){
		$db->exec('ALTER TABLE `'.$table_name.'` CHANGE `'.$old_name.'` `'.$name.'`'.$type.' '.($null?'':'NOT NULL ').($last_structure?('AFTER `'.$last_structure.'`;'):'FIRST'));
	}
	public static function update($db,$xml_path){
		$xml=new xml_tool($xml_path);
		$tablenum=$xml->how_many('/db/table');
		for($i=0;$i<$tablenum;$i++){
			$b='/db/table['.($i+1).']';
			$table_name=$xml->look_attributes('/db/table',$i,'name');
			//先不管三七二十一，保证有这么一张表。
			$db->exec('CREATE TABLE IF NOT EXISTS `'.$table_name.'`(`id` int)DEFAULT CHARSET=utf8;');
			//分析这个表目前的状态
			$s=$db->exec('DESC `'.$table_name.'`');
			$structure_path=$b.'/structure';
			$c_nu=$xml->how_many($structure_path);
			$last_structure=null;
			for($j=0;$j<$c_nu;$j++){
				$structure_name=$xml->look_attributes($structure_path,$j,'name');
				if(($k=self::found_structure($s,$structure_name))!==false){
					$old_name=$structure_name;
					unset($s[$k]);
				}else{
					$olds=explode(',', $xml->look_attributes($structure_path,$j,'old_name'));
					foreach($olds as $v){
						if(($k=self::found_structure($s,$v))!==false){
							$old_name=$v;
							//var_dump('ALTER TABLE `'.$table_name.'` CHANGE `'.$v.'` `'.$structure_name.'`');exit;
							//$db->exec('ALTER TABLE `'.$table_name.'` CHANGE `'.$v.'` `'.$structure_name.'`'.$xml->look_attributes($structure_path,$j,'type').' '.
						//($xml->look_attributes($structure_path,$j,'null')=="true"?'':'NOT NULL ').($last_structure?('AFTER `'.$last_structure.'`;'):'FIRST'));
							unset($s[$k]);
							break;
						}
					}
				}
				self::change_structure($db,$table_name,$old_name,$structure_name,$xml->look_attributes($structure_path,$j,'type'),$xml->look_attributes($structure_path,$j,'null')=="true",$last_structure);
				var_dump($k);
				if($k===false){
					$db->exec('ALTER TABLE `'.$table_name.'` ADD `'.$structure_name.'` '.$xml->look_attributes($structure_path,$j,'type').' '.
						($xml->look_attributes($structure_path,$j,'null')=="true"?'':'NOT NULL ').($last_structure?('AFTER `'.$last_structure.'`;'):'FIRST'));
				}
				$last_structure=$structure_name;
			}
			foreach($s as $v){
				$db->exec('ALTER TABLE `'.$table_name.'` DROP `'.$v['Field'].'`;');
			}
		}
	}
}