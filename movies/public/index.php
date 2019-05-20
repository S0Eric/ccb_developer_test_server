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

//$getAll = function (Request $request, Response $response, array $args) {
//    $db = $this->get('db');

//	$stm = $db->prepare('select * from film');
//	$stm->execute();
//	$data = $stm->fetchAll();
//    return $response->withJson($data);
//}

$apiJsonStr = file_get_contents("../api.json");
$apiConfig = json_decode($apiJsonStr, true);
foreach ($apiConfig['tables'] as $tableDef) {
	$tableInfo = new TableInfo($tableDef);
	$app->get($tableInfo->path, function (Request $request, Response $response, array $args) use ($tableInfo) {
		$db = $this->get('db');
		$stm = $db->prepare($tableInfo->baseGetAllSql);
		$stm->execute();
		$data = $stm->fetchAll();
		return $response->withJson($data);
	});
}
 
$app->run();
