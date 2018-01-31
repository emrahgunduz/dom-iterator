<?php

ini_set( 'display_errors', 1 );

/** Detect if we are running in windows */
define( 'WIN', strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' );
//region Terminal Color Codes (BASH CODES)
define( 'COLOR_BLACK', !WIN ? "\033[0;30m" : "" );
define( 'COLOR_RED', !WIN ? "\033[0;31m" : "" );
define( 'COLOR_GREEN', !WIN ? "\033[0;32m" : "" );
define( 'COLOR_YELLOW', !WIN ? "\033[0;33m" : "" );
define( 'COLOR_BLUE', !WIN ? "\033[0;34m" : "" );
define( 'COLOR_PURPLE', !WIN ? "\033[0;35m" : "" );
define( 'COLOR_CYAN', !WIN ? "\033[0;36m" : "" );
define( 'COLOR_WHITE', !WIN ? "\033[0;37m" : "" );
define( 'COLOR_RESET', !WIN ? "\033[0m" : "" );
//endregion

$doNotWant = [ "html", "body" ];  // We do not want these dom nodes
$currentLevel = 0;                // Current level in dom iteration
$parentNames = array();           // Parent names
$usedNames = array();             // Used names for checkName
$toEcho = "";                     // Dom tree map
$js = "";                         // Built javascript code

/**
 * Checks if the object name is used previously
 * Returns a new name if needed
 * @param string $name
 * @param int $number
 * @return string
 */
function checkName ( $name, $number = 0 )
{
  global $usedNames;

  // Convert UTF8 to ASCII for variable name
  $name = iconv( "UTF-8", "ISO-8859-1//TRANSLIT", $name );

  // We only want these characters in our variable names
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_';
  $regex = sprintf( '/[^%s]/u', preg_quote( $characters, '/' ) );
  $name = preg_replace( $regex, '', $name );

  // Sometimes things can go wrong
  if ( !strlen( $name ) ) {
    $name = "domElement";
  }

  if ( in_array( ( $number ? $name . $number : $name ), $usedNames ) ) {
    $number++;
    return checkName( $name, $number );
  }

  $nName = ( $number ? $name . $number : $name );
  array_push( $usedNames, $nName );
  return $nName;
}

/**
 * Iterates the given DOM element
 * @param DOMNode $domNode
 */
function iterateDOM ( DOMNode $domNode )
{
  global $currentLevel, $js, $parentNames, $doNotWant, $lastNodeName, $toEcho;

  foreach ( $domNode->childNodes as $node ) {

    if ( $node->nodeType === XML_ELEMENT_NODE ) {

      if ( in_array( $node->nodeName, $doNotWant ) ) {
        if ( $node->hasChildNodes() ) {
          iterateDOM( $node );
        }
        break;
      }

      $toEcho .= COLOR_CYAN . str_repeat( " ", $currentLevel ) . $node->nodeName;

      if ( $node->hasAttribute( "data-jsid" ) )
        $toEcho .= COLOR_GREEN . " | " . COLOR_PURPLE . "JSID: " . $node->getAttribute( "data-jsid" );

      if ( $node->hasAttribute( "style" ) )
        $toEcho .= COLOR_GREEN . " | Style: " . $node->getAttribute( "style" );

      if ( $node->hasAttribute( "class" ) )
        $toEcho .= COLOR_GREEN . " | Class: " . $node->getAttribute( "class" );

      if ( $node->hasAttribute( "src" ) )
        $toEcho .= COLOR_GREEN . " | Src: " . $node->getAttribute( "src" );

      $toEcho .= "\n";

      if ( $node->hasAttribute( "data-jsid" ) ) {
        $name = trim( $node->getAttribute( "data-jsid" ) );
      } else if ( $node->hasAttribute( "class" ) ) {
        $nameArr = explode( " ", $node->getAttribute( "class" ) );
        $name = trim( array_shift( $nameArr ) );
      } else {
        $name = $node->nodeName;
      }
      $name = checkName( $name, 0 );

      $parentNames[ $currentLevel ] = $name;
      $lastNodeName = $name;

      $js .= "const {$name} = document.createElement('{$node->nodeName}');\n";
      if ( $node->hasAttributes() ) {
        foreach ( $node->attributes as $attr ) {
          $attrName = $attr->nodeName;
          $attrValue = $attr->nodeValue;
          if ( $attrName != "data-jsid" )
            $js .= "{$name}.setAttribute('{$attrName}','{$attrValue}');\n";
        }
      }

      if ( $currentLevel > 0 ) {
        $pName = $parentNames[ $currentLevel - 1 ];
        $js .= "{$pName}.appendChild({$lastNodeName});\n";
      }

      $js .= "\n";
    }

    if ( $node->nodeType === XML_TEXT_NODE && $node->textContent && strlen( $node->textContent ) ) {
      $toEcho = trim( $toEcho );
      $toEcho .= COLOR_YELLOW . " | Content: " . $node->textContent . "\n";

      $js = trim( $js );
      $js .= "\n{$lastNodeName}.innerHTML = \"" . $node->textContent . "\";\n";
      $js .= "\n";
    }

    $currentLevel++;

    if ( $node->hasChildNodes() ) {
      iterateDOM( $node );
    }

    $currentLevel--;
  }
}

// Multiline user input
echo COLOR_WHITE . "Paste HTML -- Write !q to continue -- : \n";
$stdin = fopen( 'php://stdin', 'r' );
$html = "";
while ( $f = fgets( STDIN ) ) {
  $f = trim( $f );
  if ( $f != "!q" ) {
    $html .= $f;
  } else {
    break;
  }
}
echo COLOR_RESET;
file_put_contents( "temp.html", $html );

$dom = new DOMDocument;
@$dom->loadHTMLFile( __DIR__ . "/temp.html" );

iterateDOM( $dom );

@unlink( "temp.html" );

echo $toEcho;
echo COLOR_YELLOW;
echo str_repeat( "-", 50 ) . "\n";
echo $js;
echo str_repeat( "-", 50 ) . "\n";
echo COLOR_RESET;

@file_put_contents( "dom.js", $js );