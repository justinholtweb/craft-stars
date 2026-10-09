<?php
/**
 * Runs one GraphQL query against an in-memory schema and prints the result as JSON.
 *
 *     php tests/integration/_gql.php <base64 JSON scope array> <base64 query>
 *
 * checks.php calls this once per schema: Craft's GraphQL type registry can't be rebuilt for a
 * second schema in the same process (flushCaches() leaves duplicate `QueryArgument` types behind),
 * so each schema gets a fresh PHP process.
 */

$root = getcwd();
require $root . '/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

Craft::$app->getPlugins()->loadPlugins();
Craft::$app->getConfig()->getGeneral()->enableGraphqlCaching = false;

$scope = json_decode(base64_decode($argv[1] ?? ''), true) ?: [];
$query = base64_decode($argv[2] ?? '');

$result = Craft::$app->getGql()->executeQuery(new craft\models\GqlSchema(['name' => 'Stars check', 'scope' => $scope, 'uid' => craft\helpers\StringHelper::UUID()]), $query);

echo json_encode($result);
