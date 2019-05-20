<?php

class TableInfo {
	public $baseGetAllSql;
	public $path;
	public $filters;

	public function __construct($tableDef) {
		$tableName = $tableDef['table'];
		$this->baseGetAllSql = $tableDef['sql'] ?? "select * from $tableName";
		$this->path = '/'.$tableDef['path'];
		$this->filters = $tableDef['filters'] ?? array();
	}
}
