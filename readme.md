Jaro-Winkler "Fuzzy match" searches

These classes were used as part of a framework I wrote where the search functionality needed something a little more complex than a basic mySQL LIKE "%%" query.

The core framework loaded specific search classes dependent on a configuration setting, so different companies had different search functionality.

The 'Jaro-Winkler similarity' was implemented as a stored procedure in mySQL. The code on top in the PHP would execute multiple searches, for example through different tables so that different results were presented differently. One example was providing product category page results as well as actual products.

Furthermore, scoring was implemented dependend on whether the match was exact, taking up all the field, in part of the field, as well as implementing primary and secondary fields. This was an effort to get some really good search results and give them some sense of importance.

Examples:
Exact match in primary database field (eg. product name, artist name)
Exact match in primary database field, but field contains extra text as well
All words match accross most important, and subsequently important database fields
