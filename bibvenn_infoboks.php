<?php
/*
Plugin Name: Bibliotekarens beste infoboks
Plugin URI: http://www.bibvenn.no
Description: Lager en knapp i redigeringsskjermen som lar deg sette inn info om et objekt (tittel, forfatter, ISBN...) bare ved å oppgi ISBN eller URN hos Nasjonalbiblioteket.
Version: 1.0
Author: Håkon M. E. Sundaune
Author URI: http://www.sundaune.no
License: GPL3
*/

/*
ini_set('display_startup_errors',1);
ini_set('display_errors',1);
error_reporting(-1);
*/

// Sjekke om en URL er et bilde eller ikke

function isImage($url) { 

  $params = array('http' => array(
                  'method' => 'HEAD'
               ));
     $ctx = stream_context_create($params);
     $fp = @fopen($url, 'rb', false, $ctx);
     if (!$fp) 
        return false;  // Problem with url

    $meta = stream_get_meta_data($fp);
    if ($meta === false)
    {
        fclose($fp);
        return false;  // Problem reading data from url
    }

    $wrapper_data = $meta["wrapper_data"];
    if(is_array($wrapper_data)){
      foreach(array_keys($wrapper_data) as $hh){
          if (substr($wrapper_data[$hh], 0, 19) == "Content-Type: image") // strlen("Content-Type: image") == 19 
          {
            fclose($fp);
            return true;
          }
      }
    }

    fclose($fp);
    return false;

	}

	
	
// FÅ INN STILARK

function min_css() {
wp_register_style('min_css', plugins_url('/bibvenn_infoboks.css',__FILE__ ));
wp_enqueue_style('min_css');
}
add_action( 'wp_enqueue_scripts','min_css');


// Trunkerer lange strenger til et visst antall ord

function infoboks_trunc($phrase, $max_words) {
   $phrase_array = explode(' ',$phrase);
   if(count($phrase_array) > $max_words && $max_words > 0)
      $phrase = implode(' ',array_slice($phrase_array, 0, $max_words)).'...';
   return $phrase;
}

// Leser fil med curl

function get_content($url) { 

	$ch = curl_init();  
     
	curl_setopt ($ch, CURLOPT_URL, $url);  
	curl_setopt ($ch, CURLOPT_HEADER, 0);  
      
	ob_start();  
      
	curl_exec ($ch);  
	curl_close ($ch);  
	$string = ob_get_contents();  
      
	ob_end_clean();  
         
	return $string;
}  

// Callback function

