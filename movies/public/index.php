<?php
// Set development mode flag.
$isDevEnv = \strcasecmp(\getenv("ENVIRONMENT"), 'DEVELOPMENT') == 0;

// Report all errors if we are in dev mode.
if ($isDevEnv) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';
require '../src/TableInfo.php';

$slimConfig = [ 'settings' => [ 'displayErrorDetails' => $isDevEnv ] ];
$container = new \Slim\Container($slimConfig);
$app = new \Slim\App($container);

$container['db'] = function($c) {
    $database = $user = $password = "sakila";
    $host = "mysql"; // Change to localhost when running using 'PHP -S localhost:port'.

    $pdo = new PDO("mysql:host={$host};dbname={$database};charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

// Load and parse the API config file.
$apiJsonStr = \file_get_contents("../api.json");
if (!$apiJsonStr)
    throw new Exception('Error opening API config file', 500);
$apiConfig = \json_decode($apiJsonStr, true);
if (!$apiConfig)
    throw new Exception('Invalid API config file', 500);

// Create a list and detail route for each main table in the API config file.
foreach ($apiConfig['tables'] as $tableDef) {
    // Load the table config data into a TableInfo instance.
    $tableInfo = new TableInfo($tableDef);

    // Add a route for listing and optionally filtering data for this table.
    $app->get($tableInfo->path, function (Request $request, Response $response, array $args) use ($tableInfo) {
        // For all valid query params, modify the SQL and add a named parameter with the query value.
        $reqParams = $request->getQueryParams();
        $sql = $tableInfo->baseGetAllSql;
        $sqlKwd = 'where';
        $sqlParams = array();
        $namedParIdx = 0;
        foreach ($tableInfo->filters as $filter) {
            $filterParam = $filter['param'];                    // Name of query parameter.
            $filterVal = $reqParams[ $filterParam ] ?? NULL;    // Value of query parameter.
            if (isset($filterVal)) {
                $filterField = $filter['field'] ?? $filterParam;
                $filterMatchType = $filter['match-type'] ?? 'equals';
                $pName = ":p$namedParIdx";
                $namedParIdx += 1;
                if (\strcasecmp($filterMatchType, 'contains') == 0) {
                    $sql .= " $sqlKwd $filterField like $pName";
                    $sqlParams[$pName] = '%'.$filterVal.'%';
                }
                else {
                    $sql .= " $sqlKwd $filterField = $pName";
                    $sqlParams[$pName] = $filterVal;
                }
                $sqlKwd = 'and';
            }
        }
        //return $sql."\n".var_export($sqlParams, true);

        // Execute the query and return the data as JSON.
        $db = $this->get('db');
        $stm = $db->prepare($sql);
        $stm->execute($sqlParams);
        $data = $stm->fetchAll();
        return $response->withJson($data);
    });

    // Add a route for getting details for a single row in this table.
    $app->get($tableInfo->detailPath, function (Request $request, Response $response, array $args) use ($tableInfo) {
        // Get the row's ID from the path and build the SQL that selects it.
        $rowId = intval($args['row_id']);
        $sql = "select * from {$tableInfo->tableName} where {$tableInfo->tableName}_id = :row_id LIMIT 1";

        // Query the single row.
        $db = $this->get('db');
        $stm = $db->prepare($sql);
        $stm->execute(array(":row_id" => $rowId));
        $data = $stm->fetch();

        // If the row wasn't found then return a 404 response.
        if (!$data)
            return $response->withStatus(404);

        // Add children collections.
        foreach ($tableInfo->children as $child) {
            $childTable = $child['table'];
            $childFieldName = $child['name'] ?? $childTable.'s';
            $childSql = $child['sql'];
            $childStm = $db->prepare($childSql);
            $childStm->execute(array(":parent_id" => $rowId));
            $childData = $childStm->fetchAll();
            if ($childData)
                $data[$childFieldName] = $childData;
        }

        // Return the data as JSON.
        return $response->withJson($data);
    });
}
 
$app->run();
