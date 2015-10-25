<?php
/*     XMLTools工具v1.2.1.0014    */
/*  版权归星星站点所有，保留所有权利  */
/*             by 星星            */
	class xml_tool{
		public $xmldoc;
		public $XPath;
		public $my_xml;
		private $save=false;
		/**
			@xml string xml文档路径
			@root string 如果文件不存在将会以此名称为根节点创建
		*/
		public function __construct($xml,$root=''){
			$this->my_xml=$xml;
			$this->xmldoc=new DOMDocument();
			$this->xmldoc->load($xml);
			$this->XPath=new DOMXPath($this->xmldoc);
		}
		/**
			@path string 符合XPath格式的路径
			return XPath节点
		*/
		public function found($path){
			return $this->XPath->query($path);
		}
		/**
			@path string 符合XPath格式的路径
			return int 节点下的元素数目
		*/
		public function how_many($path){
			return $this->found($path)->length;
		}
		/**
			@path string 符合XPath格式的路径
			return string 节点的值
		*/
		public function look($path,$index){
			return $this->found($path)->nodeValue;
		}
		/**
			@path string 符合XPath格式的父元素路径
			@pathIndex int 父元素下标
			@index string 新元素名称
			@thing string 向新元素中增加的内容
		*/
		public function add($path,$pathIndex,$index,$thing=''){
			$things=$this->xmldoc->createElement($index);
			if($thing!=""){
				$things->nodeValue=$thing;
			}
			$node=$this->found($path)->item($pathIndex);
			$node->appendChild($things);
			$this->save=true;
		}
		//这个函数用于删除标签
		//参数说明（父路径，父下标，子标签，子下标）
		//特别说明：子下标是在全XML的下标，而不是针对其父标签下的

		//目前正考虑更新此函数 参数可能发生调整 暂时不建议使用！！！
		public function del($PPath,$Pindex,$CIndex,$Cindex){
			$node=$this->found($PPath)->item($Pindex);//$node是父节点
			$child_index=$PPath."/".$CIndex;
			$child=$this->found($child_index)->item($Cindex);//这是子节点
			$node->removeChild($child);
			$this->save=true;
		}
		/**
			@path string 符合XPath格式的路径
			@index int 元素下标
			@thing string 新的内容
		*/
		public function update($path,$index,$thing){
			$node=$this->found($path);->item($index);
			$node->nodeValue=$thing;
			$this->save=true;
		}
		//析构函数 在程序运行结束后更新磁盘上的物理文件
		public function __destruct(){
			this->save&&$this->xmldoc->save($this->my_xml);
		}
	}
?>