<?php

/*
 * CLASS: paragon_jaro_winkler_child_products
 * Meta: A search function based on the jaro winkler similarity, which itself needs to be in mysql as a stored procedure and is included in this file as comments at the bottom
 *
 * Features: Primary and secondary search fields, with result scoring based on where the match was found and if it was exact
 * 	     Returns results sorted by relevence 
 *           Searches both items and item categories separately 
 * 	     Notifies if a match is of potentially low quality
 *           Progressively searches deeper and with shorter word stems if no matches are found 
 * 	     - this was found to be the best way in testing rather than continually searching and getting less relevent
*/
class paragon_jaro_winkler_child_products{

function __construct(){
}

function paragon_jw_search($search_string,$field_list){
	$field_list="ID,".$field_list;
	$pk="ID";
	global $db;
	$primary_field_string="name";
	$secondary_field_string="description";
	$stem_length=3;
	$first_word_stem_length=4;
	$second_time_stem_length=2;
	$score1=1;
	$score2=0.99;
	$score3=0.98;
	$score4=0.97;

	// set up initial variables
	$primary_fields=explode(",",$primary_field_string);
	$secondary_fields=explode(",",$secondary_field_string);
	$final_results_array=array();
	$final_category_results_array=array();
	$words=explode(" ",$search_string);

	if (!$search_string){ format_error("No search text was entered.",1); }

	// The first search is for product categories. This will provide links to the categories themselves. 
	// Subsequent searches are through the products table.
	$sql_fields=array();
	$sql="SELECT id,category_name,html_page_name,\"$score1\" AS score FROM product_categories WHERE active=1 AND ";
	foreach ($words AS $word){
		array_push($sql_fields,"category_name LIKE \"$word%\"");
	}
	$sql .= implode(" OR ", $sql_fields);
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		$h['name']="Category: " . $h['category_name'];
		if (!array_key_exists($h[$pk],$final_category_results)){
			$final_category_results[$h[$pk]]=$h;
		}
	}

	// Product search - exact matches without trailing chars in primary field get a score of $score1
	$sql_fields=array();
	$sql="SELECT $field_list,\"$score1\" AS score FROM products WHERE (child_product IS NULL OR child_product = 0 or child_product=\"\") AND ";
	foreach ($primary_fields AS $pfield){
		array_push($sql_fields,"$pfield LIKE \"$search_string%\"");
	}
	$sql .= implode(",", $sql_fields);
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		if (!array_key_exists($h[$pk],$final_results)){
			$final_results[$h[$pk]]=$h;
		}
	}

	// Product search - exact phrase match in the primary field, anywhere in the field. Primary field may contain extra text for a score of $score2.
	$sql_fields=array();
	$sql="SELECT $field_list,\"$score2\" AS score FROM products WHERE (child_product IS NULL OR child_product = 0 or child_product=\"\") AND ";
	foreach ($primary_fields AS $pfield){
		array_push($sql_fields,"$pfield LIKE \"%$search_string%\"");
	}
	$sql .= implode(",", $sql_fields);
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		if (!array_key_exists($h[$pk],$final_results)){
			$final_results[$h[$pk]]=$h;
		}
	}

	// Product search - exact phrase match in the secondary field - anywhere in field 
	$sql_fields=array();
	$sql="SELECT $field_list,\"$score3\" AS score FROM products WHERE (child_product IS NULL OR child_product = 0 or child_product=\"\") AND ";
	foreach ($secondary_fields AS $sfield){
		array_push($sql_fields,"$sfield LIKE \"%$search_string%\"");
	}
	$sql .= implode(",", $sql_fields);
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		//print $h[$pk] . " - " . $h['name'] . " - " . $h['score'] ."\n";
		if (!array_key_exists($h[$pk],$final_results)){
			$final_results[$h[$pk]]=$h;
		}
	}


	// Product search - each word separately in primary field
	$sql_fields=array();
	$sql="SELECT $field_list,\"$score4\" AS score FROM products WHERE (child_product IS NULL OR child_product = 0 or child_product=\"\") AND ";
	foreach ($primary_fields AS $pfield){
		array_push($sql_fields,"$pfield LIKE \"%$search_string%\"");
	}
	$sql .= implode(",", $sql_fields);
	$res=$db->query($sql);
	while($h=$db->fetch_array($res)){
		//print $h[$pk] . " - " . $h['name'] . " - " . $h['score'] ."\n";
		if (!array_key_exists($h[$pk],$final_results)){
			$final_results[$h[$pk]]=$h;
		}
	}

	if (!$final_results) {
		$response = "An exact match could not be found. The closest matches to $search_string are";
	} else {
		$response = "Search Results";
		$response="";
	}

	// FIRST JARO WINKLER SERCH
	$final_results=$this->jaro_winkler($search_string,$first_word_stem_length,$stem_length,"AND",0,$final_results,$field_list,$pk);
	if (count($final_results)==0){
		$final_results=$this->jaro_winkler($search_string,$second_time_stem_length,$second_time_stem_length,"AND",0,$final_results,$field_list,$pk);
	}
	// NEXT JARO IF NO RESULTS
	if (count($final_results)==0){
		$final_result=$this->jaro_winkler($search_string,$first_word_stem_length,$stem_length,"OR",10,$final_results,$field_list,$pk);
	}

	// find out what our highest score actually is, it may be low and hence throw back totally irrelevant results
	foreach ($final_results AS $result=>$resultdata){
		$topscore=$resultdata['score'];
		break;
	}
	if ($topscore < 0.75){
		$response = "Sorry - we could not find any match for $search_string, however the closest matches to your search phrase (searching through product descriptions) are listed below:";
	}
	$print_now=0;
	if ($print_now){
		print "<p>$response</p>";
		print "<table>";
		foreach ($final_results AS $result=>$resultdata){
			print "<tr><td>$result</td><td>".$resultdata['name']."</td><td>".$resultdata['score']."</td></tr>";
		}
		print "</table><br>";
		if ($final_category_results){
		print "Category Results:<br />The following product categories may be of interest:<br /><br />";
		print "<table>";
		foreach ($final_category_results AS $result=>$resultdata){
			print "<tr><td>$result</td><td>".$resultdata['name']."</td><td>".$resultdata['score']."</td></tr>";
		}
		print "</table><br>";
		}
	}
	$return['category_results']=$final_category_results;
	$return['results']=$final_results;
	$return['search_response_message']=$response;
	return $return;
}

