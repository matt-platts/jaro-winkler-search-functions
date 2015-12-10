<?php

/*
 * CLASS: paragon_gonzo_search
 * Meta: A search function spanning products and product categories separately 
 *
 * Features: Primary, secondary and tertiary search fields, with result scoring based on where the match was found and if it was exact
 * 	     Returns results sorted by relevence 
 *           Searches both items and item categories separately 
 * 	     Notifies if a match is of potentially low quality (if no good quality matches are found)
*/
class paragon_gonzo_search {

function __construct(){
}

function custom_search($dbf_search_for,$field_list){
	$field_list="ID,".$field_list;
	$pk="ID";
	global $db;
	$primary_field_string="artists.artist"; // This is our no.1 target field for searching in
	$secondary_field_string="title, labels.label_name"; // These are sub-target fields
	$tertiary_field_string="full_description"; // fields for a lower match score still
	$stem_length=3;
	$first_word_stem_length=4;
	$second_time_stem_length=2;
	$score1=1; // a direct hit (perfect exact match) on our most revered fields
	$score2=0.99; // exact match on primary field but primary fields may contain other text as well 
	$score3=0.98; // exact match but on secondary field list
	$score4=0.97; // searches for presence of all words in both primary and secondary fields
	$score5=0.96; // searches for an exact match in the tertiary field list

	// process initial vaariables
	$primary_fields=explode(",",$primary_field_string);
	$secondary_fields=explode(",",$secondary_field_string);
	$tertiary_fields=explode(",",$tertiary_field_string);
	$final_results_array=array();
	$final_category_results_array=array();
	$words=explode(" ",$dbf_search_for);
	$new_words=array();
	foreach ($words as $word){
			if ($word != "and"){ // lets not actually search for the word and, and focus on relevent words only
				array_push($new_words,$word);
			}
	}
	$words=$new_words; 

	if (!$dbf_search_for){ format_error("no search for so exit",1); }
	// category search 
	$sql_fields=array();
	$sql="SELECT ID,artist,\"$score1\" AS score FROM artists WHERE active =1 AND (";
	$countpercent="";
	foreach ($words AS $word){
		array_push($sql_fields,"artist LIKE \"" . $countpercent . "$word%\"");
		$countpercent="%";
	}
	$sql .= implode(" AND ", $sql_fields);
	$sql .= ")";
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		//print $h[$pk] . " - " . $h['name'] . " - " . $h['score'] ."\n";
		$h['title']="Category: " . $h['artist'];
		if (!array_key_exists($h[$pk],$final_category_results)){
			$final_category_results[$h[$pk]]=$h;
		}
	}
	
	// If no exact category matches, check for the presence of ANY words in the category
	if (sizeof($final_category_results)==0){
		$sql_fields=array();
		$sql="SELECT ID,artist,\"$score1\" AS score FROM artists WHERE active =1 AND (";
		foreach ($words AS $word){
			array_push($sql_fields,"artist LIKE \"" . $countpercent . "%$word%\"");
		}
		$sql .= implode(" OR ", $sql_fields);
		$sql .= ")";
		$res=$db->query($sql);
		while($h=$db->fetch_array($res)){
			//print $h[$pk] . " - " . $h['name'] . " - " . $h['score'] ."\n";
			$h['title']="Category: " . $h['artist'];
			if (!array_key_exists($h[$pk],$final_category_results)){
				$final_category_results[$h[$pk]]=$h;
			}
		}
		

	}
	//var_dump($final_category_results);

	// GONAO - MODIFY FIELD LIST
	$modified_field_list="products.ID AS ID,products.artist,title,format,catalogue_number,price,full_description,products.image,label,release_date,allow_pre_orders,available";
	$modified_tables="products INNER JOIN artists ON products.artist = artists.ID INNER JOIN labels ON products.label=labels.id";

