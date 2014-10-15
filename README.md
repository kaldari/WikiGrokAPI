WikiGrokAPI
===========

An API for doing specialized queries against Wikidata items.

See: https://www.mediawiki.org/wiki/Extension:MobileFrontend/WikiGrok

## Actions
### get_potential_occupations
Returns potential occupation claims for an item based on contents of Wikipedia article.

Example:<br/>
https://tools.wmflabs.org/wikigrok/api.php?action=get_potential_occupations&item=508703

### get_potential_nationality
Returns a potential nationality claim for an item based on contents of Wikipedia article.

Example:<br/>
https://tools.wmflabs.org/wikigrok/api.php?action=get_potential_nationality&item=16999217

### record_answer
Record a claim about a Wikidata item

Example:<br/>
https://tools.wmflabs.org/wikigrok/api.php?action=record_answer&subject_id=Q3784220&subject=Anne+Dallas+Dudley&occupation_id=Q285759&occupation=insurance+broker&page_name=Anne_Dallas_Dudley&correct=0&user_id=645874&source=mobile+A