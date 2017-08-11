<?php

namespace MediaWiki\Extensions\ExternalArticles;

use MWHttpRequest;
use OutputPage;
use Title;

class Hooks {

	/**
	 * Preload text from a remote wiki into the edit form. Called when edit page for
	 * a new article is shown.
	 *
	 * @global OutputPage $wgOut OutputPage object for HTTP response
	 * @global array $eagRules ExternalArticles configuration array
	 * @param string &$text Text with which to prefill the edit form
	 * @param Title &$title Title of the new page
	 * @return bool
	 */
	function onEditFormPreloadText( &$text, Title &$title ) {
		global $wgOut, $wgEagRules;

		// @todo: change this so each setting is set to it's default if it is not defined.
		// Currently, if anything is overridden, all must be defined.
		if ( !isset( $wgEagRules ) || is_null( $wgEagRules ) ) {
			$wgEagRules = [];
			$wgEagRules['onpreload'] = true;
			$wgEagRules['url'] = 'https://en.wikipedia.org/w/index.php?title=';

			// @todo: remove assumption of English.
			$wgEagRules['rule'] = '/^Template:.*$/';
		} else {
			// @todo: validate $wgEagRules URL's, etc...
		}

		$pagename = $title->getPrefixedURL();
		$url = $wgEagRules['url'] . $pagename . '&action=raw';
		$ismatch = preg_match( $wgEagRules['rule'], $pagename ) > 0;

		if ( defined( 'EXTERNALARTICLES_DEBUG' ) ) {
			if ( $ismatch ) {
				$wgOut->addWikiText( "URL: $url<br />" );
			} else {
				$wgOut->addWikiText( "Page title does not match rule.<br />" );
			}
		}

		if ( $wgEagRules['onpreload'] && $ismatch && empty( $text ) ) {
			$options = [
				'followRedirects' => true,
			];
			$httpRequest = MWHttpRequest::factory( $url, $options );
			$status = $httpRequest->execute();
			if ( !$status->isOK() ) {
				if ( defined( 'EXTERNALARTICLES_DEBUG' ) ) {
					$wgOut->addWikiText( "Failed to fetch external page: " . $status->getWikiText() );
				}

				return false;
			}
			$wgOut->wrapWikiMsg( '<div class="success">$1</div>',
				[ 'externalarticles-article-loaded', $url ] );
			$text = $httpRequest->getContent();

			return true;
		}

		return true;
	}
}
