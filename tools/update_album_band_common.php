<?php

/**
 * Gets all genres with mainspace pages on enwiki.
 *
 * See https://github.com/phuedx/wikidata-genres for maps for all other wikis
 * and the script that generates them.
 *
 * @return array[string, int] A map of page title to Wikidata ID
 */
function get_genres() {
	return json_decode( file_get_contents( 'enwiki.json' ), true );
}

/**
 * Gets genre suggestions for the resolved candidates.
 *
 * @param array[string, int] $resolvedCandidates The result of `resolve_candidates`
 * @return array[string, array[int]] A map of page title to a set of Wikidata IDs
 */
function get_genre_suggestions( $resolvedCandidates ) {
	$titles = array_keys( $resolvedCandidates );
	$genres = get_genres();
	$genreTitles = array_keys( $genres );

	$sql = <<<SQL
SELECT page_title, pl_title
FROM page JOIN pagelinks ON page_id = pl_from
WHERE page_namespace = 0
	AND pl_namespace = 0 # Both linked pages are mainspace only
SQL;
	$sql .= "\n AND page_title IN (\"" . implode( '","', $titles ) . '")';
	$sql .= ' AND pl_title IN ("' . implode( '","', $genreTitles	) . '")';

	$enwikiDb = openDBwiki( 'enwiki' );
	$result = $enwikiDb->query( $sql );
	$genreSuggestions = array();

	if ( !$result ) {
		die( sprintf( "Couldn't run query get_genre_suggestions: %s\n", $enwikiDb->error ) );
	}

	while ( $row = $result->fetch_object() ) {
		$key = $enwikiDb->real_escape_string( $row->page_title );
		$resolvedCandidateId = $resolvedCandidates[$key];
		$genreId = $genres[$row->pl_title];

		if ( !isset( $genreSuggestions[$resolvedCandidateId] ) ) {
			$genreSuggestions[$resolvedCandidateId] = array();
		}

		// genre field is TINYTEXT, so limit to 30 matches
		if ( count( $genreSuggestions[$resolvedCandidateId] ) < 30 ) {
			$genreSuggestions[$resolvedCandidateId][] = $genreId;
		}
	}

	return $genreSuggestions;
}
