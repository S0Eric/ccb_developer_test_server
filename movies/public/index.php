<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';
require '../src/TableInfo.php';

$app = new \Slim\App;
$container = $app->getContainer();

$container['db'] = function($c) {
    $database = $user = $password = "sakila";
    $host = "localhost"; //"mysql";

    $pdo = new PDO("mysql:host={$host};dbname={$database};charset=utf8", $user, $password);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	return $pdo;
};

$app->get('/xmovies', function (Request $request, Response $response, array $args) {
    $db = $this->get('db');
	$stm = $db->prepare('select * from film');
	$stm->execute();
	$data = $stm->fetchAll();
    return $response->withJson($data);
});

// Create a route for each main table 
$apiJsonStr = file_get_contents("../api.json");
$apiConfig = json_decode($apiJsonStr, true);
foreach ($apiConfig['tables'] as $tableDef) {
	$tableInfo = new TableInfo($tableDef);
	$app->get($tableInfo->path, function (Request $request, Response $response, array $args) use ($app, $tableInfo) {
		$queryParams = $request->getQueryParams();
		$sqlArgs = array();
		$sql = $tableInfo->baseGetAllSql;
		$sqlKwd = 'where';
		$pIdx = 0;
		foreach ($tableInfo->filters as $filter) {
			$filterParam = $filter['param'];
			$filterVal = $queryParams[ $filterParam ] ?? NULL;
			if (isset($filterVal)) {
				$filterField = $filter['field'] ?? $filterParam;
				$filterMatchType = $filter['match-type'] ?? 'equals';
				$pName = ":p$pIdx";
				$pIdx += 1;
				if (\strcasecmp($filterMatchType, 'contains') == 0) {
					$sql .= " $sqlKwd $filterField like $pName";
					$sqlArgs[$pName] = '%'.$filterVal.'%';
				}
				else {
					$sql .= " $sqlKwd $filterField = $pName";
					$sqlArgs[$pName] = $filterVal;
				}
				$sqlKwd = 'and';
			}
		}
		//return $sql."\n".var_export($sqlArgs, true);
		$db = $this->get('db');
		$stm = $db->prepare($sql);
		$stm->execute($sqlArgs);
		$data = $stm->fetchAll();
		return $response->withJson($data);
	});


}
 
$app->run();