	// exact matches in primary field
	$sql_fields=array();
	$sql="SELECT $modified_field_list,\"$score1\" AS score FROM $modified_tables WHERE (available = 1 OR (products.release_date >= NOW() AND allow_pre_orders=1)) AND hidden != 1 AND ";
	foreach ($primary_fields AS $pfield){
		array_push($sql_fields,"$pfield LIKE \"$dbf_search_for%\"");
	}
	$sql .= implode(",", $sql_fields);
	$sql .= "ORDER BY artists.artist,title";
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		//print $h[$pk] . " - " . $h['name'] . " - " . $h['score'] ."\n";
		if (!array_key_exists($h[$pk],$final_results)){
			$final_results[$h[$pk]]=$h;
		}
	}

	//print "Exact phrase match - primary field - anywhere in field\n\n";
	$sql_fields=array();
	$sql="SELECT $modified_field_list,\"$score2\" AS score FROM $modified_tables WHERE (available = 1 OR (products.release_date >= NOW() AND allow_pre_orders=1)) AND hidden != 1 AND ";
	foreach ($primary_fields AS $pfield){
		array_push($sql_fields,"$pfield LIKE \"%$dbf_search_for%\"");
	}
	$sql .= implode(",", $sql_fields);
	$sql .= "ORDER BY artists.artist,title";
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		//print $h[$pk] . " - " . $h['name'] . " - " . $h['score'] ."\n";
		if (!array_key_exists($h[$pk],$final_results)){
			$final_results[$h[$pk]]=$h;
		}
	}

	//print "Exact phrase match - secondary field - anywhere in field\n\n";
	$sql_fields=array();
	$sql="SELECT $modified_field_list,\"$score3\" AS score FROM $modified_tables WHERE (available = 1 OR (products.release_date >= NOW() AND allow_pre_orders=1)) AND hidden != 1 AND "; 
	foreach ($secondary_fields AS $sfield){
		array_push($sql_fields,"$sfield LIKE \"%$dbf_search_for%\"");
	}
	$sql .= implode(" OR ", $sql_fields);
	$sql .= "ORDER BY artists.artist,title";
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		//print $h[$pk] . " - " . $h['name'] . " - " . $h['score'] ."\n";
		if (!array_key_exists($h[$pk],$final_results)){
			$final_results[$h[$pk]]=$h;
		}
	}


	//print "Each word separately substr 4 \n\n";
	$sql_fields=array();
	$sub_sql_fields=array();
	$sql="SELECT $modified_field_list,\"$score4\" AS score FROM $modified_tables WHERE (available = 1 OR (products.release_date >= NOW() AND allow_pre_orders=1)) AND hidden != 1 AND ("; 
		foreach ($primary_fields AS $pfield){
			foreach ($words as $word){
				array_push($sub_sql_fields,"$pfield LIKE \"%$word%\"");
			}
		array_push($sql_fields,join(" AND ",$sub_sql_fields));
		$sub_sql_fields = array();

		}
		foreach ($secondary_fields AS $pfield){
			foreach ($words as $word){
				array_push($sub_sql_fields,"$pfield LIKE \"%$word%\"");
			}
		array_push($sql_fields,join(" AND ",$sub_sql_fields));
		$sub_sql_fields = array();
		}
	
	if ($sql_fields){
		$sql .= "(";
		$sql .= implode(") OR (", $sql_fields);
		$sql .= ")";
	}

	$sql .= ") ORDER BY artists.artist,title";
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		//print $h[$pk] . " - " . $h['name'] . " - " . $h['score'] ."\n";
		if (!array_key_exists($h[$pk],$final_results)){
			$final_results[$h[$pk]]=$h;
		}
	}

	// description exact
	$sql_fields=array();
	$sql="SELECT $modified_field_list,\"$score5\" AS score FROM $modified_tables WHERE (available = 1 OR (products.release_date >= NOW() AND allow_pre_orders=1)) AND hidden != 1 AND "; 
	foreach ($tertiary_fields AS $pfield){
		array_push($sql_fields,"$pfield LIKE \"%$dbf_search_for%\"");
	}
	$sql .= implode(",", $sql_fields);
	$sql .= "ORDER BY artists.artist,title";
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		//print $h[$pk] . " - " . $h['name'] . " - " . $h['score'] ."\n";
		if (!array_key_exists($h[$pk],$final_results)){
			$final_results[$h[$pk]]=$h;
		}
	}

	if (!$final_results) {
		$response = "An exact match could not be found. The closest matches to $dbf_search_for are";
	} else {
		$response = "Search Results";
		$response="";
	}

	// FIRST JARO WINKLER SERCH

	// find out what our highest score actually is, it may be low nd hence throw back totally irrelevant results
	foreach ($final_results AS $result=>$resultdata){
		$topscore=$resultdata['score'];
		break;
	}
	if ($topscore < 0.97){
		$response = "Sorry - we could not find any match for $dbf_search_for, however the closest matches to your search phrase (searching through product descriptions) are listed below:";
	}
	$print_now=0;
	if ($print_now){
		print "<p>$response</p>";
		print "<table>";
		foreach ($final_results AS $result=>$resultdata){
			print "<tr><td>$result</td><td>".$resultdata['title']."</td><td>".$resultdata['score']."</td></tr>";
		}
		print "</table><br>";
		if ($final_category_results){
		print "Category Results:<br />The following product categories may be of interest:<br /><br />";
		print "<table>";
		foreach ($final_category_results AS $result=>$resultdata){
			print "<tr><td>$result</td><td>".$resultdata['title']."</td><td>".$resultdata['score']."</td></tr>";
		}
		print "</table><br>";
		}
	}
	$return['category_results']=$final_category_results;
	$return['results']=$final_results;
	$return['search_response_message']=$response;
	return $return;
}

function jaro_winkler($dbf_search_for,$first_word_stem_length,$subsequent_word_stem_length,$and_or_or,$limit,$final_results,$field_list,$pk){
	$sql="SELECT $field_list, jaro_winkler_similarity(`title`, \"$dbf_search_for\") AS score FROM (SELECT $field_list FROM products WHERE available IN (1,5) AND (";
	$where_clauses=array();
	$wordcounter=0;
	$words=explode(" ",$dbf_search_for);
	foreach ($words as $eachword){
		$this_word_stem_length=$first_word_stem_length;
		if ($wordcounter>0 && $subsequent_word_stem_length){
			$this_word_stem_length = $subsequent_word_stem_length;
		}
		$wordcounter++;
		$eachword=substr($eachword,0,$this_word_stem_length);
		array_push($where_clauses,"title LIKE \"%$eachword%\"");
		
	}
	$implode_string=" " . $and_or_or . " ";
	$sql.= implode($implode_string,$where_clauses);
	$sql.= ")) AS likeMatches ORDER BY score DESC";
	if ($limit){
		$sql .= " LIMIT $limit";
	}
	global $db;
	//print_debug($sql . "<br>");
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		//print $h[$pk] . " - " . $h['name'] . " - " . $h['score'] ."\n";
		if (!array_key_exists($h[$pk],$final_results)){
			$final_results[$h[$pk]]=$h;
		}
	}
	return $final_results;
}


}
?>
