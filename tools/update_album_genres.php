<?php

require_once( 'update_common.php' );
require_once( 'update_album_band_common.php' );

// "instance of band (Q482994) and has no genre property (P136)"
$candidateAlbums = getItemsForWikidataQuery( 'CLAIM[31:482994] AND NOCLAIM[P136]' );
echo sprintf( "%d unresolved candidate albums found. Resolving...\n", count( $candidateAlbums ) );

$resolvedCandidateAlbums = resolve_candidates( $candidateAlbums );
echo sprintf( "Done! Resolved %d albums.\n", count( $resolvedCandidateAlbums ) );

echo "Getting genre suggestions for the resolved albums...\n";
$genreSuggestions = get_genre_suggestions( $resolvedCandidateAlbums );

echo "Done! Updating WikiGrok suggestions...\n";
update_suggestions( $genreSuggestions, 'potential_genre', 'genre' );

echo "Done!\n";
