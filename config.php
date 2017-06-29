<?php
/* database */
$db["host"] 					= "localhost";
$db["port"] 					= 6033;
$db["user"] 					= "moh";
$db["pwd"] 						= "niggers";
$db["schema"] 					= "unlinked";
/* site */
$UN_LOCALE						= "enUS";
$UN_TITLE						= "unlinked"; // used in page title
define("UN_SHOW_ERRORS",			true);
$root=pathinfo($_SERVER["SCRIPT_FILENAME"]);
define("API_KEY", 				"<YOUR GOOGLE/YOUTUBE API KEY>");
define("API_ENDPOINT", 			"https://www.googleapis.com/youtube/v3");
define("BASE_FOLDER", 			basename($root["dirname"]));
define("BASE_POJECT_FOLDER", 	"/unlinked");
define("SITE_ROOT",    			realpath(dirname(__FILE__)));
define("QUEUE_INPUT", 			SITE_ROOT . "/queueserver-input");
if ( !defined("STDIN") ) {
	define("SITE_URL", 			"http://".$_SERVER["HTTP_HOST"]."/".BASE_POJECT_FOLDER);
} else {
	define("SITE_URL", 			"/".BASE_POJECT_FOLDER);
}
?>