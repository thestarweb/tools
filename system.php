<?php
	class system{
		const VISION=18;
		private $is_phone;//是否为手机版
		private static $self_obj=null;
		private $namespace='';
		public static function get_system(){
			return self::$self_obj;
		}
		private $servers=[];
		private $cfgs=array(//默认的配置，可在ini文件中重写
			'db_type'=>'mysql',//数据库类型
			'db_server'=>'127.0.0.1',//数据库服务器地址
			'db_username'=>'root',//数据库用户名
			'db_password'=>'root',//数据库密码
			'db_name'=>'web',//数据库明
			'db_prefix'=>'',//表前缀
			'tools_dir'=>'../tools',//工具类文件夹位置
			'servers_dir'=>'./servers',//业务逻辑类文件夹位置
			'controls_dir'=>'./control',//控制器类文件夹位置
			'views_dir'=>'./view',//模板位置
			'plugin_dir'=>'./plugin',//插件文件夹位置
			'cache_dir'=>'./cache',//插件文件夹位置
			'lang_dir'=>'./lang',//语言文件夹位置
			'lang_default'=>'zh-cn',//默认语言
			'lang_list'=>'zh-cn',//有效语言包
			'imgs_dir'=>'./img/',//图片文件夹位置
			'imgs_url'=>'./img',//图片文件夹web访问位置
			'styles_url'=>'./style',//样式文件web访问位置
			'root_use'=>'index',//文件夹相对路径相对于那个文件
			'my_script_path'=>'/myScript2.js',//核心脚本库web访问url
			'start_session'=>1,
			'has_CDN'=>0,
			'off_info'=>'',
			'allow_PCViewInMobile'=>'0',
			'debug'=>0
		);//用于存放配置文件
		public $lang_name=[
			'zh-cn'=>'简体中文',
			'zh-tw'=>'繁體中文',
			'en-uk'=>'english',
		];
		private $_lang=[];
		private $lang_type;
		public function __construct($ini='./cfg.ini',$sfc=''){
			ob_start();
			header('charset: utf-8');
			header('Content-Type: text/html;charset=utf-8');
			header('server: star-server');
			header('X-Powered-By: star-framework');
			date_default_timezone_set('PRC');
			if(strstr($_SERVER['HTTP_USER_AGENT'],'<')||strstr($_SERVER['REMOTE_ADDR'],'<')){
				echo '<center><h1>请求参数存在恶意字符串，已经终止程序执行！</h1><hr/>星星站点框架</center>';
				exit;
			}
			$ftime=0;
			if($sfc){
				require_once $sfc;
				$ftime=filemtime($sfc);
			}
			
			self::$self_obj||define('URLROOT',$this->dir(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']))));
			$this->load_cfg($ini,$ftime);//载入配置
			
			if(isset($_GET['phone'])){
				setcookie('phone',($this->is_phone=$_GET['phone']?1:0),0,URLROOT);
			}else{
				if(isset($_COOKIE['phone'])){
					$this->is_phone=$_COOKIE['phone'];
				}else{
					setcookie('phone',$this->is_phone=isset($_SERVER['HTTP_X_REQUESTED_WITH'])||stripos($_SERVER['HTTP_USER_AGENT'],'Mobile'),0,URLROOT);
				}
			}
			if(isset($_GET['lang'])&&in_array($_GET['lang'],$this->cfgs['lang_list'])){
				setcookie('lang',($this->lang_type=$_GET['lang']),0,URLROOT);
			}else{
				if(isset($_COOKIE['lang'])&&in_array($l=$_COOKIE['lang'],$this->cfgs['lang_list'])){
					$this->lang_type=$_COOKIE['lang'];
				}else{
					$this->lang_type=$this->cfgs['lang_default'];
				}
			}
			if(!self::$self_obj){
				set_error_handler(array($this,'for_error'));//注册故障处理函数
				if($this->cfgs['start_session']){
					session_start();
				}
			}

			spl_autoload_register(array($this,'load_class'),E_ALL);//类的自动加载
			if($this->cfgs['off_info']){
				echo $this->cfgs['off_info'];
				exit;
			}
			if(function_exists('loaded_ok')) loaded_ok($this);

			if(isset($_SERVER['CONTENT_TYPE'])&&strstr($_SERVER['CONTENT_TYPE'],'application/json')){
				$_POST=json_decode(file_get_contents('php://input'),true);
			}
			
			
			if(!self::$self_obj){//首次创建后URL解析，非首次创建为子系统 不自动打开页面
				self::$self_obj=$this;
				list($path)=explode('?',$_SERVER['REQUEST_URI']);
				$temp=strlen(URLROOT);
				$c=explode('/',substr($path,$temp),3);
				$this->show($c[0],isset($c[1])?$c[1]:'index',isset($c[2])?$c[2]:'');
			}
		}
		//自动加载类的方法
		public function load_class($classname){///var_dump($classname,111);exit;
			$namespace=substr($classname,0,strrpos($classname,'\\'));
			if($namespace==""){
				if(file_exists($this->cfgs['tools_dir'].$classname.'.php')){
					include_once $this->cfgs['tools_dir'].$classname.'.php';
					return;
				}
			}else{
				$classname=substr($classname,strrpos($classname,'\\')+1);
			}
			//var_dump($classname,$namespace);exit;
			//echo $classname;exit;
			if($namespace!=$this->namespace){
				return;
			}
			//echo $classname;exit;
			if(strpos($classname,'control')&&file_exists($this->cfgs['controls_dir'].$classname.'.php')) include_once $this->cfgs['controls_dir'].$classname.'.php';
			elseif(strpos($classname,'server')&&file_exists($this->cfgs['servers_dir'].$classname.'.php')) include_once $this->cfgs['servers_dir'].$classname.'.php';
		}
		//载入配置（ini文件或已处理过生成的temp文件）
		private function load_cfg($pass='./cfg.ini',$ftime){
			$ctime=file_exists($pass)?filemtime($pass):0;
			if(file_exists($pass.'.temp')){
				$arr=unserialize(file_get_contents($pass.'.temp'));
				if($arr['time']>=$ctime&&$arr['time']>=$ftime&&$arr['time']>=filemtime(__FILE__)){//文件没有修改
					$this->cfgs=$arr['cfgs'];
					return;
				}
			}
			$ctime&&$this->read_ini($pass);
			$this->rewrite_cfg();
			file_put_contents($pass.'.temp',serialize(array('time'=>time(),'cfgs'=>$this->cfgs)));
		}
		//读取配置文件
		private function read_ini($pass){
			$fp=fopen($pass,'r');//打开文件
			$line=0;//行变量
			while($str=fgets($fp)){//读取文件
				$line++;//每读取一行，行变量自增1
				
				//过滤空格，制表符以及注释（仅支持单行用#注释）
				$cfg='';
				for($i=0;($char=substr($str,$i,1))!==false;$i++){
					if($char=='#'){
						break;
					}elseif($char=="\t"||$char==' '||$char=="\n"||$char=="\r"){
						continue;
					}
					$cfg.=$char;
				}
				//检查是否有实际内容
				if($cfg){
					@list($k,$v)=explode("=",$cfg,2);
					//是否是有效地配置
					if(array_key_exists($k,$this->cfgs)){
						$this->cfgs[$k]=$v;
					}elseif(stripos($k,'p_')===0){
						$this->cfgs[$k]=$v;
					}else{
						throw new cfg_error($k,$line,6);
					}
				}
			}
			fclose($fp);
		}
		
		//进一步解析
		private function rewrite_cfg(){
			/*if($this->cfgs['root']=='use_server_dir'){
				$this->cfgs['root']=dirname(__FILE__);
			}elseif($this->cfgs['root']=='use_index_dir'){
				die('暂不支持用index文件做根目录');
			}*/
			switch($this->cfgs['root_use']){
				case 'index':
				$r=dirname($_SERVER['SCRIPT_FILENAME']).'/';
				break;
				case 'system':
				$r=dirname(__FILE__).'/';
				break;
				default:
				$r='';
			}
			$this->cfgs['root']=$r;
			$this->cfgs['imgs_url']=$this->get_full_URL($this->cfgs['imgs_url']);
			$this->cfgs['styles_url']=$this->get_full_URL($this->cfgs['styles_url']);
			$this->cfgs['views_dir']=$this->full_path($this->dir($this->cfgs['views_dir']));
			$this->cfgs['tools_dir']=$this->full_path($this->dir($this->cfgs['tools_dir']));
			$this->cfgs['servers_dir']=$this->full_path($this->dir($this->cfgs['servers_dir']));
			$this->cfgs['controls_dir']=$this->full_path($this->dir($this->cfgs['controls_dir']));
			$this->cfgs['plugin_dir']=$this->full_path($this->dir($this->cfgs['plugin_dir']));
			$this->cfgs['cache_dir']=$this->full_path($this->dir($this->cfgs['cache_dir']));
			$this->cfgs['lang_dir']=$this->full_path($this->dir($this->cfgs['lang_dir']));
			$this->cfgs['imgs_dir']=$this->full_path($this->dir($this->cfgs['imgs_dir']));
			$this->cfgs['lang_list']=explode(',',strtolower($this->cfgs['lang_list']));
			//var_dump($this->cfgs);exit;
		}
		//获取用户IP地址（解决有无CND切换造成IP地址记录的一些问题）
		public function uip(){
			if($this->cfgs['has_CDN']&&$_SERVER['HTTP_X_FORWARDED_FOR']){
				list($i)=explode(',',$_SERVER['HTTP_X_FORWARDED_FOR']);
				return $i;
			}else{
				return $_SERVER['REMOTE_ADDR'];
			}
		}
		
		//获取完整路径
		public function full_path($path){
			if(PHP_OS=='WINNT'){
				if(substr($path,1,2)==':/'){
					return $path;
				}
			}elseif(substr($path,0,1)=='/'){
				return $path;
			}
					//拼接
			return $this->cfgs['root'].$path;
		}
		//强制dir增加结束符
		public function dir($path){
			if(substr($path,-1,1)!='/'){
				$path.='/';
			}
			return $path;
		}
		//获取完整URL
		public function get_full_URL($path){
			if(substr($path,0,1)!='/'&&substr($path,0,7)!='http://'&&substr($path,0,8)!='https://'){
				//拼接
				$path=URLROOT.$path;
			}
			if(substr($path,-1,1)!='/'){
				$path.='/';
			}
			return $path;
		}
		
		//展示页面
		public function show($server='index',$function='index',$c){
			$obj_name=($server?$server:'index').'_control';
			if($this->namespace)$obj_name='\\'.$this->namespace.'\\'.$obj_name;
			if(class_exists($obj_name)){
				$obj=new $obj_name($this);
				$function_name=($function!==''?$function:'index').'_page';
				if(is_callable(array($obj,$function_name))){
					try{
						call_user_func(array($obj,$function_name),$this,$c);
					}catch(Exception $e){
						$this->for_error($e->getCode(),$e->getMessage(),$e->getFile(),$e->getLine(),null,$e->getTrace());
					}
					return;
				}
			}
			header('HTTP/1.1 404 Not Found');
			header("status: 404 Not Found");
			//require_once $this->get_view('error/404');
		}
		//展示头部（减少重复的html头部）
		public function show_head($title,$css=array(),$keyword=''){
			if(!isset($_POST['ajax'])){
				require_once $this->get_view('head');
			}else{
				$this->title=$title;
			}
		}
		//展示尾部（减少重复的html尾部）
		public function show_foot(){
			if(!isset($_POST['ajax'])){
				include $this->get_view('foot');
			}else{
				$this->show_json(array('title'=>$this->title?$this->title:'无标题','body'=>str_replace('%','%25',ob_get_contents())));
			}
		}
		//获取ini配置
		public function ini_get($name){
			return isset($this->cfgs[$name])?$this->cfgs[$name]:'';
		}
		//server对象获取位置
		public function server($server_name){
			if(!isset($this->servers[$server_name])){
				if($this->namespace)$server_name='\\'.$this->namespace.'\\'.$server_name.'_server';
				$this->servers[$server_name]=new $server_name($this);
			}
			return $this->servers[$server_name];
		}
		public function url_addget($name,$value,$oldurl=''){
			$oldurl||$oldurl=$_SERVER['REQUEST_URI'];
			if(strstr($oldurl,'?')){
				list($u,$c)=explode('?',$oldurl,2);
				$nurl=preg_replace('/(^|&)'.$name.'=.*?($|&)/','&'.$name.'='.$value.'&',$c);//试着查找之前是否存在这个参数并替换
				if($nurl==$c) $nurl.='&'.$name.'='.$value;//如果字符串没改变说明原字符串没有这个参数，直接添加个
				return $u.'?'.$nurl;
			}else{
				return $oldurl.'?'.$name.'='.$value;
			}
		}
		public function get_view($name,$use_phone=true){
			if($this->is_phone&&$use_phone){
				$file=$this->cfgs['views_dir'].$name.'.phone.html';
				if(file_exists($file)) return $file;
				$file=$this->cfgs['views_dir'].$name.'_phone.html';
				if(file_exists($file)) return $file;
				if(!$this->cfgs['allow_PCViewInMobile']){
					return $this->cfgs['views_dir'].'phone_nofile.html';
				}
			}
			return $this->cfgs['views_dir'].$name.'.html';
		}
		public function on_phone(){
			return $this->is_phone;
		}
		//提供两种加载插件的方法 视情况选择
		public function load_plugin_html($p){
			$file=$this->cfgs['plugin_dir'].'bin/'.$p.'.html';
			if(file_exists($file))echo file_get_contents($file);
		}
		public function load_plugin_php($p,$c){
			$file=$this->cfgs['plugin_dir'].'bin/'.$p.'.php';
			if(file_exists($file))include $file;
			return $c;
		}
		public function load_lang($p){
			$file=$this->cfgs['lang_dir'].$this->lang_type.'/'.$p.'.lang';
			if(file_exists($file)){
				$res=include $file;
				$this->_lang[$p]=isset($l)?$l:$res;
			}else{
				$this->_lang[$p]=[];
			}
		}
		public function lang($p,$name,$s=[]){
			if(!array_key_exists($p,$this->_lang)){
				$this->load_lang($p);
			}
			if(isset($this->_lang[$p][$name])){
				$str=$this->_lang[$p][$name];
				foreach($s as $k=>$v){
					$str=str_replace(('%'.$k),$v,$str);
				}
				return $str;
			}else{
				return $p.'.'.$name;
			}
		}
		public function lang_list(){
			$l=scandir($this->cfgs['lang_dir']);
			$list=[];
			foreach($l as $v){
				if(in_array($v,$this->cfgs['lang_list'])){
					$list[$v]=$this->lang_name[$v];
				}
			}
			return $list;
		}
		//数据库连接
		private  $link;
		public function db(){
			if(!$this->link){
				$this->link=new pdo_mysql($this->cfgs['db_server'],$this->cfgs['db_username'],$this->cfgs['db_password'],$this->cfgs['db_name'],$this->cfgs['db_prefix']);
				$this->link->system=$this;
			}
			return $this->link;
		}
		private $_mail;
		public function mail(){
		//
		}
		public function show_json($arr){
			ob_clean();
			if(is_array($arr)){
				$arr['server_version']=VERSION;
				echo json_encode($arr);
			}
			exit;
		}
		public function show_error($message){
			ob_clean();
			print_r($message);
			exit;
		}
		/**
			生成随机字符串
			@lens int 需要的长度
			return string 随机字符串
		*/
		public function rand($lens){
			$lens+=0;
			if($lens<1){
				return '';
			}
			$str='ABCDEGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
			for($time=$lens%10;$time>0;$time--) $str.=$str;
			return substr(str_shuffle($str),0,$lens);
		}
		/**
			隐藏IP地址后一段以便输出时不会造成一些信息泄露
			@ip string
			return string
		*/
		public function protect_ip($ip){
			return substr($ip,0,strrpos($ip,'.')).'.*';
		}
		//故障处理函数
		public function for_error($errno,$errstr,$errfile,$errline,$obj,$trace=null){
			if($this->cfgs['debug']){
				ob_clean();
				echo '错误'.$errno.':'.$errstr.'<br/>';
				echo '<table>';
				$array=$trace?$trace:debug_backtrace();
				//unset($array[0]);
				//var_dump($obj);exit;
				$call=null;
				foreach($array as $v){
					if(isset($v['file'])){
						echo '<tr><td>'.$v['file'].'</td><td>'.(isset($v['line'])?$v['line']:'').'</td><td>'.(isset($v['class'])?$v['class'].$v['type']:'').$v['function'].' '.$call.'</td></tr>';
						$call=null;
					}else{
						$call=(isset($v['class'])?$v['class'].$v['type']:'').$v['function'];
					}
					//isset($v['file'])||var_dump($v);
				}
				echo '</table>';
				exit;
			}else{
				$file=explode('\\',$errfile);
				$file=explode('/',array_pop($file));
				$fp=fopen('./error.log','a');
				fwrite($fp,"\r\n".serialize(array('time'=>date('Y-m-d h:m:s'),'file'=>array_pop($file),'line'=>$errline,'info'=>$errstr,'page'=>$_SERVER['REQUEST_URI']))."\r\n");
				fclose($fp);
				require $this->get_view('error/500');
			}
		}
	}
	
	//故障处理类
	class cfg_error extends Exception{
		// 重定义构造器使 message 变为必须被指定的属性
		public function __construct($k,$line, $code = 0) {
			// 自定义的代码
			$this->k=$k;
			$this->line=$line;
			// 确保所有变量都被正确赋值
			parent::__construct('警告：无法识别“'.$k.'”在配置文件第'.$line.'行', $code);
		}
	
		// 自定义字符串输出的样式
		public function __toString() {
			return __CLASS__ . '警告：无法识别“'.$this->k.'”在配置文件第<b>'.$this->line.'</b>行';
		}
	
		public function customFunction() {
			echo "A Custom function for this type of exception\n";
		}
	}
?>
