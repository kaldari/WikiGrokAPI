<?php
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'max_execution_time', 2000 );
ini_set( 'memory_limit', '200M' );

require_once( '../config.inc.php' );

header( 'Content-type: application/json' );
//header('Content-type: text/plain'); // FOR TESTING

$wikigrokdb = new mysqli( $wikigrokdb['host'], $wikigrokdb['user'], $wikigrokdb['pass'], $wikigrokdb['dbname'] );
$action = get_request( 'action' , '' ) ;
$item = intval( get_request( 'item' , 0 ) ) ;

$out = array( 'status' => 'OK' ) ;

if ( $action == 'get_potential_occupations' ) {

        if ( $item ) {
                $sql = "SELECT occupation FROM potential_occupation WHERE status IS NULL AND item = $item LIMIT 1";
                $result = $wikigrokdb->query( $sql );
                if ( !$result ) die( 'There was an error running the query [' . $wikigrokdb->error . '] '.$sql );
                $x = $result->fetch_array();
                if ( $x ) {
                        $out['data'] = "$x[0]";
                } else {
                        $out['data'] = false;
                }
        } else {
                $out['status'] = 'Invalid input';
        }

} else {
	$out['status'] = "Unknown action $action" ;
}

print json_encode ( $out ) ;
