<?php
 
/**
 * ExternalArticles.php
 *
 * Copyright (C) 2009 Nathan Perry <nate perry 333 at g mail dot com>
 * http://www.nateperry.org/
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
 * @version 0.1.3
 * @link http://www.nateperry.org/wiki/External_Articles
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0
 *
 * Credit to Alvinos [http://www.mediawiki.org/wiki/User:Alvinos] for changing to use cURL.
 */
 
/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if( !defined( 'MEDIAWIKI' ) )
{
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
 
$wgExtensionCredits['other'][] = array
(
    'name'           => 'External Articles',
    'description'    => 'Preloads source from external articles.',
    //'descriptionmsg' => 'externalarticles-description-msg',
    'version'        => '0.1.3', // version date 2010-03-05
    'author'         => '[mailto:externalarticles@nateperry.org Nathan Perry]',
    'url'            => 'http://www.mediawiki.org/wiki/Extension:ExternalArticles'
);
 
// todo: change this so each setting is set to it's default if it is not defined.
//       Currently, if anything is overridden, all must be defined.
if ( !isset( $eagRules ) || is_null( $eagRules ) )
{
    $eagRules = array();
    $eagRules['onpreload'] = true;
    $eagRules['url']       = 'http://en.wikipedia.org/w/index.php?title=';
 
    // todo: remove assumption of English.
    $eagRules['rule']      = '/^Template:.*$/'; // http://us3.php.net/manual/en/function.preg-match.php
}
else
{
    // todo: validate $eagRules URL's, etc...
}
 
 
$wgHooks['EditFormPreloadText'][] = 'externalarticles_EditFormPreloadText';
 
function externalarticles_EditFormPreloadText(&$text, &$title)
{
    // Called when edit page for a new article is shown. This lets you fill the text-box of a new page with initial wikitext
    // $text: text to prefill edit form with
    // $title: title of new page (Title Object)
 
    global $wgOut, $eagRules;
    $pagename = $title->getEscapedText();
    $url = $eagRules['url'] . urlencode( $pagename ) . '&action=raw';
    $ismatch = preg_match( $eagRules['rule'], $pagename ) > 0;
 
    if ( defined( 'EXTERNALARTICLES_DEBUG' ) )
    {
        if ( $ismatch )
        {
            $wgOut->addWikiText( "URL: $url<br />" );
        }
        else 
        {
            $wgOut->addWikiText( "Page title does not match rule.<br />" );
        }
    }
 
    if ( $eagRules['onpreload'] && $ismatch && empty($text) )
    {
        // Initialize the cURL session
        $ch = curl_init();
 
        // Set the URL of the page or file to download.
        curl_setopt($ch, CURLOPT_URL,$url);
 
        // Ask cURL to return the contents in a variable
        // instead of simply echoing them to the browser.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
 
        // Execute the cURL session
        $url_text = curl_exec ($ch);
 
        // Close cURL session
        curl_close ($ch);
 
        if ( !empty( $url_text ) )
        {
            $text = $url_text;
        }
        else
        {
            if ( defined( 'EXTERNALARTICLES_DEBUG' ) )
            {
                $wgOut->addWikiText( "Failed to fetch external page.<br />" );
            }
        }
        return true;
    }
    else
    {
        return false;
    }
}
?>