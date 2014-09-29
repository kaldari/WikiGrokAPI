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

function getPotentialOccupations( $item ) {
	$sql = "SELECT occupation FROM potential_occupation WHERE status IS NULL AND item = $item LIMIT 1";
	$result = $candidatesdb->query( $sql );
	if ( !$result ) die( 'There was an error running the query [' . $candidatesdb->error . '] '.$sql );
	$x = $result->fetch_array();
	if ( $x ) {
		return $x[0];
	} else {
		return false;
	}
}

$action = getRequest( 'action' , '' );
$callback = getRequest( 'callback' );

$out = array( 'status' => 'OK' ) ;

$candidatesdb = new mysqli( $candidatesdb['host'], $candidatesdb['user'], $candidatesdb['pass'], $candidatesdb['dbname'] );

switch ( $action ) {

	case 'get_potential_occupations':
		$item = intval( getRequest( 'item' , 0 ) );
		if ( $item ) {
				$out['occupations'] = getPotentialOccupations( $item );
		} else {
				$out['status'] = "Invalid item input: $item";
		}
		break;

	case 'record_answer':
		$wikigrokdb = new mysqli( $wikigrokdb['host'], $wikigrokdb['user'], $wikigrokdb['pass'], $wikigrokdb['dbname'] );

		$subject_id = $wikigrokdb->real_escape_string( getRequest( 'subject_id' ) );
		$subject = $wikigrokdb->real_escape_string( getRequest( 'subject' ) );
		$occupation_id = $wikigrokdb->real_escape_string( getRequest( 'occupation_id' ) );
		$occupation = $wikigrokdb->real_escape_string( getRequest( 'occupation' ) );
		$page_name = $wikigrokdb->real_escape_string( getRequest( 'page_name' ) );
		$correct = intval( getRequest( 'correct', -1 ) );
		$user_id = intval( getRequest( 'user_id', 0 ) );
		$source = $wikigrokdb->real_escape_string( getRequest( 'source' ) );
		$host = $wikigrokdb->real_escape_string( parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST ) );

		if ( $subject_id && $occupation_id && ( $correct === 0 || $correct === 1 ) ) {
			$sql = "INSERT INTO `claim_log` (`subject_id`, `subject`, `claim_property_id`, `claim_property`, `claim_value_id`, `claim_value`, `page_name`, `correct`, `user_id`, `source`, `host`, `timestamp`) VALUES ('$subject_id', '$subject', 'P106', 'occupation', '$occupation_id', '$occupation', '$page_name', $correct, $user_id, '$source', '$host', CURRENT_TIMESTAMP)";
			$result = $wikigrokdb->query( $sql );
			if ( !$result ) die( 'There was an error running the query [' . $candidatesdb->error . '] '.$sql );
		}
		break;

	default:
		$out['status'] = "Unknown action $action" ;
}

// Output the results
$json = json_encode( $out );
// Use callback if JSONP request
if ( $callback ) { 
	print $callback . '(' . $json . ');';
} else { 
	print $json . "\n"; 
}
