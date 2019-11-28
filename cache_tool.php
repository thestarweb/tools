<?php
/**
	例子:
		echo 'some html';
		if($c->start_page_cache()){
			echo 'some thing need to cache';
			$c->end_page_cache();
		}
		echo 'some html';
*/
class cache_tool{
	const AUTO=0;
	const TIMEOUT=1;
	const CHANGE=2;
	//用于存放缓存数据
	private $data;
	private $file;
	private $flag;
	private $t_type;
	private $ob_temp;//ob缓存转储
	/**
	@cache_file 缓存文件
	*/
	public function __construct($cache_file,$cache_flag=3600,$t_type=0){
		if($t_type==self::AUTO){
			if($cache_flag<3600000&&$cache_flag!=0){
				$t_type=self::TIMEOUT;
			}else{
				$t_type=self::CHANGE;
			}
		}
		if(file_exists($cache_file)) $this->data=unserialize(file_get_contents($cache_file));
		//list($this->data['cache_flag'],$this->data['body'])=explode("\n", file_get_contents($cache_file),2);
		$this->file=$cache_file;
		$this->flag=$cache_flag;
		$this->t_type=$t_type;
	}
	public function start_page_cache(){
		if($this->check()){
			echo $this->data['body'];
			return false;
		}else{
			$this->ob_temp=ob_get_contents();
			ob_clean();
			return true;
		}
	}
	public function end_page_cache(){
		$temp=ob_get_contents();
		ob_clean();
		echo $this->ob_temp,$temp;
		$this->data['body']=$temp;
		$this->data['cache_flag']=$this->flag==self::TIMEOUT?time():$this->flag;
		$this->save();
	}
	private function check(){
		if(!$this->data) return false;
		switch ($this->t_type) {
			case self::TIMEOUT:
				return time()<$this->data['cache_flag']+$this->flag;
			case self::CHANGE:
				return $this->data['cache_flag']===$this->flag;
			default:
				return false;
				break;
		}
	}
	private function save(){
		var_dump(1111);
		if(!file_exists(dirname($this->file))) mkdir(dirname($this->file));
		file_put_contents($this->file,serialize($this->data));
	}
}