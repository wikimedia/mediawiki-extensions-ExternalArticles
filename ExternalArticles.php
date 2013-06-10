<?php
/**
 * ExternalArticles.php
 * 
 * The ExternalArticles extension fetches pages from a remote wiki.
 * 
 * Copyright (C) 2009-2013 the authors listed below.
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 * 
 * @ingroup Extensions
 * @author Nathan Perry <externalarticles@nateperry.org>
 * @author Alvinos http://www.mediawiki.org/wiki/User:Alvinos
 * @author Sam Wilson <sam@samwilson.id.au>
 * @version 0.1.4
 * @link http://www.nateperry.org/wiki/External_Articles
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @file
 */
/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	echo <<<EOT
This file is not a valid entry point.
To install this extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/ExternalArticles/ExternalArticles.php" );
EOT;
	exit( 1 );
}

/**
 * Initialize variables
 */
define( 'MEDIAWIKI_EXTERNALARTICLES', true );
//define( 'EXTERNALARTICLES_DEBUG', true );

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'External Articles',
	'descriptionmsg' => 'externalarticles-desc',
	'version' => '0.1.4', // version date 2013-05-31
	'author' => array( 'Nathan Perry', 'Alvinos', 'Sam Wilson' ),
	'url' => 'http://www.mediawiki.org/wiki/Extension:ExternalArticles'
);

$wgExtensionMessagesFiles['ExternalArticles'] = __DIR__ . '/ExternalArticles.i18n.php';

// todo: change this so each setting is set to it's default if it is not defined.
//       Currently, if anything is overridden, all must be defined.
if ( !isset( $eagRules ) || is_null( $eagRules ) ) {
	$eagRules = array();
	$eagRules['onpreload'] = true;
	$eagRules['url'] = 'http://en.wikipedia.org/w/index.php?title=';

	// todo: remove assumption of English.
	$eagRules['rule'] = '/^Template:.*$/'; // http://us3.php.net/manual/en/function.preg-match.php
} else {
	// todo: validate $eagRules URL's, etc...
}

$wgHooks['EditFormPreloadText'][] = 'ExternalArticles_EditFormPreloadText';

/**
 * Preload text from a remote wiki into the edit form. Called when edit page for
 * a new article is shown.
 * 
 * @global OutputPage $wgOut OutputPage object for HTTP response
 * @global array $eagRules ExternalArticles configuration array
 * @param string $text Text with which to prefill the edit form
 * @param Title $title Title of the new page
 * @return boolean
 */
function ExternalArticles_EditFormPreloadText( &$text, &$title ) {
	global $wgOut, $eagRules;

	$pagename = $title->getPrefixedURL();
	$url = $eagRules['url'] . $pagename . '&action=raw';
	$ismatch = preg_match( $eagRules['rule'], $pagename ) > 0;

	if ( defined( 'EXTERNALARTICLES_DEBUG' ) ) {
		if ( $ismatch ) {
			$wgOut->addWikiText( "URL: $url<br />" );
		} else {
			$wgOut->addWikiText( "Page title does not match rule.<br />" );
		}
	}

	if ( $eagRules['onpreload'] && $ismatch && empty( $text ) ) {
		$options = array(
			'followRedirects' => true,
		);
		$httpRequest = MWHttpRequest::factory( $url, $options );
		$status = $httpRequest->execute();
		if ( !$status->isOK() ) {
			if ( defined( 'EXTERNALARTICLES_DEBUG' ) ) {
				$wgOut->addWikiText( "Failed to fetch external page: " . $status->getWikiText() );
			}
			return false;
		}
		$text = $httpRequest->getContent();

		return true;
	}
	return true;
}

