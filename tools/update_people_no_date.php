#!/usr/bin/php
<?php

error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'max_execution_time', 2000 );
ini_set( 'memory_limit', '200M' );
require_once( '../../config.inc.php' );

define( 'BASE_WDQ_URL', 'http://wdq.wmflabs.org/api' );

/**
 * Updates the table by trying to insert all items returned in the Wikidata
 * Query query.
 *
 * @param mysqli $db
 * @param string $table
 * @param string $query
 */
function doUpdateTable( mysqli $db, $table, $query ) {
	$responseBody = file_get_contents( BASE_WDQ_URL . '?q=' . urlencode( $query ) );
	$decodedResponseBody = json_decode( $responseBody, true );

	if ( !isset( $decodedResponseBody[ 'items' ] ) || count( $decodedResponseBody[ 'items' ] ) === 0 ) {
		echo "No updates for '{$table}'";

		return;
	}

	$items = $decodedResponseBody[ 'items' ];

	$statement = $db->prepare( "INSERT IGNORE INTO {$table}( item, random ) VALUES ( ?, RAND() )" );

	foreach ( $items as $item ) {
		$statement->bind_param( 's', $item );
		if ( !$statement->execute() ) {
			fwrite( STDERR, "Couldn't insert {$item} into '{$table}'\n" );
			exit( 1 );
		}
	}
}

$db = new mysqli(
	$candidatesdb['host'],
	$candidatesdb['user'],
	$candidatesdb['pass'],
	$candidatesdb['dbname']
);
$tableToQueryMap = array(

	// instance of human and has no date of birth claim
	'people_no_dob' => 'CLAIM[31:5] AND NOCLAIM[569]',

	// instance of human, has a date of death claim with a value between 0 and
	// 1880, and has no date of death claim
	'people_no_dod' => 'CLAIM[31:5] AND BETWEEN[569,0,1880] AND NOCLAIM[570]',
);

foreach ( $tableToQueryMap as $table => $query ) {
	doUpdateTable( $db, $table, $query );
}

