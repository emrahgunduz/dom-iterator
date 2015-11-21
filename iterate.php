<?php

ini_set( 'display_errors', 0 );

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
  $name = iconv( "UTF-8", "ASCII", $name );

  // We do not want "-" in the name
  $name = str_ireplace( array( "-" ), "", $name );

  if ( in_array( $name, $usedNames ) ) {
    $number++;
    $name = checkName( $name . $number, $number );
  }

  array_push( $usedNames, $name );
  return $name;
}

/**
 * Iterates the given DOM element
 * @param DOMNode $domNode
 */
function iterateDOM ( DOMNode $domNode )
{
  global $currentLevel, $js, $parentNames, $doNotWant;

  foreach ( $domNode->childNodes as $node ) {
    if ( $node->nodeType === XML_ELEMENT_NODE ) {

      //region Node information
      if ( in_array( $node->nodeName, $doNotWant ) ) {
        if ( $node->hasChildNodes() ) {
          iterateDOM( $node );
        }
        break;
      }

      echo COLOR_CYAN . str_repeat( " ", $currentLevel ) . $node->nodeName;

      if ( $node->hasAttribute( "data-jsid" ) )
        echo COLOR_GREEN . " | " . COLOR_PURPLE . "JSID: " . $node->getAttribute( "data-jsid" );

      if ( $node->hasAttribute( "style" ) )
        echo COLOR_GREEN . " | Style: " . $node->getAttribute( "style" );

      if ( $node->hasAttribute( "class" ) )
        echo COLOR_GREEN . " | Class: " . $node->getAttribute( "class" );

      if ( $node->hasAttribute( "src" ) )
        echo COLOR_GREEN . " | Src: " . $node->getAttribute( "src" );

      echo "\n";
      //endregion

      //region Decide the name for the node
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
      //endregion

      //region Javascript
      $js .= "var {$name} = document.createElement('{$node->nodeName}');\n";
      if ( $node->hasAttributes() ) {
        foreach ( $node->attributes as $attr ) {
          $attrName = $attr->nodeName;
          $attrValue = $attr->nodeValue;
          $js .= "{$name}.setAttribute('{$attrName}','{$attrValue}');\n";
        }
      }

      if ( $currentLevel > 0 ) {
        $pName = $parentNames[ $currentLevel - 1 ];
        $js .= "{$pName}.appendChild({$name});\n";
      }
      //endregion

      $currentLevel++;

      if ( $node->hasChildNodes() ) {
        $js .= "\n";
        iterateDOM( $node );
      }

      $currentLevel--;
    }
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

echo COLOR_YELLOW;
echo str_repeat( "-", 50 ) . "\n";
echo $js;
echo str_repeat( "-", 50 ) . "\n";
echo COLOR_RESET;

@file_put_contents( "dom.js", $js );