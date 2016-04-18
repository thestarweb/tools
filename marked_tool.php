<?php
class marked_tool{
	public static function marked($mk,$ob=array()){
		foreach($ob as $k=>$v){
			$mk=str_replace('{$'.$k.'}',$v,$mk);
		}
		$mk=preg_replace_callback ('/(?:\r|\n|^)(#+)(.*?)(\r|\n)/',function($c){
			$h=strlen($c[1]);//var_dump($c);
			return "\n\n<h".$h.'>'.$c[2].'</h'.$h.">\n\n";
		}, $mk);
		$mk=preg_replace('/\$\[(.*?)\]\((.*?)\)/','<span style="color:$2">$1</span>', $mk);
		$mk=preg_replace('/!\[(.*?)\]\((.*?)(?: w=(\d+%?))?(?: h=(\d+%?))?\)/','<img src="$2" title="$1" style="width:$3;height:$4"/>', $mk);
		$mk=preg_replace('/\[(.*?)\]\((.*?)\)/','<a href="$2">$1</a>', $mk);
		$mk=preg_replace_callback('/((?:<[^<]+?>)?)(\n+)((?:<.+?>)?)/',function($c){
			$res=$c[1];
			if(strpos($c[1],'</h')!==0)$res.='</p>';
			if(strpos($c[3],'<h')!==0) $res.='<p>';
			$res.=$c[3].$c[2];
			//var_dump($c,strpos($c[1],'</h')!==0,strpos($c[1],'</h')!==0,$res);
			return $res;
		},$mk);
		return '<p>'.$mk.'</p>';
	}
	public static function h($c){
		var_dump($c);exit;
		$h=strlen($j);
		return '<h'.$h.'>'.$title.'</h'.$h.'>';
	}
}