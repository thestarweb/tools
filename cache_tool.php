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
	//用于存放缓存数据
	private $data=array(
		'end_time'=>0,//缓存结束时间
		'body'=>''//缓存主体
	);
	private $file;
	private $time;
	private $ob_temp;//ob缓存转储
	/**
	@cache_file 缓存文件
	*/
	public function __construct($cache_file,$cache_time=3600){
		if(file_exists($cache_file)) list($this->data['end_time'],$this->data['body'])=explode("\n", file_get_contents($cache_file),2);
		$this->file=$cache_file;
		$this->time=$cache_time;
	}
	public function start_page_cache(){
		if(time()<$this->data['end_time']){
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
		$this->data['end_time']=$this->time+time();
		$this->save();
	}
	private function save(){
		file_put_contents($this->file,$this->data['end_time']."\n".$this->data['body']);
	}
}