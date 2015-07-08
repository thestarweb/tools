<?php
class mail_tool{
	private $is_ok=false;
	/**
		@name string 联系人名称
		@server string 服务器
		@username string 用户名
		@password string 密码
		@charset string 设置字符集
	*/
	public function __construct($name,$server,$username,$password,$charset='utf-8'){
		require_once dirname(__FILE__).'/PHPMailer/class.phpmailer.php';
		//include dirname(__FILE__).'/PHPMailer/class.'.$type.'.php';
		$this->mail=new PHPMailer();
		$this->mail->CharSet =$charset;
		$this->host=$server;
		$this->username=$username;
		$this->password=$password;
		$this->name=$name;
	}
	public function smtp(){
		$this->mail->IsSMTP();
		//$this->mail->SMTPDebug  = 1;//
		$this->mail->Host=$this->host;
		//$this->mail->Port=465;
		$this->mail->Username=$this->username;
		$this->mail->Password=$this->password;
		$this->mail->SMTPAuth=true;
		$this->mail->SetFrom($this->username,$this->name);
		$this->mail->SMTPAuth= true;
		$this->is_ok=true;
	}
	/**
		@mail string 接收人邮箱
		@name string 接收人称呼
	*/
	public function add_geter($mail,$name=''){
		$this->mail->AddAddress($mail,$name);
	}
	/**
		@Subject staring 标题
		@body staring 正文（HTML格式）
	*/
	public function send($Subject,$body){
		if($this->is_ok){
			$this->mail->Subject=$Subject;
			$this->mail->MsgHTML($body);
			if(!$this->mail->Send()) {
				$this->error=$this->mail->ErrorInfo;
				return false;
			} else {
			 	return true;
			}
		}
	}
	/**
		下面这些部分为模板的方式准备
		使用前必须打开ob缓存
		此套函数暂时不支持嵌套使用
		连续多个开始标记以第一个为准
		连续多个结束标记，从第二个处开始报错
	*/
	private $ob_temp='';
	private $title='';
	private $start=false;
	/**
		从ob缓存获取的开始标记
		@title string 标题

		函数会自动保存之前输出的内容，故之前输出的内容不会出现在邮件中，也不会被强制刷新给客户端，而是在标记结束后再从新输出
	*/
	public function send_star($title=''){
		if($this->is_ok){
			$this->ob_temp=ob_get_contents();
			ob_clean();
			$this->title=$title;
			$this->start=true;
		}
	}/**
		从ob缓存获取的结束标记
		只有执行了这个函数，邮件才会被发出，不能认为程序结束后会自动发送！
	*/
	public function send_end(){
		if($this->start){
			$type=$this->send($this->title,ob_get_contents());
			ob_clean();
			echo $this->ob_temp;
			$this->start=false;
			return $type;
		}else{
			trigger_error('mail_tool error:在使用send_start前使用了send_end',512);
		}
	}
}