function jaro_winkler($search_string,$first_word_stem_length,$subsequent_word_stem_length,$and_or_or,$limit,$final_results,$field_list,$pk){
	$sql="SELECT $field_list, jaro_winkler_similarity(`name`, \"$search_string\") AS score FROM (SELECT $field_list FROM products WHERE (child_product IS NULL OR child_product = 0 or child_product=\"\") AND (";
	$where_clauses=array();
	$wordcounter=0;
	$words=explode(" ",$search_string);
	foreach ($words as $eachword){
		$this_word_stem_length=$first_word_stem_length;
		if ($wordcounter>0 && $subsequent_word_stem_length){
			$this_word_stem_length = $subsequent_word_stem_length;
		}
		$wordcounter++;
		$eachword=substr($eachword,0,$this_word_stem_length);
		array_push($where_clauses,"name LIKE \"%$eachword%\"");
		
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

/* The 'jaro winkler similarity' code below should be entered into mysql as a stored procedure */

/*
DELIMITER $$

CREATE DEFINER=`root`@`localhost` FUNCTION `jaro_winkler_similarity`(
in1 varchar(255),
in2 varchar(255)
) RETURNS float
DETERMINISTIC
BEGIN
#finestra:= search window, curString:= scanning cursor for the original string, curSub:= scanning cursor for the compared string
declare finestra, curString, curSub, maxSub, trasposizioni, prefixlen, maxPrefix int;
declare char1, char2 char(1);
declare common1, common2, old1, old2 varchar(255);
declare trovato boolean;
declare returnValue, jaro float;
set maxPrefix=6; #from the original jaro - winkler algorithm
set common1="";
set common2="";
set finestra=(length(in1)+length(in2)-abs(length(in1)-length(in2))) DIV 4
+ ((length(in1)+length(in2)-abs(length(in1)-length(in2)))/2) mod 2;
set old1=in1;
set old2=in2;

#calculating common letters vectors
set curString=1;
while curString<=length(in1) and (curString<=(length(in2)+finestra)) do
set curSub=curstring-finestra;
if (curSub)<1 then
set curSub=1;
end if;
set maxSub=curstring+finestra;
if (maxSub)>length(in2) then
set maxSub=length(in2);
end if;
set trovato = false;
while curSub<=maxSub and trovato=false do
if substr(in1,curString,1)=substr(in2,curSub,1) then
set common1 = concat(common1,substr(in1,curString,1));
set in2 = concat(substr(in2,1,curSub-1),concat("0",substr(in2,curSub+1,length(in2)-curSub+1)));
set trovato=true;
end if;
set curSub=curSub+1;
end while;
set curString=curString+1;
end while;
#back to the original string
set in2=old2;
set curString=1;
while curString<=length(in2) and (curString<=(length(in1)+finestra)) do
set curSub=curstring-finestra;
if (curSub)<1 then
set curSub=1;
end if;
set maxSub=curstring+finestra;
if (maxSub)>length(in1) then
set maxSub=length(in1);
end if;
set trovato = false;
while curSub<=maxSub and trovato=false do
if substr(in2,curString,1)=substr(in1,curSub,1) then
set common2 = concat(common2,substr(in2,curString,1));
set in1 = concat(substr(in1,1,curSub-1),concat("0",substr(in1,curSub+1,length(in1)-curSub+1)));
set trovato=true;
end if;
set curSub=curSub+1;
end while;
set curString=curString+1;
end while;
#back to the original string
set in1=old1;

#calculating jaro metric
if length(common1)<>length(common2)
then set jaro=0;
elseif length(common1)=0 or length(common2)=0
then set jaro=0;
else
#calcolo la distanza di winkler
#passo 1: calcolo le trasposizioni
set trasposizioni=0;
set curString=1;
while curString<=length(common1) do
if(substr(common1,curString,1)<>substr(common2,curString,1)) then
set trasposizioni=trasposizioni+1;
end if;
set curString=curString+1;
end while;
set jaro=
(
length(common1)/length(in1)+
length(common2)/length(in2)+
(length(common1)-trasposizioni/2)/length(common1)
)/3;

end if; #end if for jaro metric

#calculating common prefix for winkler metric
set prefixlen=0;
while (substring(in1,prefixlen+1,1)=substring(in2,prefixlen+1,1)) and (prefixlen<6) do
set prefixlen= prefixlen+1;
end while;


#calculate jaro-winkler metric
return jaro+(prefixlen*0.1*(1-jaro));
END
*/
?>
