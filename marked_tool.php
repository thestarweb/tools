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
	public static $block_rule;
	public static function marked_all($mk,$ob=[],$m_flag=0){
		//Lexer.prototype.lex
		$mk=str_replace("\r","\n",str_replace("\r\n","\n",$mk));
		//Lexer.prototype.token
		$res='';
		while($mk){
			$flag=false;
			foreach (marked_tool::$block_rule as $k => $v) {
				if(preg_match($v[0],$mk,$cap)){
					$flag=true;
					if(isset($v[1]))$res.=($v[1])($cap,$ob,$m_flag);//Parser.prototype.tok
					$mk=substr($mk,strlen($cap[0]));
					break;
				}
			}
			if(!$flag) throw new Exception('marked_tool解析出错',512);
		}
		return $res;
	}
	public static function marked_inline($t){
		return $t;
	}

	public static function init(){
		self::$block_rule=[
			"newline"=>["/^\n/",function($cap){
				return '';
			}],//空行
			"code"=>["/^( {4}[^\n]+\n*)+/",function($cap){
				return '<pre style="overflow:auto;"><code><ol><li>'.str_replace("\n",'</li><li>',$cap[0]).'</li></ol></code></pre>';
			}],
			"fences"=>["/^ *(`{3,}|~{3,}) *(\w*) *\n([\s\S]+?)\s*\\1 *(?:\n+|$)/",function($cap){
				return '<pre style="overflow:auto;"><code class="'.$cap[2].'"><ol><li>'.str_replace("\n",'</li><li>',$cap[3]).'</li></ol></code></pre>';
			}],//代码段
			"hr"=>["/^( *[-*_]){3,} *(?:\n+|$)/",function($cap){
				return '<hr/>';
			}],//分隔符
			"heading"=>["/^ *(#{1,6}) *([^\n]+?) *#* *(?:\n+|$)/",function($cap,$cfg=[],$flag=0){
				$depth=strlen($cap[1]);
				return '<h'.$depth.'>'.marked_tool::marked_inline($cap[2],$cfg).'</h'.$depth.'>';
			}],//标题
			//"nptable"=>["/^ *(\S.*\|.*)\n *([-:]+ *\|[-| :]*)\n((?:.*\|.*(?:\n|$))*)\n*/"],
			//"lheading"=>["/^([^\n]+)\n *(=|-){3,} *\n*/"],
			"blockquote"=>["/^( *>[^\n]+(\n[^\n]+)*\n*)+/",function($cap,$cfg=[],$flag=0){
				return '<blockquote>'.marked_tool::marked_all(preg_replace('/^ *> ?/',$cap[0]),$cfg,$flag).'</blockquote>';
			}],
			"list"=>["/^( *)([*+-]|\d+\.) [\s\S]+?(?:\n+(?=(?: *[-*_]){3,} *(?:\n+|$))|\n{2,}(?! )(?!\\1(?:[*+-]|\d+\.) )\n*|\s*$)/",function($cap,$cfg=[],$flag=0){
				if(in_array($cap[2], ['*','+','-'])){
					$type='ul';
				}else{
					$type='ol';
				}
				$res='';
				preg_match_all("/^( *)([*+-]|\d+\.) [^\n]*(?:\n(?!\\1(?:[*+-]|\d+\.) )[^\n]*)*/m",$cap[0],$cap);
				foreach ($cap[0] as $key => $value) {
					$value=preg_replace('/^ *([*+-]|\d+\.) +/','',$value);
					$res.='<li>'.marked_tool::marked_all($value,$cfg,$flag|0x10000).'</li>';
				}
				return '<'.$type.'>'.$res.'</'.$type.'>';
			}],//列表
			//"html"=>"/^ *(?:comment|closed|closing) *(?:\n{2,}|\s*$)/",
			"def"=>["/^ *\[([^\]]+)\]: *<?([^\s>]+)>?(?: +[\"(]([^\n]+)[\")])? *(?:\n+|$)/",function($cap,$cfg=[],$flag=0){
				//
			}],
			"table"=>["/^ *\|(.+)\n *\|( *[-:]+[-| :]*)\n((?: *\|.*(?:\n|$))*)\n*/",function($cap){
				$body='<thead><tr>';
				$header=preg_split('/ *\| */',preg_replace('/^ *| *\| *$/','',$cap[1]));
				$align=preg_split('/ *\| */',preg_replace('/^ *|\| *$/','',$cap[2]));
				$cells=explode("\n",preg_replace("/(?: *\| *)?\n$/",'',$cap[3]));
				foreach ($align as $k=>$v) {
					if(preg_match('/^ *-+: *$/',$v)){
						$align[$k]='right';
					}else if(preg_match('/^ *:-+: *$/',$v)){
						$align[$k]='center';
					}else if(preg_match('/^ *:-+ *$/',$v)){
						$align[$k]='left';
					}else{
						$align[$k]='';
					}
				}
				foreach ($header as $k=>$v) {
					$body.=($align[$k]?('<th align="'.$align[$k].'">'):'<th>').marked_tool::marked_inline($v).'</th>';
				}
				$body.='</tr></thead><tbody>';
				foreach($cells as $line) {
					$c=preg_split('/ *\| */',preg_replace('/^ *\| *| *\| *$/','',$line));
					$body.='<tr>';
					foreach ($c as $k=>$v) {
						$body.=($align[$k]?('<td align="'.$align[$k].'">'):'<td>').marked_tool::marked_inline($v).'</td>';
					}
					$body.='</tr>';
				}
				$body.='</tbody>';
				return '<table>'.$body.'</table>';
			}],//表格
			//"paragraph"=>"/^((?:[^\n]+\n?(?!hr|heading|lheading|blockquote|tag|def))+)\n*/",
			"text"=>["/^[^\n]+/",function($cap,$cfg=[],$flag=0){
				if($flag&0x10000) return marked_tool::marked_inline($cap[0],$cfg);
				return'<p>'.marked_tool::marked_inline($cap[0],$cfg).'</p>';
			}]//普通文本
		];
	}
}
marked_tool::init();
