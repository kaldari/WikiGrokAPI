<?php
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'max_execution_time', 2000 );
ini_set( 'memory_limit', '200M' );

require_once( '../config.inc.php' );

class WikiGrokApi {
	/** @var int version to use */
	protected $version;

	/** @var mysqli Connection to database storing candidate information (for reading) */
	protected $dbr;

	/** @var mysqli Connection to database storing the log of claims (for writing) */
	protected $dbw;

	/** @var string The action to execute */
	public $action;

	/** @var string JSON output string */
	protected $out;

	public function __construct() {
		global $candidatesdb, $wikigrokdb;
		$this->version = intval( self::getRequest( 'version', '1' ) );
		$this->action = self::getRequest( 'action' );
		// Initialize read database connection
		$this->dbr = new mysqli(
			$candidatesdb['host'],
			$candidatesdb['user'],
			$candidatesdb['pass'],
			$candidatesdb['dbname']
		);
		// Initialize write database connection
		$this->dbw = new mysqli(
			$wikigrokdb['host'],
			$wikigrokdb['user'],
			$wikigrokdb['pass'],
			$wikigrokdb['dbname']
		);
		// Set default status (should be overridden in the event of an error)
		$this->out = array( 'status' => 'OK' );
	}

	/**
	 * Get value for an API parameter
	 * @param string $key The name of the API parameter
	 * @param string $default Default value to return if none was specified in request
	 * @return string The value of the API parameter (or default)
	 */
	public static function getRequest( $key, $default = '' ) {
		if ( isset ( $_REQUEST[$key] ) ) return str_replace( "\'" , "'" , $_REQUEST[$key] );
		return $default;
	}

	/**
	 * Generic method for retrieving potential claims from candidate database
	 * @param int $item The Wikidata ID for the person (without Q)
	 * @param string $field Name of the database field containing the potential claims
	 * @param string $table Name of the database table containing the potential claims
	 * @return array An array for ids for items in Wikidata (with Q)
	 */
	protected function getPotentialClaimsList( $item, $field, $table ) {
		$str = $this->getPotentialClaims( $item, $field, $table );
		if ( $str ) {
			$list = explode( ',', $str );
			foreach( $list as $key => $item ) {
				$list[$key] = 'Q' . $item;
			}
			return $list;
		} else {
			return array();
		}
	}

	/**
	 * Generic method for retrieving potential claims from candidate database
	 * @param int $item The Wikidata ID for the person (without Q)
	 * @param string $field Name of the database field containing the potential claims
	 * @param string $table Name of the database table containing the potential claims
	 * @return string A comma-separated list of IDs for items in Wikidata (without the Q)
	 */
	protected function getPotentialClaims( $item, $field, $table ) {
		// Sanitize input
		$item = intval( $item );
		$field = $this->dbr->real_escape_string( $field );
		$table = $this->dbr->real_escape_string( $table );
		// Common claim statuses are NULL, DEL, YES, NO, and DONE.
		// NULL: No decisions have been made about the potential claims
		// DEL: Item has problems (article deleted, etc.)
		// YES: The suggested claims are correct and have been recorded in Wikidata
		// NO: None of the suggested claims are correct
		// DONE: Claims have been set for this item in WikiData via WikiData Game
		$sql = "SELECT $field FROM $table WHERE (status IS NULL OR status != 'DEL') AND item = $item LIMIT 1";
		$result = $this->dbr->query( $sql );
		if ( !$result ) die( 'There was an error running the query [' . $this->dbr->error . '] '.$sql );
		$x = $result->fetch_array();
		return $x ? $x[0] : false;
	}

	protected function recordAnswer() {
		$subject_id = $this->dbw->real_escape_string( self::getRequest( 'subject_id' ) );
		$subject = $this->dbw->real_escape_string( self::getRequest( 'subject' ) );
		$page_name = $this->dbw->real_escape_string( self::getRequest( 'page_name' ) );
		$user_id = intval( self::getRequest( 'user_id', 0 ) );
		$source = $this->dbw->real_escape_string( self::getRequest( 'source' ) );
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$host = $this->dbw->real_escape_string( parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST ) );
		} else {
			$host = 'none';
		}
		$claims = json_decode( urldecode( self::getRequest( 'claims' ) ) );
		if ( $subject_id && $claims ) {
			foreach ( $claims as $claim ) {
				$claim_property_id = $this->dbw->real_escape_string( $claim->propid );
				$claim_property = $this->dbw->real_escape_string( $claim->prop );
				$claim_value_id = $this->dbw->real_escape_string( $claim->valueid );
				$claim_value = $this->dbw->real_escape_string( $claim->value );
				$correct = $claim->correct ? 1 : 0;
				$sql = "INSERT INTO `claim_log` (`subject_id`, `subject`, `claim_property_id`, `claim_property`, `claim_value_id`, `claim_value`, `page_name`, `correct`, `user_id`, `source`, `host`, `timestamp`) VALUES ('$subject_id', '$subject', '$claim_property_id', '$claim_property', '$claim_value_id', '$claim_value', '$page_name', $correct, $user_id, '$source', '$host', CURRENT_TIMESTAMP)";
				$result = $this->dbw->query( $sql );
				if ( !$result ) die( 'There was an error running the query [' . $this->dbw->error . '] '.$sql );
			}
		} else {
			$this->out['status'] = "Incomplete data";
		}
	}

	public function handleRequest() {
		if ( $this->action === 'record_answer' ) {
			$this->recordAnswer();
		} else {
			// Handle all the 'get' actions
			$item = self::getRequest( 'item' , 0 );
			if ( $item ) {
				switch ( $this->action ) {
					case 'get_potential_occupations':
						if ( $this->version === 1 ) {
							$this->out['occupations'] = $this->getPotentialClaims( $item, 'occupation', 'potential_occupation' );
						} else {
							$this->out['occupations'] = $this->getPotentialClaimsList( $item, 'occupation', 'potential_occupation' );
						}
						break;
					case 'get_potential_nationality':
						if ( $this->version === 1 ) {
							$this->out['nationality'] = $this->getPotentialClaims( $item, 'nationality', 'potential_nationality' );
						} else {
							$this->out['nationality'] = $this->getPotentialClaimsList( $item, 'nationality', 'potential_nationality' );
						}
						break;
					case 'get_suggestions':
						$this->out['suggestions'] = array(
							'occupations' => array(
								'id' => 'P106',
								'name' => 'occupations',
								'list' => $this->getPotentialClaimsList( $item, 'occupation', 'potential_occupation' ),
							),
							'nationalities' => array(
								'id' => 'P27',
								'name' => 'nationality',
								'list' => $this->getPotentialClaimsList( $item, 'nationality', 'potential_nationality' ),
							),
							'dob' => array(
								'id' => 'P569',
								'name' => 'date of birth',
								'list' => array(),
							),
							'dod' => array(
								'id' => 'P570',
								'name' => 'date of death',
								'list' => array(),
							),
						);
						break;
					default:
						$this->out['status'] = "Unknown action " . $this->action;
				}
			} else {
				$this->out['status'] = "Invalid item input: " . $item;
			}
		}
	}

	/**
	 * Output the results
	 */
	public function generateOutput() {
		header( 'Content-type: application/json' );
		//header('Content-type: text/plain'); // for testing

		$json = json_encode( $this->out );
		$callback = self::getRequest( 'callback' );

		// Use callback if JSONP request
		if ( $callback ) { 
			print $callback . '(' . $json . ');';
		} else { 
			print $json . "\n"; 
		}
	}
}

$api = new WikiGrokApi;
$api->handleRequest();
$api->generateOutput();
