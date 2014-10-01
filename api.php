<?php
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'max_execution_time', 2000 );
ini_set( 'memory_limit', '200M' );

require_once( '../config.inc.php' );

function getRequest( $key , $default = '' ) {
	if ( isset ( $_REQUEST[$key] ) ) return str_replace( "\'" , "'" , $_REQUEST[$key] );
	return $default;
}

/**
 * Get potential occupations for a person
 * @param int $item The Wikidata ID for the person (without Q)
 * @param mysqli $db Connection to database storing candidate information
 * @return string A comma-separated list of IDs for occupations in Wikidata (without the Q)
 */
function getPotentialOccupations( $item, $db ) {
	// The possible statuses for potential_occupation entries are NULL, DEL, NO, and DONE.
	// NULL: No decisions have been made about occupation claims for this person
	// DEL: Item has problems (article deleted, etc.)
	// NO: None of the potential occupations are appropriate
	// DONE: Occupations have been set for this person in WikiData via WikiData Game
	$sql = "SELECT occupation FROM potential_occupation WHERE status IS NULL AND item = $item LIMIT 1";
	$result = $db->query( $sql );
	if ( !$result ) die( 'There was an error running the query [' . $db->error . '] '.$sql );
	$x = $result->fetch_array();
	return $x ? $x[0] : false;
}

/**
 * Get a potential nationality for a person ('country of citizenship' in Wikidata)
 * @param int $item The Wikidata ID for the person (without Q)
 * @param mysqli $db Connection to database storing candidate information
 * @return string A single ID for a country in Wikidata (without the Q), e.g. '145'
 */
function getPotentialNationality( $item, $db ) {
	// The possible statuses for potential_nationality entries are NULL, DEL, YES, and NO.
	// NULL: No decisions have been made about the nationality claim for this person
	// DEL: Item has problems (article deleted, etc.)
	// YES: The suggested nationality is correct
	// NO: The suggested nationality is not correct
	$sql = "SELECT nationality FROM potential_nationality WHERE status IS NULL AND item = $item LIMIT 1";
	$result = $db->query( $sql );
	if ( !$result ) die( 'There was an error running the query [' . $db->error . '] '.$sql );
	$x = $result->fetch_array();
	return $x ? $x[0] : false;
}

$action = getRequest( 'action' , '' );
$callback = getRequest( 'callback' );
$out = array( 'status' => 'OK' ) ;

/* Handle various actions */

if ( $action === 'record_answer' ) {
	$wikigrokdb = new mysqli( $wikigrokdb['host'], $wikigrokdb['user'], $wikigrokdb['pass'], $wikigrokdb['dbname'] );

	$subject_id = $wikigrokdb->real_escape_string( getRequest( 'subject_id' ) );
	$subject = $wikigrokdb->real_escape_string( getRequest( 'subject' ) );
	$occupation_id = $wikigrokdb->real_escape_string( getRequest( 'occupation_id' ) );
	$occupation = $wikigrokdb->real_escape_string( getRequest( 'occupation' ) );
	$page_name = $wikigrokdb->real_escape_string( getRequest( 'page_name' ) );
	$correct = intval( getRequest( 'correct', -1 ) );
	$user_id = intval( getRequest( 'user_id', 0 ) );
	$source = $wikigrokdb->real_escape_string( getRequest( 'source' ) );
	if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
		$host = $wikigrokdb->real_escape_string( parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST ) );
	} else {
		$host = 'none';
	}

	if ( $subject_id && $occupation_id && ( $correct === 0 || $correct === 1 ) ) {
		$sql = "INSERT INTO `claim_log` (`subject_id`, `subject`, `claim_property_id`, `claim_property`, `claim_value_id`, `claim_value`, `page_name`, `correct`, `user_id`, `source`, `host`, `timestamp`) VALUES ('$subject_id', '$subject', 'P106', 'occupation', '$occupation_id', '$occupation', '$page_name', $correct, $user_id, '$source', '$host', CURRENT_TIMESTAMP)";
		$result = $wikigrokdb->query( $sql );
		if ( !$result ) die( 'There was an error running the query [' . $candidatesdb->error . '] '.$sql );
	}
} else {
	// Handle all the 'get' actions
	$item = intval( getRequest( 'item' , 0 ) );
	if ( $item ) {
		$candidatesdb = new mysqli( $candidatesdb['host'], $candidatesdb['user'], $candidatesdb['pass'], $candidatesdb['dbname'] );
		switch ( $action ) {
			case 'get_potential_occupations':
				$out['occupations'] = getPotentialOccupations( $item, $candidatesdb );
				break;
			case 'get_potential_nationality':
				$out['occupations'] = getPotentialNationality( $item, $candidatesdb );
				break;
			default:
				$out['status'] = "Unknown action $action" ;
		}
	} else {
		$out['status'] = "Invalid item input: $item";
	}
}

/* Output the results */

header( 'Content-type: application/json' );
//header('Content-type: text/plain'); // for testing

$json = json_encode( $out );

// Use callback if JSONP request
if ( $callback ) { 
	print $callback . '(' . $json . ');';
} else { 
	print $json . "\n"; 
}