function bibvenn_infoboks_function($atts){
   extract(shortcode_atts(array(
      'id' => '',
   ), $atts));

// SÅ TIL SAKEN

$outputhtml = "\n\n<div class=\"bibvenn_infoboks_wrapper\">\n";
$outputhtml .= "\n\n<div class=\"bibvenn_infoboks\">\n";
$outputhtml .= "<div class=\"bibvenn_infoboks_left\">";
$outputhtml .= "<a href=\"urlString\">\n";
$outputhtml .= "<img class=\"bibvenn_infoboks_left\" src=\"bildeString\" alt=\"tittelString\" /></a>\n";
$outputhtml .= "tilgjengeligString\n";
$outputhtml .= "</div>";
$outputhtml .= "<div class=\"bibvenn_infoboks_right\">";
$outputhtml .= "<a style=\"text-decoration: none;\" href=\"urlString\">\n";
$outputhtml .= "<h3>tittelString</h3></a>\n";
$outputhtml .= "<p>descriptionString</p>\n\n";
$outputhtml .= "</div>";
$outputhtml .= "</div><!-- /infoboks -->";
$outputhtml .= "</div><!-- /wrapper -->";

// Er det URN eller ISBN?
// Sette opp søketerm / lage URL
// Hente XML-fil
// Sette opp HTML
// Returnere HTML

$id = trim($id); // bort med ev. whitespace

if ($id != "") {

	if (strtolower(substr($id, 0, 4) == "http")) {
		// stripp av HTML etc
		$id = stristr($id, "URN:NBN");
		// kjør URN-søk
		$rawurl = "http://www.nb.no/services/search/v2/search?q=urn:%22" . $id . "%22";
		$xmlfil = get_content($rawurl);
		$xmldata = simplexml_load_string($xmlfil);
	} elseif (strtolower(substr($id, 0, 3) == "URN")) {
		// kjør URN-søk
		$rawurl = "http://www.nb.no/services/search/v2/search?q=urn:%22" . $id . "%22";
		$xmlfil = get_content($rawurl);
		$xmldata = simplexml_load_string($xmlfil);
	} else { // hvis alt annet svikter - dette er kanskje ISBN? 
		// kjør ISBN-søk
		$isisbn = 1;
		$rawurl = "http://www.nb.no/services/search/v2/search?q=isbn:%22" . $id . "%22";
		$xmlfil = get_content($rawurl);
		$xmldata = simplexml_load_string($xmlfil);
	}
} 

// Hente metadata og struct
	
if (!isset($xmldata->entry)) {
	die ("Bibvenn infoboks: Det er noe feil med ID-en du har oppgitt. Det er antagelig ikke et gyldig ISBN-nummer eller en URL til Nasjonalbiblioteket!");
}

$childxmlfil = $xmldata->entry->link[0]->attributes()->href;
$childxml = get_content ($childxmlfil);
$childxmldata = simplexml_load_string ($childxml);

$namespaces = $xmldata->entry->getNameSpaces(true);
$nb = $xmldata->entry->children($namespaces['nb']); // alle som er nb:ditten og nb:datten
$mediatype = $nb->mediatype;

// struct finnes bare hvis digital!

if ($nb->digital == "true") {
	$structxmlfil = $xmldata->entry->link[3]->attributes()->href;
	$structxml = get_content ($structxmlfil);
	$structxmldata = simplexml_load_string ($structxml);
}

$contentclasses = $nb->contentclasses;

// FINNE OMSLAGSBILDE

if ((strtolower($mediatype) == "bøker") && ($nb->digital == "true")) {
	foreach ($structxmldata->div as $utgave) {
		if ($utgave->attributes()->TYPE == "COVER_FRONT") { // Hvis første side
			$sideid = $utgave->resource->attributes("xlink", TRUE)->href; 
		}
	}	
}

if (strtolower($mediatype) == "aviser") {
	foreach ($structxmldata->div as $utgave) {
		if ($utgave->attributes()->ORDER == "1") { // Her er det side 1 som er forsiden
			$sideid = $utgave->resource->attributes("xlink", TRUE)->href; 
		}
	}	
}

// FINNE URL
	// Noen kan ha flere URN, vi splitter på semikolon og bruker den andre

	if (stristr($nb->urn , ";")) {
		$tempura = explode (";" , $nb->urn);
		$urn = trim($tempura[1]); // vi tar nummer 2 
	} else {
		$urn = $nb->urn[0];
	}

	if ($urn != '') { // bruk URN, men hvis vi ikke har, så bruk sesamid
		$url = "http://urn.nb.no/" . $urn;	
	} else {
		$url = "http://www.nb.no/nbsok/nb/" . $nb->sesamid;
	}

// FINNE OMSLAG
	if (stristr($contentclasses, "public") || stristr($contentclasses, "bokhylla")) {
		$omslag = "http://www.nb.no/services/iiif/api/" . $sideid . "/full/300,/0/native.jpg";
	} else { // Prøve Bokkilden for nyere bøker
		//$omslag = plugins_url( 'ikke_digital.jpg', __FILE__ ); 
		if ($isisbn == "1") {
			$isbnsearch = "http://partner.bokkilden.no/SamboWeb/partner.do?format=XML&uttrekk=5&ept=3&xslId=117&enkeltsok=" . $id;
			$firsttry = simplexml_load_file ($isbnsearch);
			$omslag = $firsttry->Produkt->BildeURL;
			$omslag = str_replace ("width=80" , "" , $omslag); // knegg knegg, dirty hack
			if (!isset($treff['beskrivelse'])) {
			$beskrivelse = infoboks_trunc($firsttry->Produkt->Ingress , 20);
			}
		}
	}

// Fallback for intet bilde
if (trim($omslag) == "") {
	$omslag = plugins_url('/ikke_digital.jpg',__FILE__ );
}

// FINNE ANNET

/********************* BØKER ******************/

if (stristr(strtolower($mediatype), "bøker")) {
	$tittel = infoboks_trunc($childxmldata->titleInfo->title, 10);
	$beskrivelse = '<span class="bibvenn_infoboks_byline">';

	if ($nb->namecreator == "") {
		$beskrivelse .= "N.N.";
	} else {
		$beskrivelse .= infoboks_trunc($nb->namecreator, 5);
	}

	$beskrivelse .= "</span><br /><br />\n";

	$beskrivelse .= "<b>Utgitt: </b><br />" . $childxmldata->originInfo->publisher . ", " . $childxmldata->originInfo->place->placeTerm . " " . $nb->year . "<br />\n";
	$beskrivelse .= "<b>Omfang: </b><br />" . $childxmldata->physicalDescription->extent . "<br />";
	
}

/********************* AVISER ******************/

if (strtolower($mediatype) == "aviser") {
	$tittel = trim(substr($childxmldata->titleInfo->title, 0, -10));
	$dato = trim(substr($childxmldata->titleInfo->title, -10));
	$dato = substr($dato, -2) . "." . substr($dato, 5, 2) . "." . substr($dato, 0, 4);
	$beskrivelse = "<br /><b>Dato: </b><br />" . $dato . "<br />\n";	

}

// Tilgjengelig online? Kjøre flere tester
	$tilgjengelig = 0;
	$tilgjengeligtekst = '';
	// Hvis contentclass inneholder "public" eller "bokhylla" er den 99.99% sikkert tilgjengelig
	if (stristr($contentclasses, "public") || stristr($contentclasses, "bokhylla")) {
		$tilgjengelig = 1;
	}
		
// Grønn dott hvis tilgjengelig online, uavhengig av materialtype
	if ($tilgjengelig == "1") {
		$tilgjengeligtekst .= '<br /><span class="bibvenn_infoboks_tilgjengelig">Kan leses online!</span>';
		}


$htmlout = str_replace ("urlString" , $url, $outputhtml);
$htmlout = str_replace ("bildeString" , $omslag , $htmlout);	
$htmlout = str_replace ("tittelString" , $tittel , $htmlout);
$htmlout = str_replace ("descriptionString" , $beskrivelse , $htmlout);
$htmlout = str_replace ("tilgjengeligString" , $tilgjengeligtekst , $htmlout);

return $htmlout;
	
}
// Registrer shortcode

function bibvenn_infoboks_register_shortcodes(){
   add_shortcode('bibvenn_infoboks', 'bibvenn_infoboks_function');
}

add_action( 'init', 'bibvenn_infoboks_register_shortcodes');

// Push button into editor

function register_button( $buttons ) {
   array_push( $buttons, "|", "bibvenn_infoboks" );
   return $buttons;
}

function add_plugin( $plugin_array ) {
   $plugin_array['bibvenn_infoboks'] = plugins_url( 'tinymce_knapp.js', __FILE__ );
   return $plugin_array;
}

function infoboks_button() {

   if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') ) {
      return;
   }

   if ( get_user_option('rich_editing') == 'true' ) {
      add_filter( 'mce_external_plugins', 'add_plugin' );
      add_filter( 'mce_buttons', 'register_button' );
   }

}

add_action('init', 'infoboks_button');
