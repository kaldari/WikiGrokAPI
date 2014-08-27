<?php
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'max_execution_time', 2000 );
ini_set( 'memory_limit', '200M' );

require_once( '../config.inc.php' );

header( 'Content-type: application/json' );
//header('Content-type: text/plain'); // FOR TESTING

function getRequest( $key , $default = '' ) {
	if ( isset ( $_REQUEST[$key] ) ) return str_replace( "\'" , "'" , $_REQUEST[$key] );
	return $default;
}

$action = getRequest( 'action' , '' );
$callback = getRequest( 'callback' );

$out = array( 'status' => 'OK' ) ;

if ( $action === 'get_potential_occupations' ) {

	$candidatesdb = new mysqli( $candidatesdb['host'], $candidatesdb['user'], $candidatesdb['pass'], $candidatesdb['dbname'] );

	$item = intval( getRequest( 'item' , 0 ) );
	if ( $item ) {
			$sql = "SELECT occupation FROM potential_occupation WHERE status IS NULL AND item = $item LIMIT 1";
			$result = $candidatesdb->query( $sql );
			if ( !$result ) die( 'There was an error running the query [' . $candidatesdb->error . '] '.$sql );
			$x = $result->fetch_array();
			if ( $x ) {
					$out['occupations'] = "$x[0]";
			} else {
					$out['occupations'] = false;
			}
	} else {
			$out['status'] = "Invalid item input: $item";
	}

} else if ( $action === 'record_answer' ) {

	$wikigrokdb = new mysqli( $wikigrokdb['host'], $wikigrokdb['user'], $wikigrokdb['pass'], $wikigrokdb['dbname'] );

	$item_id = $wikigrokdb->real_escape_string( getRequest( 'item_id' ) );
	$item = $wikigrokdb->real_escape_string( getRequest( 'item' ) );
	$occupation_id = $wikigrokdb->real_escape_string( getRequest( 'occupation_id' ) );
	$occupation = $wikigrokdb->real_escape_string( getRequest( 'occupation' ) );
	$page_name = $wikigrokdb->real_escape_string( getRequest( 'page_name' ) );
	$correct = intval( getRequest( 'correct', -1 ) );

	if ( $item_id && $occupation_id && ( $correct === 0 || $correct === 1 ) ) {
		$sql = "INSERT INTO `occupation_log` (`item_id`, `item`, `occupation_id`, `occupation`, `page_name`, `correct`, `timestamp`) VALUES ($item_id, $item, $occupation_id, $occupation, $page_name, $correct, CURRENT_TIMESTAMP)";
		$result = $wikigrokdb->query( $sql );
		if ( !$result ) die( 'There was an error running the query [' . $candidatesdb->error . '] '.$sql );
	}

} else {
	$out['status'] = "Unknown action $action" ;
}

$json = json_encode( $out );
// Use callback if JSONP request
if ( $callback ) { 
	print $callback . '(' . $json . ');';
} else { 
	print $json . "\n"; 
}
