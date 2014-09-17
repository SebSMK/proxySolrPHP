<?php

/**
 * @file
 * Implements a Solr proxy.
 *
 * Currently requires json_decode which is bundled with PHP >= 5.2.0.
 *
 * You must download the SolrPhpClient and store it in the same directory as this file.
 *
 *   http://code.google.com/p/solr-php-client/
 */

require_once(dirname(__FILE__) . '/SolrPhpClient/Apache/Solr/Service.php');

?>

<form name="input" action="proxy.php" method="post">
q: <input type="text" name="query" value="facet=true&facet.field=artist_name_ss&facet.field=artist_natio&facet.field=object_production_century_earliest&facet.field=object_type&facet.field={!ex=category}category&facet.limit=-1&facet.mincount=1&rows=12&defType=edismax&json.nl=map&q=-(id_s%3A(*%2F*) AND category%3Acollections) -(id_s%3A(*verso) AND category%3Acollections)%2Cjorn&fq={!tag=category}category%3Acollections&qf= id^20 title_dk^15 title_eng^5 title_first^15 artist_name^15 page_content^10 page_title^15 description_note_dk^5 description_note_en^2 prod_technique_dk^5 prod_technique_en^2 object_type^10&sort=score desc&wt=json">
<input type="submit" value="Submit">
</form>



