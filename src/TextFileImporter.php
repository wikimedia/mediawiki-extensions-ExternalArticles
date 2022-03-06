<?php

namespace MediaWiki\Extension\ExternalArticles;

use DirectoryIterator;
use Exception;
use MediaWiki\MediaWikiServices;
use RecentChange;
use Revision;
use Title;
use User;
use WikiRevision;

class TextFileImporter {

	/** @var resource */
	protected $inotify;

	/** @var string[] */
	protected $watches;

	/**
	 * Import text files from a directory.
	 * @param string $dir The directory to import from.
	 * @param bool $watch Whether to continue to watch the files for changes.
	 * @throws Exception If the directory does not exist.
	 */
	public function __construct( $dir, $watch ) {
		if ( !is_dir( $dir ) ) {
			throw new Exception( "'$dir' is not a directory" );
		}
		$this->dir = realpath( $dir );
		$this->watch = (bool)$watch && function_exists( 'inotify_init' );
		if ( $this->watch ) {
			$this->inotify = inotify_init();
		}
	}

	/**
	 *
	 */
	public function import() {
		$topLevel = new DirectoryIterator( $this->dir );
		foreach ( $topLevel as $file ) {
			if ( $file->isDot() ) {
				continue;
			}
			if ( $file->isDir() ) {
				// Use the directory names as namespaces.
				$secondLevel = new DirectoryIterator( $this->dir . '/' . $file );
				foreach ( $secondLevel as $subfile ) {
					if ( $subfile->isDot() ) {
						continue;
					}
					$this->importFile( $subfile->getPathname() );
				}
			} else {
				$this->importFile( $file->getPathname() );
			}
		}

		if ( $this->watch ) {
			while ( true ) {
				$events = inotify_read( $this->inotify );
				foreach ( $events as $event ) {
					$file = $this->watches[ $event['wd'] ];
					$this->importFile( $file );
				}
			}
		}
	}

	/**
	 * Import a single file.
	 * @param string $file Full filesystem path to the file to import.
	 * @return bool
	 */
	protected function importFile( $file ) {
		// Construct the page name from the last components of the file path.
		$pagePath = substr( $file, strlen( $this->dir ) + 1 );
		$pageName = str_replace( '/', ':', $pagePath );

		// Have to check for # manually, since it gets interpreted as a fragment
		$title = Title::newFromText( $pageName );
		if ( !$title || $title->hasFragment() ) {
			echo "Invalid title: $pageName\n";
			return false;
		}

		if ( $this->watch ) {
			// Watch this file.
			$watchId = inotify_add_watch( $this->inotify, $file, IN_MODIFY );
			$this->watches[ $watchId ] = $file;
		}

		$exists = $title->exists();
		$oldRevID = $title->getLatestRevID();
		$oldRev = $oldRevID ? Revision::newFromId( $oldRevID ) : null;
		$actualTitle = $title->getPrefixedDBkey();

		$text = file_get_contents( $file );

		$rev = new WikiRevision( MediaWikiServices::getInstance()->getMainConfig() );
		$rev->setText( rtrim( $text ) );
		$rev->setTitle( $title );
		$user = User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );
		$rev->setUserObj( $user );
		$rev->setComment( 'Imported by ExternalArticles extension' );
		$rev->setTimestamp( wfTimestampNow() );

		if ( $exists && $rev->getContent()->equals( $oldRev->getContent() ) ) {
			echo "$actualTitle does not need to be updated\n";
			return false;
		}

		$status = $rev->importOldRevision();
		$newId = $title->getLatestRevID();

		if ( $status ) {
			$action = $exists ? 'updated' : 'created';
			echo "Successfully $action $actualTitle\n";
		} else {
			$action = $exists ? 'update' : 'create';
			echo "Failed to $action $actualTitle\n";
			return false;
		}

		// Create the RecentChanges entry if necessary
		if ( $exists ) {
			if ( is_object( $oldRev ) ) {
				$oldContent = $oldRev->getContent();
				RecentChange::notifyEdit(
					$rev->getTimestamp(),
					$title,
					$rev->getMinor(),
					$user,
					$rev->getComment(),
					$oldRevID,
					$oldRev->getTimestamp(),
					false,
					'',
					$oldContent ? $oldContent->getSize() : 0,
					$rev->getContent()->getSize(),
					$newId,
					1
				);
			}
		} else {
			RecentChange::notifyNew(
				$rev->getTimestamp(),
				$title,
				$rev->getMinor(),
				$user,
				$rev->getTimestamp(),
				false,
				'',
				$rev->getContent()->getSize(),
				$newId,
				1
			);
		}
	}
}
