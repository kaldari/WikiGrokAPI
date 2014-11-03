#!/usr/bin/php
<?PHP

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set( 'display_errors', 1 );

define( 'WIKIDATA_GAME_DIR', '/data/project/wikidata-game' );
define( 'WIKIGROK_DIR', '/data/project/wikigrok' );

// WikiGame common.php
require_once ( WIKIDATA_GAME_DIR . '/public_html/php/common.php' );
require_once ( WIKIGROK_DIR . '/config.inc.php' );

$batch_size = 10000;
$unlikely_page_title = "vwhj9ew8h94whbviwg7vi7w";
$db = openDB ( 'wikidata' , 'wikidata' );
$dbu = new mysqli(
	$candidatesdb['host'],
	$candidatesdb['user'],
	$candidatesdb['pass'],
	$candidatesdb['dbname']
);

$hadthat = array();
if ( 0 ) { // Pre-filter existing items?
	$sql = "select distinct item from potential_alma_mater";
	$result = $dbu->query( $sql );
	if(!$result) die('There was an error running the query 1[' . $dbu->error . '] '.$sql);
	while( $o = $result->fetch_object() ){
		$hadthat[$o->item] = 1;
	}
}

// Get schools
$url = "$wdq_internal_url?q=" . urlencode( "CLAIM[31:3918]" );
$j = json_decode ( file_get_contents ( $url ) );
$sql = "select * from wb_items_per_site WHERE ips_item_id IN (" . implode ( ',' , $j->items ) . ")";
if( !$result = $db->query( $sql ) ) die( 'There was an error running the query 1[' . $db->error . '] '.$sql );
$schools = array();
$schools_rev = array();
while( $o = $result->fetch_object() ) {
	$school = str_replace ( ' ' , '_' , $o->ips_site_page );
	$schools[$o->ips_site_id][] = $school;
	$schools_rev[$o->ips_site_id][$school] = $o->ips_item_id;
}

// Make SQL strings
$newArray = array();
foreach ( $schools AS $site => $list ) {
	$a = array();
	foreach ( $list AS $l ) $a[] = $db->real_escape_string ( $l );
	$newArray[$site] = '"' . implode ( '","' , $a ) . '"';
}
// Site:sql strings
$schools = $newArray;

print count ( $schools ) . " schools/sites found.\n";

// Get people without schools
$url = "$wdq_internal_url?q=" . urlencode( "CLAIM[31:5] AND NOCLAIM[69]" );
$j = json_decode ( file_get_contents ( $url ) );
shuffle ( $j->items );
$candidates = array_slice ( $j->items , 0 , $batch_size ); // subset
unset ( $j ); // Save space

$sql = "select * from wb_items_per_site WHERE ips_item_id IN (" . implode ( ',' , $candidates ) . ")";
if( !$result = $db->query( $sql ) ) die( 'There was an error running the query 2[' . $db->error . '] '.$sql );
$people = array();
while( $o = $result->fetch_object() ){
	if ( isset ( $hadthat[$o->ips_item_id] ) ) continue; // Have those already
	if ( preg_match ( '/:/' , $o->ips_site_page ) ) continue; // No namespace-prexied titles
	$people[$o->ips_site_id][$o->ips_item_id] = $db->real_escape_string ( str_replace ( ' ' , '_' , $o->ips_site_page ) );
}

print count ( $people ) . " people/sites found.\n";

$person2school = array();
foreach ( $people AS $site => $list ) {
	if ( !preg_match ( '/wiki$/' , $site ) ) continue; // Wikipedia only
	//if ( !preg_match ( '/enwiki$/' , $site ) ) continue; // English Wikipedia only
	if ( !isset ( $schools[$site] ) ) continue;
	$schools_site = $schools[$site];
	$dbw = openDBwiki ( $site );
	if ( $dbw === false ) continue; // Can't open database
	foreach ( $list AS $q => $title ) {
		$sql = "SELECT * FROM page,pagelinks WHERE page_title=\"$title\" and page_namespace=0 and page_id=pl_from and pl_namespace=0 and pl_title IN ($schools_site)";
		if( !$result = $dbw->query( $sql ) ) die( 'There was an error running the query 3[' . $db->error . '] '."$site : $sql" );
		while( $o = $result->fetch_object() ) {
			if ( !isset ( $schools_rev[$site][$o->pl_title] ) ) { print "Dunno $site:".$o->pl_title."\n"; continue; }
			$person2school[$q][$schools_rev[$site][$o->pl_title]] = 1;
		}
	}
}

// Reconnect because connection is lost by the time we get to inserting suggestions
$dbu->close();
$dbu = new mysqli(
	$candidatesdb['host'],
	$candidatesdb['user'],
	$candidatesdb['pass'],
	$candidatesdb['dbname']
);

foreach ( $person2school AS $q_item => $list ) {
	$q_target = implode ( ',' , array_keys ( $list ) );
	$sql = "INSERT IGNORE INTO potential_alma_mater (item,alma_mater) VALUES ('$q_item','$q_target')";
	$result = $dbu->query( $sql );
	if( !$result ) die( 'There was an error running the query 4[' . $dbu->error . '] '."$site : $sql" );
}

$sql = "update potential_alma_mater set random=rand() where random is null";
$result = $dbu->query( $sql  );
if( !$result ) die( 'There was an error running the query 5[' . $dbu->error . '] '.$sql );

?>