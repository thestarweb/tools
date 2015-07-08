<?php
class fenye_tool{
	public $out='';
	public $now_page=' {page} ';
	/**
		@ max 最大页
		@ now 当前页
		@ url 连接地址
	*/
	public function __construct($max,$now,$url=''){
		$this->max=$max;
		$this->now=$now;
		$this->out=' <a href="'.$url.'{page}">[{page}]</a> ';
	}
	/**
		return 分页数据（网页下方的那些按钮）
	*/
	public function get(){
		//var_dump($this->max==0,$this->max<$this->now,$this->now<1);exit;
		if($this->max==0||$this->max<$this->now||$this->now<1) return '';
		$re='';
		$startpage=$this->now<5?1:$this->now-4;
		$endpage=$this->now<$this->max-5?$this->now+5:$this->max;
		for($i=$startpage;$i<=$endpage;$i++){
			if($i==$this->now){
				$re.=str_replace('{page}',$i,$this->now_page);
			}else{
				$re.=str_replace('{page}',$i,$this->out);
			}
		}
		return $re."($this->now/$this->max)";
	}
}