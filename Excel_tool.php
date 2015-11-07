<?php
class Excel_tool{
	private $file;
	private $o;
	private $Excel;
	public function __construct($file,$read_only=true){
		require_once dirname(__FILE__).'/PHPExcel/PHPExcel.php';
		//require_once dirname(__FILE__).'/PHPExcel/IOFactory.php';
		$this->o=PHPExcel_IOFactory::createReader('Excel5');  
    $this->o->setReadDataOnly($read_only);
    //$xlsReader->setLoadSheetsOnly(true);
    $this->Excel=$this->o->load($file);
	}
	public function get_cell($sheet,$cell){
		return $this->Excel->getSheet($sheet)->getCell($cell);
	}
	public function read_cell($sheet,$cell){
		return $this->get_cell($sheet,$cell)->getValue();
	}
}