<?php

namespace MediaWiki\Extension\ExternalArticles;

use Maintenance;

require_once __DIR__ . "/../../../maintenance/Maintenance.php";

class ImportTextFiles extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'ExternalArticles' );
		$this->addOption( 'watch', 'Keep watching the files and re-import whenever one changes' );
		$this->addArg( 'directory', 'The directory to import' );
	}

	/**
	 * Run the import.
	 */
	public function execute() {
		$importer = new TextFileImporter( $this->getArg( 0 ), $this->getOption( 'watch' ) );
		$importer->import();
	}
}

$maintClass = ImportTextFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
