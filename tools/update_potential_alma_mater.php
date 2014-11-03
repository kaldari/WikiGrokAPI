#!/usr/bin/php
<?PHP
require_once( 'update_common.php' );

function get_suggestions( $schools, $resolvedCandidates ) {
	$titles = array_keys( $resolvedCandidates );
	$schoolTitles = array_keys( $schools );
	$sql = <<<SQL
SELECT page_title, pl_title
FROM page JOIN pagelinks ON page_id = pl_from
WHERE page_namespace = 0
	AND pl_namespace = 0 # Both linked pages are mainspace only
SQL;
	$sql .= "\n AND page_title IN (\"" . implode( '","', $titles ) . '")';
	$sql .= ' AND pl_title IN ("' . implode( '","', $schoolTitles	) . '")';

	$enwikiDb = openDBwiki( 'enwiki' );
	$result = $enwikiDb->query( $sql );
	$suggestions = array();

	if ( !$result ) {
		die( sprintf( "Couldn't run query get_suggestions: %s\n", $enwikiDb->error ) );
	}

	while ( $row = $result->fetch_object() ) {
		$key = $enwikiDb->real_escape_string( $row->page_title );
		$resolvedCandidateId = $resolvedCandidates[$key];
		$suggestionId = $schools[addslashes($row->pl_title)];

		if ( !isset( $suggestions[$resolvedCandidateId] ) ) {
			$suggestions[$resolvedCandidateId] = array();
		}

		$suggestions[$resolvedCandidateId][] = $suggestionId;
	}

	return $suggestions;
}

$candidateSchools = getItemsFromWikidataQuery( 'CLAIM[31:3918]' );
echo sprintf( "%d unresolved candidate schools found. Resolving...\n", count( $candidateSchools ) );

// [Madenat_Alelem_University_College] => 6727119
$resolvedCandidateSchools = resolve_candidates( $candidateSchools );
echo sprintf( "Done! Resolved names for %d schools.\n", count( $resolvedCandidateSchools ) );

$peopleWithoutSchools = getItemsFromWikidataQuery( 'CLAIM[31:5] AND NOCLAIM[69]' );
echo sprintf( "%d unresolved people without schools found. Resolving...\n", count( $peopleWithoutSchools ) );

// Shuffle and limit here because the sql query would be huge otherwise
shuffle ( $peopleWithoutSchools );
$peopleWithoutSchools = array_slice( $peopleWithoutSchools, 0, 10000 );

// [Kenneth_Harkins] => 18387587
$resolvedPeopleWithoutSchools = resolve_candidates( $peopleWithoutSchools );
echo sprintf( "Done! Resolved names for %d people without schools.\n", count( $resolvedPeopleWithoutSchools ) );

echo "Getting suggested schools for people...\n";
$suggestions = get_suggestions( $resolvedCandidateSchools, $resolvedPeopleWithoutSchools );

echo "Done! Updating WikiGrok suggestions...\n";
update_suggestions( $suggestions, 'potential_alma_mater', 'alma_mater' );

echo "Done!\n";

?>