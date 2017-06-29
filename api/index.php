<?php
include "../config.php";
include "api.php";
try {
	$API = new unlinked_API($db);
	$API->ClientAction();
	$API->respond();
} catch (unlinked_API_Exception $e) {
	header('Access-Control-Allow-Origin: *');
	$callback = isset($_GET['callback']) ? preg_replace('/[^a-z0-9$_]/si', '', $_GET['callback']) : false;
	header('Content-Type: ' . ($callback ? 'application/javascript' : 'application/json') . ';charset=UTF-8');
	echo ($callback ? $callback . '(' : '') . json_encode($e->API_Error()) . ($callback ? ')' : '');
} catch (Controller_Exception $e) {
	$response = array(
		"error" => 1,
		"message" => $API->errorList[$e->code],
		"id" => $e->code
	);
	header('Access-Control-Allow-Origin: *');
	$callback = isset($_GET['callback']) ? preg_replace('/[^a-z0-9$_]/si', '', $_GET['callback']) : false;
	header('Content-Type: ' . ($callback ? 'application/javascript' : 'application/json') . ';charset=UTF-8');
	echo ($callback ? $callback . '(' : '') . json_encode($response) . ($callback ? ')' : '');
}
?>