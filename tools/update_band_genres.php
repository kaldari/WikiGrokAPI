<?php

require_once( 'update_common.php' );
require_once( 'update_album_band_common.php' );

// "instance of band (Q215380) and has no genre property (P136)"
$candidateBands = getItemsFromWikidataQuery( 'CLAIM[31:215380] AND NOCLAIM[P136]' );
echo sprintf( "%d unresolved candidate bands found. Resolving...\n", count( $candidateBands ) );

$resolvedCandidateBands = resolve_candidates( $candidateBands );
echo sprintf( "Done! Resolved %d bands.\n", count( $resolvedCandidateBands ) );

echo "Getting genre suggestions for the resolved bands...\n";
$genreSuggestions = get_genre_suggestions( $resolvedCandidateBands );

echo "Done! Updating WikiGrok suggestions...\n";
update_suggestions( $genreSuggestions, 'potential_genre', 'genre' );

echo "Done!\n";
