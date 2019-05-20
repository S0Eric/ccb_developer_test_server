<?php

// Represents the settings for a specific table defined in the API config file.
class TableInfo {
    public $tableName;
	public $baseGetAllSql;
	public $path;
	public $filters;
    public $detailPath;
    public $children;

	public function __construct($tableDef) {
		$this->tableName = $tableDef['table'];
		$this->baseGetAllSql = $tableDef['sql'] ?? "select * from {$this->tableName}";
		$this->path = '/'.$tableDef['path'];
		$this->filters = $tableDef['filters'] ?? array();
        $this->detailPath = '/'.($tableDef['detail-path'] ?? $this->tableName).'/{row_id}';
        $this->children = $tableDef['children'] ?? array();
	}
}
