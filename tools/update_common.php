<?php

/**
 * Helper functions for the `update_foo.php` scripts.
 */

require_once( '/data/project/wikidata-game/public_html/php/common.php' );
require_once( '/data/project/wikigrok/config.inc.php' );

/**
 * @param string $query
 * @return array[int] The Wikidata items, specified by their IDs
 */
function getItemsFromWikidataQuery( $query ) {
	global $wdq_internal_url;

	$url = $wdq_internal_url . '?q=' . urlencode( $query );
	$rawResponseBody = file_get_contents( $url );
	$responseBody = json_decode( $rawResponseBody, true );

	return $responseBody['items'];
}

/**
 * Resolve candidate Wikidata items to enwiki mainspace pages.
 *
 * @param array[int] $candidates The Wikidata items, specified by their IDs
 * @return array[string, int] A map of page title to Wikidata ID
 */
function resolve_candidates( $candidates ) {
	$sql = <<<SQL
SELECT ips_item_id, ips_site_page
FROM wb_items_per_site
WHERE ips_site_id = 'enwiki' # English Wikipedia only
AND INSTR( ips_site_page, ':' ) = 0 # Mainspace only
SQL;
	$sql .= "\n AND ips_item_id IN (" . implode( ',', $candidates ) . ')';

	$wikidataDb = openDB( 'wikidata', 'wikidata' );
	$result = $wikidataDb->query( $sql );

	if ( !$result ) {
		die( sprintf( "Couldn't run query [%s]: %s\n", $wikidataDb->error, $sql ) );
	}

	$resolvedCandidates = array();

	while ( $row = $result->fetch_object() ) {
		$key = $wikidataDb->real_escape_string( str_replace( ' ', '_', $row->ips_site_page ) );
		$resolvedCandidates[$key] = $row->ips_item_id;
	}

	return $resolvedCandidates;
}

/**
 * @param array[string, array[int]] $suggestions The result of
 *  `get_genre_suggestions`
 * @param string $tableName The name of the table to update
 * @param string $fieldName The name of the column that holds the list of
 *  suggestions
 */
function update_suggestions( $suggestions, $tableName, $fieldName ) {
	global $candidatesdb;

	$candidatesDb = new mysqli(
		$candidatesdb['host'],
		$candidatesdb['user'],
		$candidatesdb['pass'],
		$candidatesdb['dbname']
	);

	$sql = "INSERT IGNORE INTO $tableName ( item, $fieldName ) VALUES ( ?, ? )";
	$statement = $candidatesDb->prepare( $sql );

	if ( !$statement ) {
			die( sprintf( "Couldn't prepare query update_suggestions: %s\n", $candidatesDb->error ) );
	}

	foreach ( $suggestions as $resolvedCandidateId => $suggestionIds ) {
		$statement->bind_param( 'is', $resolvedCandidateId, implode( ',', $suggestionIds ) );
		$statement->execute();
	}
}
