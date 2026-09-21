<?php
namespace App\Controllers;

use App\Controllers\Solr;
use App\Controllers\OpenAIVector;
use App\Enums\ResponseStatusEnum;

class SolrPodcastService extends Solr {    
    
    public $percentage = 90;
    public $openai_ct;
    
    public function __construct($vars = []) {	
        parent::__construct($vars);         
	}
	
	public function openai_ct() {
	    $this->openai_ct = new OpenAIVector();
	}
	
	public function init_apple_core() {
	    $solr = new Solr();
	    $this->solr_client->setDefaultEndPoint(SOLR_CORE_APPLE); 
	}
	
	public function init_apple_sugg_core() {
	    $solr = new Solr();
	    $this->solr_client->setDefaultEndPoint(SOLR_CORE_APPLE_SUGGESTION);
	}
	
	public function init_location_core() {
	    $solr = new Solr();
	    $this->solr_client->setDefaultEndPoint(SOLR_CORE_LOCATION);
	}
	
	public function detail($payload) {	
	    
	    if(empty($payload["site_id"])):
	       return false;
	    endif;
	    
	    $this->init_apple_core();
	    $site_id = str_replace(",", " ", $payload["site_id"]);	  
	    $hasEmail = isset($payload["has_email"]) && !empty($payload["has_email"]);
	    
	    $query = $this->solr_client->createSelect();	    
	    $query->setQuery("feed_id:($site_id)");
	    $query->addSort("feed_id", $query::SORT_ASC);	    
	    
	    if(!empty($payload["limit"]) && $payload["limit"] > 0):
            $query->setStart(0)->setRows($payload["limit"]);
	    endif;
	    
	    if(!isset($payload["ignore_cloumn"])):
	        //$query->setFields($this->set_fields());	
	    endif;
	    
	    if($hasEmail) {
            $fq = $query->createFilterQuery("has_email_filter");
            $fq->setQuery("{!tag=emailFilter}email_count:[1 TO *]");
        
            $stats = $query->getStats();
            $stats->createField("{!ex=emailFilter}email_count");
        }
	    
	    if(isset($payload["include_fields"]) && !empty($payload["include_fields"])):
	       $query->setFields($payload["include_fields"]);
	    endif;
	    
	    $results = $this->solr_client->select($query);	
	    
	    if(isset($payload["debug"]) && $payload["debug"]):
            //echo '<pre>';print_r($results); echo '<hr>';
            //$request = $this->solr_client->createRequest($query); echo $request->getUri(); die;
	    endif;
	    
	    $data = $this->format_data($results);
	    
        $response = ["data" => $data];
        
        if($hasEmail) {
            $statsResult = $results->getStats();
            $emailStats = $statsResult->getResult("email_count");    
            $response["total_email_count"] = $emailStats ? $emailStats->getSum() : 0;
        }
        
	    return $response;	    
	    //$this->sendJson(ResponseStatusEnum::SUCCESS, "", $data);
	}
	
	public function get_location_column($column, $type = 0) {
	    $column_name = $txt = "";
	    if($type == 1):
            $txt = "_text_suggest_ngram";
        elseif($type == 2):
            $txt = "_string";
        elseif($type == 3):
            $txt = "_phrase_ngram";
	    endif;
	    
	    $arr = [
	        "country_name" => "country_name$txt",
	        "state_name"   => "state_name$txt",
	        "city_name"    => "city_name$txt",
	        "location_name"    => "location_name$txt",
	    ];
	    
	    if(isset($arr[$column])):
	       $column_name = $arr[$column];
	    endif;
	    
	    return $column_name;	    
	}
	
	//location options
	public function location_option($payload) {
	    $this->init_location_core();
	    
	    $location_qry = $query_loc_arr = [];
	    $locations = isset($payload) ? $payload["location"] : [];
	    foreach($locations as $location):
    	    $loc_arr = [];
    	    foreach($location as $key => $value):    	       
                $loc_arr[] = "$key:$value";
    	    endforeach;
    	    $query_loc_arr[] = $loc_arr;
	    endforeach;
	    
	    if(!empty($query_loc_arr)):
	       foreach($query_loc_arr as $qrr):
	           $location_qry[] = "(".implode(" AND ", $qrr).")";
	       endforeach;
	    endif;
	    
	    $select = ["query" => "(".implode(" OR ", $location_qry).")"];
	    $query = $this->solr_client->createSelect($select);
	    $query->setStart($payload["offset"])->setRows($payload["limit"]);
	    
	    $results = $this->solr_client->select($query);	    
	    if($payload["debug"]):
            echo '<pre>';print_r($results); echo '<hr>';
            $request = $this->solr_client->createRequest($query); echo $request->getUri(); die;
	    endif;
	    
	    $properties = $results->getData();
	    $data = $this->prepare_location($properties);
	    return ["data" => $data];	    
	}
	
	//c1 filter suggestion
	public function location($payload) {
	    //$this->validateInput(["query" => "required", "columns" => "required|array"]);
	    $this->init_location_core();
	    
	    $group = $loc_arr = [];
	    $columns = $payload["columns"];
	    $group_field = $payload["group_field"];
	    
	    $query = trim(str_replace('"', '', $payload["query"]));
	    $query = $this->solr_escape($query);
	    $boosts = isset($payload["boost"]) ? $payload["boost"] : [];
	    
	    /*if(!empty($columns)):
    	    foreach($columns as $column):	    
    	       $column_name = $this->get_location_column($column, 3);	       
    	       $loc_arr[] = $column_name.':'.$query;	       
    	    endforeach;	  
	    endif;*/
	    
	    //$query_params = "(".implode(" OR ", $loc_arr).")^10";
	    $and_query = str_replace(" ", " AND ", $query);
	    
	    //$query_params = '(location_name_suggest_ngram:"'.$query.'")^200 OR (location_name_suggest_ngram:'.$and_query.')^100';
	    //$query_params .= ' OR (location_name:"'.$query.'")^150 OR (location_name_phrase_ngram:('.$query.'))^10';
	    
	    $query_params = 'location_name_phrase_ngram:("'.$query.'")';
	    $select = ["query" => $query_params];
	    
	    $query = $this->solr_client->createSelect($select);
	    $query->setStart($payload["offset"])->setRows($payload["limit"]);
	    
	    if(!empty($group)):
            foreach($group as $gkey => $gval):
    	       $query->addParam($gkey, $gval);
    	    endforeach;
	    endif;
	    
	    if(!empty($boosts)):
    	    foreach($boosts as $boost):
    	       //$query->addParam("bf", $boost);
    	       $query->addParam("sort", "$boost desc");
    	    endforeach;	   
    	    //$query->addParam("defType", "edismax");
	    endif;
	    
	    $results = $this->solr_client->select($query);
	    if($payload["debug"]):
    	    $request = $this->solr_client->createRequest($query);
    	    $fullUrl = rtrim($request->getHandler(), '/') . '?' . $request->getQueryString();
    	    echo $this->solr_client->getEndpoint()->getBaseUri() . $fullUrl;
    	    echo '<hr><pre>';
    	    print_r($results);
    	    echo '<hr>';die;
	    endif;
	    
	    $properties = $results->getData();	    
	    $data = $this->prepare_location($properties);
	    return ["data" => $data];
	}
	
	public function suggession($query, $limit = 10, $debug = 0) {
	    $this->init_apple_sugg_core();
	    
	    $select = ["query" => $query];
	    $query = $this->solr_client->createSelect($select);
	    $query->addParam("defType", "edismax");
	    $query->addParam("qf", "feed_name_exact^200 feed_name_autocomplete^100 feed_name_substring^60 feed_name^30 feed_name_stem^20 suggest_ngram^10 feed_name_back_ngram^5 feed_name_string^2");
	    $query->addParam("pf", "feed_name_exact^300 feed_name_autocomplete^200 feed_name^50 feed_name_stem^40");
	    $query->addParam("bf", "product(10,log(sum(review_count,1)))");
	    $query->addParam("ps", 0);
	    $query->addParam("df", "suggest_ngram");
	    $query->addParam("sort", "score desc, review_count desc");
	    $query->addParam("mm", 1);	    
	    $query->setFields(["feed_name", "feed_id", "feed_image_url"]);
	    
	    $query->setRows($limit);
	    $results = $this->solr_client->select($query);
	    
	    if($debug):
    	    $request = $this->solr_client->createRequest($query);
    	    $fullUrl = rtrim($request->getHandler(), '/') . '?' . $request->getQueryString();
    	    echo $this->solr_client->getEndpoint()->getBaseUri() . $fullUrl;
    	    echo '<hr><pre>';
    	    print_r($results);
    	    echo '<hr>';die;
	    endif;
	    
	    $data = $results->getData();
	    return $data["response"]["docs"];
	}
	
	public function search_inactive($payload) {
	    $this->init_apple_core();
	    
	    $select = 'feed_name_exact:"'.$payload["query"].'"';
	    //$select = "feed_id:1223820";
	    
	    $select = ["query" => $select];
	    
	    $query = $this->solr_client->createSelect($select);
	    $query->addParam("sort", "review_count desc");	
	    
	    $query->setStart(0)->setRows(1);
	    $results = $this->solr_client->select($query);
	    
	    if($payload["debug"]):
            $request = $this->solr_client->createRequest($query); echo $request->getUri(); 
            echo '<pre>';print_r($results); echo '<hr>';die;
	    endif;
	    
	    $data = $this->prepare_mp_data($results->getData(), 1);
	    return ["data" => $data];
	}
	
	public function review($payload = [], $return = 0, $is_brightdata = 0) {
	    $this->init_apple_core();
	    $name = !empty($payload["st_name"]) ? $payload["st_name"] : "max_review_count";
	    
	    if($is_brightdata):
	        unset($payload["bright_data"]);
	    endif;
	    
	    $filter = $this->filter_mp_search($payload);
	    $query = $this->solr_client->createSelect($filter["select"]);
	    $query->setRows(0);	    
	    
	    if(!empty($filter["fq"]) && false):
	       $query->addParam('fq', $filter["fq"]);
	    endif;
	    
	    $stats = $query->getStats();
	    $stats->createField($name);
	    
	    $result = $this->solr_client->select($query);	    
	    $st_result = $result->getStats();
	    $review = $st_result->getResult($name);
	    
	    $data = [
	        "min_count"    => (int)$review->getMin(),
	        "max_count"    => (int)$review->getMax()
	    ];
	    
	    if($return):
            return $data;
	    endif;
	    
	    return ["data" => $data];
	}
	
    private function solr_escape($string) {
        $pattern = '/([+\-!(){}\[\]^"~*?:\\/]|&&|\|\|)/';
        $string  = preg_replace($pattern, '\\\\$1', $string);
    
        if(substr($string, -1) === '\\'):
            $string .= '\\';
        endif;
    
        return $string;
    }
    
    private function build_qf_string($columns, $weights = []) {
        $qf_parts = [];
        foreach ($columns as $field) {
            $weight = $weights[$field] ?? 1;
            $qf_parts[] = "$field^$weight";
        }
        return implode(" ", $qf_parts);
    }
    
    private function scale_qf_weights($qf, $multiplier) {
        $parts = explode(" ", $qf);
        $scaled = [];
        foreach ($parts as $part) {
            if (strpos($part, '^') !== false) {
                list($field, $weight) = explode('^', $part);
                $new_weight = round(((float)$weight) * $multiplier, 2);
                $scaled[] = "$field^$new_weight";
            } else {
                $scaled[] = $part;
            }
        }
        return implode(" ", $scaled);
    }
    
    /*private function build_group_clause($term, $qf, $phrase = false, $require_all = true) {
        $term = $this->solr_escape($term);
        $term = str_replace("'", "\\'", $term);
        
        // pf/ps: boosts docs where words appear as an adjacent/near phrase,
        // in addition to the qf-based bag-of-words match. tie: blends field
        // scores instead of just taking the max, so a term matching two
        // fields scores a bit higher than matching just one.
        $tie = " tie='0.25' bq=''";
        $pf  = " pf='$qf' ps='2'";
        
        if ($phrase) {
            return "{!edismax qf='$qf'$tie v='\"$term\"'}";
        }
        
        $word_count = count(array_filter(explode(" ", trim($term))));
        $mm = ($word_count > 1 && $require_all) ? " mm='100%'" : "";
        return "{!edismax qf='$qf'$mm$tie$pf v='$term'}";
    }*/
    private function build_group_clause($term, $qf, $phrase = false, $require_all = true) {
        $term = $this->solr_escape($term);
        $term = str_replace("'", "\\'", $term);
        $tie = " tie='0.25' bq=''";
        
        if ($phrase) {
            return "{!edismax qf='$qf'$tie v='\"$term\"'}";
        }
        
        $word_count = count(array_filter(explode(" ", trim($term))));        
        $mm = ($word_count > 1 && $require_all) ? " mm='100%'" : "";
        
        // 3-tier phrase-proximity boost, per audit PDF's pf/pf2/pf3 pattern:
        // 2-word phrases get the strongest boost, 3-word a lighter one, 4+ lightest.
        // ps/ps2/ps3=1 (tight slop) per spec. Multipliers (2.5/1.5/1.0) are scaled
        // proportionally from our current qf weights, not a literal copy of the
        // PDF's numbers, since our field set differs from theirs — tune after
        // reviewing real results.
        $pf_clause = "";
        if ($word_count == 2) {
            $pf_clause = " pf='" . $this->scale_qf_weights($qf, 2.5) . "' ps='1'";
        } elseif ($word_count == 3) {
            $pf_clause = " pf2='" . $this->scale_qf_weights($qf, 1.5) . "' ps2='1'";
        } elseif ($word_count >= 4) {
            $pf_clause = " pf3='" . $this->scale_qf_weights($qf, 1.0) . "' ps3='1'";
        }
        
        return "{!edismax qf='$qf'$mm$pf_clause$tie v='$term'}";
    }
	
	public function search($payload, $stats = 0) {
	    $this->init_apple_core();
	    $filter = $this->filter_mp_search($payload);
	    $is_count = isset($payload["count"]) && $payload["count"] == 1 ? 1 : 0;
	    
	    //timer_mark("inside_solr");
	    
	    if($payload["core"] == 1 && !empty($payload["vector_query"])):
	       timer_mark("before_vector_search");
	       $this->openai_ct();
	       $response = $this->openai_ct->process_query_vector($filter, $payload);
	       timer_mark("after_vector_search");
	       return $response;
	    endif;
	    
	    $select = $filter["select"];
	    if(!empty($filter["main_query"])) {
	        $select = [];  // don't set a query here — Spot 2 sets q= directly via setQuery()
	    }	    
	    $query = $this->solr_client->createSelect($select);
	    
	    if (!empty($filter["group_params"])) {
	        foreach ($filter["group_params"] as $pname => $pvalue) {
	            $query->addParam($pname, $pvalue);
	        }
	    }
	    
	    $review_min_max = [];
	    if(isset($payload["review_min_max"]) && $payload["review_min_max"] == 1):
	       $review_min_max = $this->review($payload, 1, $payload["bright_data"]);
	    endif;
	    
	    if(isset($payload["cursor_mark"]) && !empty($payload["cursor_mark"])):
            $query->addParam("cursorMark", $payload["cursor_mark"]);
            $query->setRows($payload["limit"]);
	    else:
	        $payload["limit"] = $is_count ? 0 : $payload["limit"];
            $query->setStart((int)($payload["offset"] ?? 0))->setRows((int)$payload["limit"]);
	    endif;
	    
	    if(!empty($filter["fq"])):
	        $query->addParam('fq', $filter["fq"]);
	    endif;
	    
	    if(isset($payload["fields"]) && !empty($payload["fields"])):
	       $query->setFields(json_decode($payload["fields"], true));
	    endif;
	    
	    if(!$is_count && !isset($payload["knn_request"]) && empty($filter["main_query"]) && isset($payload["weights"]) && !empty($payload["weights"])) {
	        $weights = [];
	        foreach ($payload["weights"] as $field => $weight) {
	            if ($field == "total_apple_review_count") {
	            } else {
	                $weights[] = $field . '^' . $weight;
	            }
	        }
	        $edismax = $query->getEDisMax();
	        $edismax->setQueryFields(implode(" ", $weights));
	    }
	    
	    $boosts = [];
	    $mult_boosts = [];
	    
	    if(!$is_count && !isset($payload["knn_request"]) && !empty($payload["pin_feed_id"]) && empty($filter["fq"])):
	       $boosts[] = 'feed_id:"'.(int)$payload["pin_feed_id"].'"^100000';
	    endif;
	    
	    if(!isset($payload["knn_request"]) && !empty($filter["boost_query"]) && !empty($payload["weights"])):
    	    foreach($filter["boost_query"] as $bquery):    	    
        	    if(empty(trim($bquery))):
        	       continue;
        	    endif;
        	    
        	    foreach($payload["weights"] as $field => $weight):
            	    if($field != "total_apple_review_count"):
            	       $boosts[] = $field . ':"' . $this->solr_escape($bquery) . '"^' . $weight;
            	    endif;
        	    endforeach;
    	    endforeach;
	    endif;
    	    
	    if(!$is_count && !isset($payload["knn_request"])):
    	    $mult_boosts[] = "min(3.0,sum(1,mul(0.25,log(sum(1,def(total_apple_review_count,0))))))";
	    endif;	 
	    
	    if(!$is_count && !isset($payload["knn_request"]) && !empty($payload["deboost"])) {
	        foreach ($payload["deboost"] as $field) {
	            $age = "ms(NOW/DAY,$field)";
	            $mult_boosts[] = "if(gt($age,6.3e11),0.6,max(0.5,recip(max(0,$age),3.171e-11,1,1)))";
	        }
	    }
	    
	    if (!$is_count && !isset($payload["knn_request"]) && !empty($mult_boosts) && !empty($filter["main_query"])) {
	        $pop_expr = count($mult_boosts) > 1 ? "product(" . implode(",", $mult_boosts) . ")" : $mult_boosts[0];
	        $query->addParam("pop", $pop_expr);
	        $query->addParam("main", $filter["main_query"]);
	        $query->setQuery("{!boost b=\$pop v=\$main}");
	    } elseif (!$is_count && !isset($payload["knn_request"]) && !empty($mult_boosts)) {
	        $query->getEDisMax();
	        $query->addParam("boost", count($mult_boosts) > 1 ? "product(" . implode(",", $mult_boosts) . ")" : $mult_boosts[0]);
	    }
	    
	    //$query->addParam('debugQuery', 'true');
	    
	    /*$sort = "score desc, id asc";
	    if(!$is_count && !isset($payload["knn_request"]) && !empty($payload["pin_feed_id"])):
    	    $query->addParam("pin_q", 'feed_id:"'.(int)$payload["pin_feed_id"].'"');
    	    $sort = "{!func}if(query(\$pin_q),2,1) desc, ".$sort;
	    endif;*/	    
	    
	    $sort = "score desc, id asc";	    
	    if(!$is_count && !isset($payload["knn_request"])):
    	    $bury_expr = "if(or(gt(ms(NOW/DAY,last_original_article_creation_date),6.31e10),and(lte(entry_count,3),gt(ms(NOW/DAY,last_original_article_creation_date),3.16e10))),1,0)";
    	    $sort = $bury_expr." asc, ".$sort;
	    endif;
	    
        if(!$is_count && !isset($payload["knn_request"]) && !empty($sort)):
            if(isset($payload["sort_by"]) && !empty($payload["sort_by"])):
	           $dir = $payload["sort_dir"] == "asc" ? "asc" : "desc";
            endif;	   
    	    
            if(!empty($payload["sort_by"])):
                $sort = $payload["sort_by"]." ".$dir.", $sort";
    	    endif;
            $query->addParam("sort", $sort);
	    endif;
	    
	    if(!$is_count && !isset($payload["knn_request"]) && !empty($boosts) && empty($filter["main_query"])):
	       $query->addParam("bq", implode(" ", $boosts));
	    endif;
	    
	    if(!empty($filter["group"])):	        
	        foreach($filter["group"] as $gkey => $gval):
	            $query->addParam($gkey, $gval);
	        endforeach;
	    endif;	
	    
	    try {
            $results = $this->solr_client->select($query);	
            
            $solr_url = "";            
            if(!empty($payload["auto_id"]) && isAdmin($payload["auto_id"])):
                $request = $this->solr_client->createRequest($query);
                $fullUrl = rtrim($request->getHandler(), '/') . '?' . $request->getQueryString();
                $solr_url = $this->solr_client->getEndpoint()->getBaseUri() . $fullUrl;	
            endif;
            
    	    if($payload["debug"] && isAdmin($payload["auto_id"])):	 
        	    echo "solr debug <hr> $solr_url <pre>"; 
        	    print_r($results);
        	    echo '<hr>';die;
    	    endif;
    	    
    	    $properties = $results->getData();	  
    	    $data = $this->prepare_mp_data($properties, 0, $payload);
    	    $data["review_min_max"] = $review_min_max;
    	    $data["solr_url"] = $solr_url;
	    
        } catch (\Solarium\Exception\HttpException $e) {            
            return [
                "data"      => [],
                "error"     => "Search temporarily unavailable",
                "detail"    => $e
            ];
            
        } catch (\Solarium\Exception\ExceptionInterface $e) {
            return [
                "data"  => [],
                "error" => "Search service error"
            ];
        
        } catch (\Throwable $e) {        
            return [
                "data"  => [],
                "error" => "Unexpected error occurred"
            ];
        }
        
	    return ["data" => $data];
	}
	
	private function prepare_location($array) {
	    $final_docs = [];
	    $exclude_keys = [
	        'id',
	        '_version_',
	        'score',
	        "is_indexed",
	        "status",
	    ];
	    
	    $total_records = (int)$array['response']['numFound'];
        if($total_records > 0):
            if(isset($array['response']['docs']) && is_array($array['response']['docs'])):
        	    foreach($array['response']['docs'] as $doc):
            	    $doc = array_diff_key($doc, array_flip($exclude_keys));
            	    $final_docs[] = array_map("trim", $doc);
        	    endforeach;
    	    endif;
	    endif;
	    
	    $response = [
	        "location"     => $final_docs,
	        "totalRecords" => $total_records
	    ];
	    return $response;
	}
	
	private function prepare_mp_data($array, $ignore_doc_by_score = 0, $payload = []) {
	    $final_docs = [];	    
	    $exclude_keys = [
	        'id',
	        '_version_',
	        'parent_categories',
	        'notes',
	    ];
	    
	    $total_records = (int)$array['response']['numFound'];	
	    $max_score = !empty($array["response"]["maxScore"]) ? $array["response"]["maxScore"] : 1.0;
	    
	    $cursor_mark = $array["nextCursorMark"];
	    if($total_records > 0): 
	        if(isset($array['response']['docs']) && is_array($array['response']['docs'])):            
	            $highlighting = $array["highlighting"];	            
	            foreach($array['response']['docs'] as $doc):
	                $doc = array_diff_key($doc, array_flip($exclude_keys));
	            
	                if(!isset($payload["knn_request"])):	                
    	                $doc["email_json"] = isset($doc["email_json"]) ? un_escape_solr_special_chars($doc["email_json"]) : "";
    	                $doc["categories"] = isset($doc["categories"]) ? un_escape_solr_special_chars($doc["categories"]) : "";
    	                $doc["social_handles"] = isset($doc["social_handles"]) ? un_escape_solr_special_chars($doc["social_handles"]) : "";
    	                $doc["beats_name"] = isset($doc["beats_name"]) ? un_escape_solr_special_chars($doc["beats_name"]) : "";
    	                $doc["network_name"] = isset($doc["network_name"]) ? un_escape_solr_special_chars($doc["network_name"]) : "";
    	                $doc["guest_names"] = isset($doc["guest_names"]) ? un_escape_solr_special_chars($doc["guest_names"]) : "";
    	                $doc["sponsor_names"] = isset($doc["sponsor_names"]) ? un_escape_solr_special_chars($doc["sponsor_names"]) : "";
    	                $doc["beats_id"] = isset($doc["beats_id"]) ? un_escape_solr_special_chars($doc["beats_id"]) : "";
    	                $doc["itune_contact_json"] = isset($doc["itune_contact_json"]) ? un_escape_solr_special_chars($doc["itune_contact_json"]) : "";
    	                $doc["percentage"] = isset($doc["score"]) && !empty($doc["score"]) ? round(($doc["score"] / $max_score) * 100, 2) : 0;
    	                
    	                if($ignore_doc_by_score && $doc["percentage"] < $this->percentage):
                            continue;
    	                endif;	                
	                endif;
	                
	                $final_docs[] = $doc; //array_map("trim", $doc);
	            endforeach;
	        endif;
	    endif;
	    
	    $response = [
	        "contacts"     => $final_docs,
	        "totalRecords" => $total_records,
	        "cursor_mark"  => $cursor_mark
	    ];	    
	    return $response;
	}
	
	private function query_in_column($key, $mode = 0, $payload = []) {	    
	    $feed_suffix = $txt = "";	  
	    if($mode == 0):	       
	       $txt = "_text_general";	
	    elseif($mode == 1):
	       $txt = "_ngram";
	    endif;
	    
	    $stem = $mode == 4 ? "" : "_stem";
	    $feed_suffix = $mode == 4 ? "" : "_stem";
	    if(isset($payload["build_mode"]) && $payload["build_mode"] == 2):	    
            $feed_suffix = $mode == 4 ? "" : "_exact";
        endif;
        
        $title_columns = $mode == 4 ? ["feed_name", "feed_name_apos"] : ["feed_name_stem", "feed_name_apos"]; //feed_name_prefix
        
	    $search_in = [
	        "title"            => $title_columns,
	        "twitter_location" => ["twitter_location$txt"],
	        "location"         => ["country$txt", "region$txt", "city$txt"],
	        "desc"             => ["feed_desc$stem"],
	        "folder_name"      => ["folder_names"],
	        "host"             => ["designation_name"],
	        "producer"         => ["designation_name"],
	        "package"          => ["package_names_text"],
	        "suggest"          => ["suggest$txt"],
	        "feed_name"        => ["feed_name"],
	        "language"         => ["language"], //lang_detected
	        "category"         => ["category$txt"],
	        "has_youtube"      => ["youtube_url"],
	        "has_email"        => ["email_count"],
	        "has_video_podcast"=> ["video_podcast_url"],
	        "has_guest"        => ["has_guests"],
	        "has_sponsor"      => ["has_sponsor"],
	        "email_json_txt"   => ["email_json_txt"]
	    ];
	    $column_name = isset($search_in[$key]) ? $search_in[$key] : [];
	    return $column_name;
	}
	
	//language filter c1
	public function language($payload) {
	    //$this->validateInput(["facet_field" => "required"]);
	    
	    $this->init_apple_core();
	    $facet_field = $payload["facet_field"];
	    $facets = [];
	    
	    $query = $this->solr_client->createSelect();
	    
	    $facetSet = $query->getFacetSet();
	    if(!empty($facet_field)):
	        $facet = $facetSet->createFacetField("ranges");
	        $facet->setField($facet_field);
    	    $facet->setSort("count");
    	    $facet->setMincount(10);
    	    $facet->setMethod("fc");
    	    $facet->setLimit(100000);	    
	    endif;
	    
	    $results = $this->solr_client->select($query);
	    if(!empty($facet_field)):
    	    $facet = $results->getFacetSet()->getFacet("ranges");
    	    foreach ($facet as $value => $count):
    	        $facets[$value] = ["field" => $value, "count" => $count];
    	    endforeach;	 
	    endif;
	    
	    if(isset($payload["debug"]) && $payload["debug"]):
	       echo '<pre>';print_r($facets); echo '<hr>';
	       $request = $this->solr_client->createRequest($query); echo $request->getUri(); die;
	    endif;
	    
	    return ["data" => $facets];	    
	}
	
    private function build_query_new($input) {
        $column_field_and = $column_field_or = $column_field = $or_parts = $arr = $ad_query = $query = [];    
        
        /*if(!empty($input["OR"])):
            foreach ($input["OR"] as $field => $values):
                $or_parts[] = "$field:(".implode(" ", $values).")";
            endforeach;
        endif;        
        if(!empty($or_parts)):
            $query[] = implode(" OR ", $or_parts);
        endif;*/
        
        if(!empty($input["OR"])):
            foreach ($input["OR"] as $field => $values):
            $column_name = str_replace(["_text_general", "_stem", "_ngram", "_exact"], "", $field);
            //$column_field[$column_name][] = "$field:(" . implode(" AND ", $values) . ")";
            
            foreach($values as $v) {
                $terms = array_map('trim', explode('OR', $v));
                $field_parts = [];
                foreach ($terms as $term) {
                    $term = trim($term, '" ');
                    $field_parts[] = $field . ':"' . $term . '"';
                }
                $column_field_or[$column_name][] = '(' . implode(' OR ', $field_parts) . ')';
            }
            endforeach;
        endif;
        
        if(!empty($input["AND"])):
            foreach ($input["AND"] as $field => $values):
                $column_name = str_replace(["_text_general", "_stem", "_ngram", "_exact"], "", $field);
                //$column_field[$column_name][] = "$field:(" . implode(" AND ", $values) . ")"; 
                
                foreach ($values as $v) {
                    $terms = array_map('trim', explode('AND', $v));
                    $field_parts = [];                    
                    foreach ($terms as $term) {
                        $term = trim($term, '" ');
                        $field_parts[] = $field . ':"' . $term . '"';
                    }                    
                    $column_field_and[$column_name][] = '(' . implode(' AND ', $field_parts) . ')';
                }                
            endforeach;
        endif;
        
        /*foreach($column_field as $fields):
            //$ad_query[] = "(".implode(" AND ", $fields).")";
            $ad_query[] = implode(" AND ", $fields);
        endforeach;*/
        
        foreach($column_field_and as $fields):
            $ad_query[] = "(".implode(" AND ", $fields).")";
            //$ad_query[] = implode(" AND ", $fields);
        endforeach;
        
        foreach($column_field_or as $fields):
        $query[] = "(".implode(" OR ", $fields).")";
            //$query[] = implode(" OR ", $fields);
        endforeach;
        
        //echo "<pre>"; print_r($ad_query); print_r($query); die; 
        
        
        if(!empty($query)):
            $arr[] = "(".implode(" OR ", $query).")";
            //$arr[] = implode(" OR ", $query);
        endif;
        
        if(!empty($ad_query)):
            $arr[] = "(".implode(" OR ", $ad_query).")";
            //$arr[] = implode(" OR ", $ad_query);
        endif;
        
        //echo "<pre>"; print_r($ad_query); print_r($arr); die;        
        
        $select = auto_implode($arr);  
        
        //echo $select; die;
        
        return $select;      
	}
	
	private function build_keyword_filter(array $include_keywords, array $exclude_keywords, string $include_type, array $search_in_keys): array {
        $filter_query     = [];
        $kw_operator      = strtolower(trim($include_type)) === "any" ? "OR" : "AND";
        $search_in_arr_kw = ["title", "desc", "host", "location", "email_json_txt", "folder_name"];
        
//         echo "-$include_type-<pre>";
//         print_r($include_keywords);
//         print_r($exclude_keywords);
//         die;
    
        $build_clause = function(string $phrase) use ($search_in_keys, $search_in_arr_kw): string {
            $phrase = trim($phrase);
            if (empty($phrase)) return "";
    
            $is_quoted = preg_match('/^"(.+)"$/', $phrase, $qm);
            $clean     = $this->solr_escape(trim($is_quoted ? $qm[1] : $phrase));
    
            //$search_in_keys = explode(",", $search_in);
            $col_clauses    = [];
    
            foreach ($search_in_keys as $si_key) {
                $si_key  = trim($si_key);
                if (!in_array($si_key, $search_in_arr_kw)) continue;
                $columns = $this->query_in_column($si_key, $is_quoted ? 4 : 0);
    
                foreach ($columns as $field) {
                    if ($is_quoted || strpos($clean, ' ') === false) {
                        $col_clauses[] = $field . ':"' . $clean . '"';
                    } else {
                        $terms       = array_filter(array_map('trim', explode(' ', $clean)));
                        $term_parts  = array_map(fn($t) => $field . ':"' . $t . '"', $terms);
                        $col_clauses[] = '(' . implode(' AND ', $term_parts) . ')';
                    }
                }
            }
    
            return empty($col_clauses) ? "" : '(' . implode(' OR ', $col_clauses) . ')';
        };
    
        if (!empty($include_keywords)) {
            $clauses = array_filter(array_map($build_clause, $include_keywords));
            if (!empty($clauses)) {
                $filter_query[] = '(' . implode(" $kw_operator ", $clauses) . ')';
            }
        }
    
        if (!empty($exclude_keywords)) {
            $clauses = array_filter(array_map($build_clause, $exclude_keywords));
            if (!empty($clauses)) {
                $filter_query[] = '-(' . implode(' OR ', $clauses) . ')';
            }
        }
    
        return $filter_query;
    }
	
	public function filter_mp_search($payload) {
	    $filter_query_str = $close = $open = "";
	    $select = "*:*";
	    
	    $negate_query = $location_qry = $query_loc_arr = $boost_query = $group = $search_filter = $query_arr = $filter_query = [];
	    $arr = $ue_query = $episode_query = [];
	    
        $search_query_arr = !empty($payload["query"]) ? $payload["query"] : [];
        $actual_query = !empty($payload["actual_query"]) ? $payload["actual_query"] : "";
        $negated = !empty($payload["negated"]) ? $payload["negated"] : [];
        $search_in = trim($payload["search_in"]);
        $or_feeds = isset($payload["or_feeds"]) ? array_filter(array_map("trim", (array)$payload["or_feeds"])) : [];
        $search_match = trim($payload["search_match"]);
        
        $search_in = explode(",", $search_in);
        $bracket = $payload["search_match"] == "any" ? false : true;
        $is_suggestion = isset($payload["suggestion"]) && $payload["suggestion"] ? true : false;
        $column_mode = $is_suggestion ? 1 : 0;
        $search_in_arr = ["title", "desc", "host", "location", "email_json_txt", "folder_name"];        
        
        if($payload["search_match"] == "exact"):
            $column_mode = 4;
        endif;
        
        $exact_query = !empty($payload["exact_query"]) ? $payload["exact_query"] : [];
        $parse_query = !empty($payload["parse_query"]) ? $payload["parse_query"] : [];
        $default = isset($parse_query["DEFAULT"]) ? $parse_query["DEFAULT"] : [];
        unset($parse_query["DEFAULT"]);
        
        $primary_query_set = null;
        if(isset($payload["primary_query"])) {
            $primary_query_set = array_map(fn($p) => trim(strtolower($p)), (array)$payload["primary_query"]);
        }
        $literal_query = isset($payload["literal_query"]) ? trim(strtolower($payload["literal_query"])) : null;
        
        if($payload["debug"] > 0):
            //echo "<pre>"; print_r($payload); die;
            //echo "<pre>"; print_r($default); die;
        endif;
        
        $should_apply = 0;
        if(should_apply_comma_logic($actual_query, $search_match) && !empty($exact_query)):
            $should_apply = 1;
        
//             foreach($exact_query as $equery):
//                 $parse_query["AND"][] = $equery["query"];
//             endforeach; 
//             $exact_query = [];
        endif;  
        
//         echo "<pre>";
//         print_r($parse_query);
//         die;
        
        if(!empty($default)):
            foreach($default as $query):
            
                $query = only_special_char($query);
                $query = trim($query);
                $query = preg_replace('/\s+/', ' ', $query);

                if(empty($query)):
                    continue;
                endif;
                
                if(preg_match("/[^\x00-\x7F]/", $query)):
                    $search_match = "exact";
                endif;
                
                if($search_match == "exact"):   
                    $word_arr = explode(" ", $query);
                    $word_count = $word_arr ? count($word_arr) : 0;
                    
                    if(false && !empty($negated) && $word_count > 1):
                        foreach($word_arr as $word):
                            $exact_query[] = ["query" => $word, "type" => "AND"];
                        endforeach;
                    else:                
                        $exact_query[] = ["query" => $query, "type" => "AND"];
                    endif;   
                    
                elseif($search_match == "any"):
                    $parse_query["OR"][] = $query;
                elseif($search_match == "all"):
                    $parse_query["AND"][] = $query;
                endif;
            endforeach;        
        endif;
        
        
        
        $groups = ["AND" => [], "OR" => []];        
        if(!empty($parse_query)) {
            $has_adv_query = has_advanced_query($actual_query);
            
            $ranked_search_in_arr = ["title", "desc", "host", "location", "folder_name"];
            $literal_field_map = ["email_json_txt" => "email_json_txt"];
            
            $ranked_columns = $literal_fields = [];
            if ($payload["build_mode"] == 1) {
                foreach ($search_in as $search_in_key) {
                    $search_in_key = trim($search_in_key);
                    if (in_array($search_in_key, $ranked_search_in_arr)) {
                        $ranked_columns = array_merge($ranked_columns, $this->query_in_column($search_in_key));
                    } elseif (isset($literal_field_map[$search_in_key])) {
                        $literal_fields[] = $literal_field_map[$search_in_key];
                    }
                }
                $ranked_columns = array_values(array_unique($ranked_columns));
                $literal_fields = array_values(array_unique($literal_fields));
                $qf = $this->build_qf_string($ranked_columns, $payload["weights"] ?? []);
            }
            
            foreach ($parse_query as $operator => $queries) {
                if (!empty($queries)) {
                    foreach ($queries as $query) {
                        
                        $query = only_special_char($query);
                        $query = trim($query);
                        $query = preg_replace('/\s+/', ' ', $query);
                        
                        if (empty($query)) {
                            continue;
                        }
                        
                        $boost_query[] = $query;
                        
                        if($payload["build_mode"] == 1) {
                            
                            /*if(!empty($qf)) {
                                $is_primary = $primary_query_set === null || in_array(trim(strtolower($query)), $primary_query_set);
                                $group_qf = $is_primary ? $qf : $this->scale_qf_weights($qf, 0.2);
                                $is_literal = $literal_query === null || trim(strtolower($query)) == $literal_query;
                                $groups[$operator][] = $this->build_group_clause($query, $group_qf, !$is_literal, $operator == "AND");
                            }*/
                            
                            if(!empty($qf)) {
                                $is_primary = $primary_query_set === null || in_array(trim(strtolower($query)), $primary_query_set);
                                $group_qf = $is_primary ? $qf : $this->scale_qf_weights($qf, 0.2);
                                $is_literal = $literal_query === null || trim(strtolower($query)) == $literal_query;
                                $word_count = count(array_filter(explode(" ", trim($query))));
                                
                                if($is_literal && $word_count > 1) {
                                    $groups[$operator][] = $this->build_group_clause($query, $group_qf, true, false);
                                    $token_qf = $this->scale_qf_weights($group_qf, 0.1);
                                    $groups[$operator][] = $this->build_group_clause($query, $token_qf, false, $operator == "AND");
                                } else {
                                    $groups[$operator][] = $this->build_group_clause($query, $group_qf, !$is_literal, $operator == "AND");
                                }
                            }
                            
                            $esc_query = escape_query($query);
                            foreach ($literal_fields as $field_name) {
                                $arr[$operator][$field_name][] = '"' . $esc_query . '"';
                            }
                        } elseif ($payload["build_mode"] == 2) {
                            foreach ($search_in as $search_in_key) {
                                if (!in_array($search_in_key, $search_in_arr)) {
                                    continue;
                                }
                                $columns = $this->query_in_column($search_in_key);
                                foreach ($columns as $field_name) {
                                    $word_count = count(explode(" ", $query));
                                    $up_query = $word_count > 1 ? implode(" $operator ", array_map(fn($w) => "\"$w\"", explode(" ", $query))) : "\"$query\"";
                                    $arr[$operator][$field_name][] = $up_query;
                                }
                            }
                        }                        
                    }
                }
            }
        }
        
        if (!empty($exact_query)) {
            foreach ($exact_query as $equery) {
                $query = $equery["query"];
                $type = $equery["type"]; // AND or OR
                
                $query = only_special_char($query);
                $query = trim($query);
                $query = preg_replace('/\s+/', ' ', $query);
                
                if (empty($query)) {
                    continue;
                }
                
                $boost_query[] = $query;
                
                if ($payload["build_mode"] == 1) {
                    $exact_ranked_columns = $exact_literal_fields = [];
                    foreach ($search_in as $search_in_key) {
                        $search_in_key = trim($search_in_key);
                        if (in_array($search_in_key, $ranked_search_in_arr)) {
                            $column_type = ($search_in_key == "location") ? 0 : ($should_apply ? 0 : 4);
                            $exact_ranked_columns = array_merge($exact_ranked_columns, $this->query_in_column($search_in_key, $column_type));
                            
                            //$column_type = $should_apply ? 0 : 4;
                            //$exact_ranked_columns = array_merge($exact_ranked_columns, $this->query_in_column($search_in_key, $column_type));
                        } elseif (isset($literal_field_map[$search_in_key])) {
                            $exact_literal_fields[] = $literal_field_map[$search_in_key];
                        }
                    }
                    $exact_ranked_columns = array_values(array_unique($exact_ranked_columns));
                    $exact_literal_fields = array_values(array_unique($exact_literal_fields));
                    $exact_qf = $this->build_qf_string($exact_ranked_columns, $payload["weights"] ?? []);
                    
                    
                    /*if(!empty($exact_qf)) {
                        $is_primary = $primary_query_set === null || in_array(trim(strtolower($query)), $primary_query_set);
                        $group_qf = $is_primary ? $exact_qf : $this->scale_qf_weights($exact_qf, 0.2);
                        $is_literal = $literal_query === null || trim(strtolower($query)) == $literal_query;
                        $groups[$type][] = $this->build_group_clause($query, $group_qf, $is_literal ? !$should_apply : true, (bool)$should_apply);
                    }*/
                    
                    if(!empty($exact_qf)) {
                        $is_primary = $primary_query_set === null || in_array(trim(strtolower($query)), $primary_query_set);
                        $group_qf = $is_primary ? $exact_qf : $this->scale_qf_weights($exact_qf, 0.2);
                        $is_literal = $literal_query === null || trim(strtolower($query)) == $literal_query;
                        $word_count = count(array_filter(explode(" ", trim($query))));
                        
                        if($is_literal && $word_count > 1) {
                            $groups[$type][] = $this->build_group_clause($query, $group_qf, true, false);
                            $token_qf = $this->scale_qf_weights($group_qf, 0.1);
                            $groups[$type][] = $this->build_group_clause($query, $token_qf, false, (bool)$should_apply);
                        } else {
                            $groups[$type][] = $this->build_group_clause($query, $group_qf, $is_literal ? !$should_apply : true, (bool)$should_apply);
                        }
                    }
                    
                    
                    $esc_query = escape_query($query);
                    foreach ($exact_literal_fields as $field_name) {
                        $arr[$type][$field_name][] = '"' . $esc_query . '"';
                    }
                } elseif ($payload["build_mode"] == 2) {
                    // unchanged, deferred same as parse_query build_mode 2
                    foreach ($search_in as $search_in_key) {
                        $column_type = $should_apply ? 0 : 4;
                        $columns = $this->query_in_column($search_in_key, $column_type);
                        foreach ($columns as $field_name) {
                            if ($field_name == "folder_names") {
                                $arr[$type][$field_name][] = '"' . $query . '"';
                            } elseif ($should_apply) {
                                $arr[$type][$field_name][] = "(" . implode(" AND ", explode(" ", $query)) . ")";
                            } else {
                                $arr[$type][$field_name][] = '"' . $query . '"';
                            }
                        }
                    }
                }
            }
        }
        
        if($payload["debug"] > 0):
            //echo "<pre>"; print_r($arr);die;
        endif;        
        
        
        if($payload["build_mode"] == 1 && !empty($payload["boost_terms"])) {
            $boost_ranked_search_in_arr = ["title", "desc", "host", "location", "folder_name"];
            $boost_ranked_columns = [];
            foreach ($search_in as $search_in_key) {
                $search_in_key = trim($search_in_key);
                if (in_array($search_in_key, $boost_ranked_search_in_arr)) {
                    $boost_ranked_columns = array_merge($boost_ranked_columns, $this->query_in_column($search_in_key));
                }
            }
            $boost_ranked_columns = array_values(array_unique($boost_ranked_columns));
            $boost_base_qf = $this->build_qf_string($boost_ranked_columns, $payload["weights"] ?? []);
            
            if (!empty($boost_base_qf)) {
                $boost_qf = $this->scale_qf_weights($boost_base_qf, 0.05);
                foreach ($payload["boost_terms"] as $bterm) {
                    $bterm = trim($bterm);
                    if (empty($bterm)) {
                        continue;
                    }
                    $groups["OR"][] = $this->build_group_clause($bterm, $boost_qf, true, false);
                }
            }
        }
        
        $main_query = "";
        $group_params = [];        
        if($payload["build_mode"] == 1 && (!empty($groups["AND"]) || !empty($groups["OR"]))) {
            $gi = 0;
            $and_parts = $or_parts_bool = [];
            
            foreach ($groups["AND"] as $clause) {
                $gname = "g" . $gi;
                $group_params[$gname] = $clause;
                $and_parts[] = "must=\$$gname";
                $gi++;
            }
            
            foreach ($groups["OR"] as $clause) {
                $gname = "g" . $gi;
                $group_params[$gname] = $clause;
                $or_parts_bool[] = "should=\$$gname";
                $gi++;
            }
            
            $and_block = !empty($and_parts) ? "{!bool " . implode(" ", $and_parts) . "}" : "";
            $or_block = !empty($or_parts_bool) ? "{!bool " . implode(" ", $or_parts_bool) . "}" : "";
            
            if (!empty($and_block) && !empty($or_block)) {
                $group_params["andblk"] = $and_block;
                $group_params["orblk"] = $or_block;
                $main_query = "{!bool should=\$andblk should=\$orblk}";
            } elseif (!empty($and_block)) {
                $main_query = $and_block;
            } elseif (!empty($or_block)) {
                $main_query = $or_block;
            }
        }
        
        $bright_data = isset($payload["bright_data"]) ? $payload["bright_data"] : 0;
        if(!empty($arr) && !$bright_data):
            if($payload["build_mode"] == 1):        
                $select = build_query($arr, $payload); 
            elseif($payload["build_mode"] == 2):
                $select = $this->build_query_new($arr); 
            endif;
        endif;
        
        if($payload["debug"] > 0):
            //echo $select; die;
        endif;       
        
        if(isset($payload["ignore_query"]) && $payload["ignore_query"] == 1):
            $select = "*:*";
        endif;
        
        //location filter 
        $locations = isset($payload["locations"]) ? $payload["locations"] : [];
        $us_region = isset($payload["us_region"]) ? $payload["us_region"] : [];
        
        //exclude filter
        $exclude_location = $payload["exclude_location"] ? "-" : "";
        $exclude_language = $payload["exclude_language"] ? "-" : "";
        $exclude_podcast_network = $payload["exclude_podcast_network"] ? "-" : "";
        $exclude_beats = $payload["exclude_beats"] ? "-" : "";
        $exclude_audience = $payload["exclude_audience_type"] ? "-" : "";
        $exclude_us_region = $payload["exclude_us_region"] ? "-" : "";
        $exclude_episode_length = $payload["exclude_episode_length"] ? "-" : "";
        //$exclude_review_range = $payload["exclude_apple_review"] ? "-" : "";        
        $exclude_user_engagement = $payload["exclude_user_engagement"] ? "-" : "";
        
        $exclude_listener_age = $payload["exclude_listener_age"] ? "-" : "";
        $exclude_listener_income = $payload["exclude_listener_income"] ? "-" : "";
        $exclude_listener_gender = $payload["exclude_listener_gender"] ? "-" : "";
        $exclude_apple_review = $payload["exclude_apple_review"] ? "-" : "";
        $exclude_apple_rating = $payload["exclude_apple_rating"] ? "-" : "";
        $exclude_community = $payload["exclude_community"] ? "-" : "";
        
        $combined_loc_arr = $au_loc = $author_location = [];  
        if(!empty($locations)):
            foreach($locations as $location):
                $au_loc_arr = $loc_arr = [];
                foreach($location as $key => $value): 
                    if(empty($value)):continue;endif;
                    $loc_arr[] = "$key:$value";
                    $au_loc_arr[] = 'email_json_txt:"' . $key . '\":\"' . $value . '"';
                endforeach;
                $query_loc_arr[] = $loc_arr;
                $author_location[] = $au_loc_arr;
            endforeach; 
            
            if(!empty($query_loc_arr)):
                foreach($query_loc_arr as $qrr):
                    $location_qry[] = "(".implode(" AND ", $qrr).")";
                endforeach;                
            endif;  
            
            if(!empty($author_location)):
                foreach($author_location as $aloc):
                    $au_loc[] = "(".implode(" AND ", $aloc).")";
                endforeach;
            endif;  
        endif;
        
        if(!empty($au_loc)):
            $combined_loc_arr[] = $au_loc;
        endif;
        
        if(!empty($location_qry)):
            $combined_loc_arr[] = $location_qry;
        endif;
        
        if(!empty($combined_loc_arr)):
            $cloc_qry = [];
            foreach($combined_loc_arr as $carr):
                foreach($carr as $citem):            
                    $cloc_qry[] = $citem;
                endforeach;                
            endforeach;
            
            if(!empty($cloc_qry)):
                $filter_query[] = "$exclude_location(".implode(' OR ', $cloc_qry).")";
            endif;
        endif; 
        
        //us region same as location
        $location_qry = $query_loc_arr = $combined_loc_arr = $au_loc = $author_location = [];
        if(!empty($us_region)):
            foreach($us_region as $location):
                $au_loc_arr = $loc_arr = [];
                foreach($location as $key => $value):
                    if(empty($value)):continue;endif;
                    $loc_arr[] = "$key:$value";
                    $au_loc_arr[] = 'email_json_txt:"' . $key . '\":\"' . $value . '"';
                endforeach;
                $query_loc_arr[] = $loc_arr;
                $author_location[] = $au_loc_arr;
            endforeach;
        
            if(!empty($query_loc_arr)):
                foreach($query_loc_arr as $qrr):
                    $location_qry[] = "(".implode(" AND ", $qrr).")";
                endforeach;
            endif;
        
            if(!empty($author_location)):
                foreach($author_location as $aloc):
                    $au_loc[] = "(".implode(" AND ", $aloc).")";
                endforeach;
            endif;
        endif;
        
        if(!empty($au_loc)):
            $combined_loc_arr[] = $au_loc;
        endif;
        
        if(!empty($location_qry)):
            $combined_loc_arr[] = $location_qry;
        endif;
        
        if(!empty($combined_loc_arr)):
            $cloc_qry = [];
            foreach($combined_loc_arr as $carr):
                foreach($carr as $citem):
                    $cloc_qry[] = $citem;
                endforeach;
            endforeach;
        
            if(!empty($cloc_qry)):
                $filter_query[] = "$exclude_us_region(".implode(' OR ', $cloc_qry).")";
            endif;
        endif;         
        
        //negate query
        if(!empty($negated)):
            foreach($search_in as $search_column):
            
                if(!in_array($search_column, $search_in_arr)):
                    continue;
                endif;
            
                $columns = $this->query_in_column($search_column, 4);
                foreach($columns as $column):                    
                    $str_ng = '"'.implode('" "', $negated).'"';
                    $negate_query[] = "$column:($str_ng)";                    
                endforeach;            
            endforeach;
            
            if(!empty($negate_query)):
                $filter_query[] = "-(".implode(" OR ", $negate_query).")";
            endif;            
        endif;
        
        $exclude_feeds = isset($payload["exclude_feeds"]) ? $payload["exclude_feeds"] : [];
        if(!empty($exclude_feeds)):
            $exclude_feeds = array_filter(array_map("trim", $exclude_feeds));
            if(!empty($exclude_feeds)):
                $filter_query[] = '-(feed_id:('.implode(' ', $exclude_feeds).'))';  
            endif;                 
        endif;
        
        $include_feeds = isset($payload["include_feeds"]) ? $payload["include_feeds"] : [];
        if(!empty($include_feeds)):
            $include_feeds = array_filter(array_map("trim", $include_feeds));
            if(!empty($include_feeds)):
                $filter_query[] = 'feed_id:('.implode(' ', $include_feeds).')';
            endif;            
        endif;
        
        if(!empty($payload["start_date"]) && !empty($payload["end_date"])):
            $start = date("Y-m-d 00:00:00", strtotime($payload["start_date"]));
            $start = str_replace(" ", "T", $start).'Z';
            
            $end = date("Y-m-d H:i:s", strtotime($payload["end_date"]));
            $end = str_replace(" ","T",$end).'Z';
            $filter_query[] = 'last_original_article_creation_date:['.$start.' TO '.$end.']';
        endif;  
        
        $type = isset($payload["social_type"]) ? $payload["social_type"] : "";
        $social_query = isset($payload["social_query"]) ? $payload["social_query"] : "";
        $social_query = $social_handle = isset($payload["social_handle"]) ? strtolower($payload["social_handle"]) : "";
        $auth_social_column = "";
        
        if(!empty($type) && !empty($social_query)):
            $name = "{$type}_url_text";        
            $column_arr = ["facebook", "twitter", "instagram", "linkedin", "youtube", "tiktok"];
            if(in_array($type, $column_arr)):
                $select = $name.':"'.$social_query.'"';
            
                if($type == "twitter"):                    
                    $auth_social_column = "t:";
                elseif($type == "linkedin"):
                    $auth_social_column = "l:";
                elseif($type == "facebook" || $type == "instagram"):
                    $auth_social_column = "$type:";
                endif;
                
                if(!empty($auth_social_column)):
                    $select .= ' OR email_json_txt:("'.$auth_social_column.'" AND "'.$social_query.'")';
                    $select = "($select)"; 
                endif;
                
            elseif($type == "spotify"):
                $select = 'social_handles:"'.$social_query.'"'; 
            elseif($type == "all"):            
                $select_arr = [];
                $social_query = str_replace("@", "", $social_query);
                foreach($column_arr as $column):
                    $name = "{$column}_url_text";  
                    $select_arr[] = $name.':"'.$social_query.'"';                
                endforeach;                
                
                $select_arr[] = 'social_handles:"'.$social_query.'"';
                $select_arr[] = 'email_json_txt:("t:" AND "'.$social_query.'")';
                $select_arr[] = 'email_json_txt:("l:" AND "'.$social_query.'")';                
                $select_arr[] = 'email_json_txt:("facebook:" AND "'.$social_query.'")';
                $select_arr[] = 'email_json_txt:("instagram:" AND "'.$social_query.'")';
                
                $select = "(".implode(" OR ", $select_arr).")";                
            endif;            
        endif;
        
        if(isset($payload["beats"]) && !empty($payload["beats"])):
            $arr = explode(",", $payload["beats"]);
            $filter_query[] = $exclude_beats.'(beats_id:("'.implode('" OR "', $arr).'"))';              
        endif;
        
        if(isset($payload["apple_id"]) && !empty($payload["apple_id"])):
            $filter_query[] = 'apple_id:('.implode(" ", $payload["apple_id"]).")";
        endif;
        
        if(isset($payload["site_url"]) && !empty($payload["site_url"])):
            $filter_query[] = '(site_url:'.$payload["site_url"].' OR feed_url_text:"'.$payload["site_url"].'")';
        endif;
        
        if(isset($payload["feed_domain"]) && !empty($payload["feed_domain"])):
            $filter_query[] = 'feed_domain:'.$payload["feed_domain"];
        endif;
        
        /*$min_rating = !empty($payload["min_rating"]) ? $payload["min_rating"] : 0;
        $max_rating = !empty($payload["max_rating"]) ? $payload["max_rating"] : 0;
        if(!empty($min_rating) || !empty($max_rating)):
            $filter_query[] = "rating_value:[$min_rating TO $max_rating]";
        endif;*/
        
        if(isset($payload["podcast_network"]) && !empty($payload["podcast_network"])):
            $arr = explode(",", $payload["podcast_network"]);
            $filter_query[] = $exclude_podcast_network.'(network_id:("'.implode('" OR "', $arr).'"))';
        endif;
        
        if(isset($payload["audience_type"]) && !empty($payload["audience_type"])):
            $arr = explode(",", $payload["audience_type"]);
            $filter_query[] = $exclude_audience.'(audience_type_id_array:("'.implode('" OR "', $arr).'"))';
        endif;
        
        if(isset($payload["listener_age"]) && !empty($payload["listener_age"])):
            $arr = explode(",", $payload["listener_age"]);
            $filter_query[] = $exclude_listener_age.'(listener_generation:("'.implode('" OR "', $arr).'"))';
        endif;
        
        if(isset($payload["listener_income"]) && !empty($payload["listener_income"])):
            $arr = explode(",", $payload["listener_income"]);
            $filter_query[] = $exclude_listener_income.'(listener_income:("'.implode('" OR "', $arr).'"))';
        endif;
        
        if(isset($payload["listener_gender"]) && !empty($payload["listener_gender"])):
            $arr = explode(",", $payload["listener_gender"]);
            $filter_query[] = $exclude_listener_gender.'(listener_gender:("'.implode('" OR "', $arr).'"))';
        endif;
        
        if(isset($payload["episode_language"]) && !empty($payload["episode_language"])):
            $arr = explode(",", $payload["episode_language"]);
            $filter_query[] = $exclude_language.'(language:("'.implode('" OR "', $arr).'"))';
        endif;
        
        $episode_length = !empty($payload["episode_length"]) ? $payload["episode_length"] : [];
        if(!empty($episode_length)):
            foreach($episode_length as $row):
                $start = $row["start"];
                $end = $row["end"];
                $episode_query[] = "duration_seconds:[$start TO $end]";
            endforeach;
        endif;
        
        if(!empty($episode_query)):
            $filter_query[] = "$exclude_episode_length(".implode(" OR ", $episode_query).")";
        endif;
        
        /*$apple_review_range = !empty($payload["apple_review_range"]) ? $payload["apple_review_range"] : [];
        if(!empty($apple_review_range)):
            foreach($apple_review_range as $row):
                $review_range_query[] = "max_review_count:[".$row["start"]." TO ".$row["end"]."]";
            endforeach;
            if(!empty($review_range_query)):
                $filter_query[] = "$exclude_review_range(".implode(" OR ", $review_range_query).")";
            endif;
        endif;*/
        
        $other_attributes = !empty($payload["other_attributes"]) ? $payload["other_attributes"] : [];
        $other_fq = $other_filter = [];
        if(!empty($other_attributes)):
            foreach($other_attributes as $attr):
                $columns = $this->query_in_column($attr, 5);
                $st_value = $attr == "has_email" ? 1 : "*";
                if(!empty($columns)):
                    foreach($columns as $column):
                        if($attr == "has_guest" || $attr == "has_sponsor"):
                            $other_filter[$attr][] = "$column:1";
                        else:                        
                            $other_filter[$attr][] = "$column:[$st_value TO *]";
                        endif;
                    endforeach;                    
                endif;                  
            endforeach;
            
            if(!empty($other_filter)):
                foreach($other_filter as $of):
                    $other_fq[] = implode(" OR ", $of);
                endforeach;
            endif;
            
            if(!empty($other_fq)):
                $filter_query[] = "(".implode(" AND ", $other_fq).")";
            endif;            
        endif;
        
        $gender = !empty($payload["gender"]) ? $payload["gender"] : [];
        if(!empty($gender)):
            $gender_column = "";
            if($gender == "male"):
                $gender_column = "has_male";
            elseif($gender == "female"):
                $gender_column = "has_female";
            endif;
            
            if(!empty($gender_column)):
                $filter_query[] = "$gender_column:1";
            endif;            
        endif;
        
        $user_engagement = !empty($payload["user_engagement"]) ? $payload["user_engagement"] : [];
        if(!empty($user_engagement)):
            foreach($user_engagement as $row):
                $start = $row["start"];
                $end = $row["end"];
                $ue_query[] = "estimated_listeners:[$start TO $end]";
            endforeach;
        endif;
        
        if(!empty($ue_query)):
            $filter_query[] = "$exclude_user_engagement(".implode(" OR ", $ue_query).")";
        endif;
        
        $ra_query = $ar_query = [];
        $apple_review = !empty($payload["apple_review"]) ? $payload["apple_review"] : [];
        $apple_rating = !empty($payload["apple_rating"]) ? $payload["apple_rating"] : []; 
        
        if(!empty($apple_review)):
            foreach($apple_review as $row):
                $start = $row["start"];
                $end = $row["end"];
                $ar_query[] = "max_review_count:[$start TO $end]";
            endforeach;
            if(!empty($ar_query)):
                $filter_query[] = "$exclude_apple_review(".implode(" OR ", $ar_query).")";
            endif;
        endif;
        
        if(!empty($apple_rating)):
            foreach($apple_rating as $row):
                $start = $row["start"];
                $end = $row["end"];
                $ra_query[] = "rating_value:[$start TO $end]";
            endforeach;
            
            if(!empty($ra_query)):
                $filter_query[] = "$exclude_apple_rating(".implode(" OR ", $ra_query).")";
            endif;
        endif;
        
        if(!empty($payload["community"])):
            $arr = explode(",", $payload["community"]);
            $filter_query[] = "$exclude_community(community_id_array:(".implode(" OR ", $arr)."))";
        endif;
        
        if(!empty($payload["include_keyword"]) || !empty($payload["exlude_keyword"])):
            $kw_filters = $this->build_keyword_filter($payload["include_keyword"], $payload["exlude_keyword"], $payload["include_type"], $search_in);
            foreach ($kw_filters as $kw_fq):
                $filter_query[] = $kw_fq;
            endforeach;
        endif;
        
        $loc_type = isset($payload["location"]["type"]) ? $payload["location"]["type"] : "";
        $location_query = isset($payload["location"]["query"]) ? $payload["location"]["query"] : "";
        $group_field = isset($payload["group_field"]) ? $payload["group_field"] : [];
        
        //suggestions
        if(!empty($location_query) && !empty($loc_type)):
            $column = $this->query_in_column($loc_type, 1)[0] ?? ""; 
            if(!empty($column)):
                $select = $column.":".$location_query;
            endif; 
        endif;
        
        //group
        if(!empty($group_field)):  
            $columns = [];            
            foreach($group_field as $col):
                $columns[] = $this->query_in_column($col, 2)[0] ?? "";	
            endforeach;            
            $columns = array_values(array_unique(array_filter($columns)));            
            if(!empty($columns)):
                $group = [
                    "group"        => true,
                    "group.main"   => true,
                    "group.field"  => $columns
                ]; 	 
            endif;	
        endif;
        
        if($filter_query && count($filter_query) > 0):
	        $filter_query_str = implode(" AND ", $filter_query); 
        endif;
        
        if(!empty($or_feeds) && empty($filter_query_str)):
            $or_feed_clause = "feed_id:(".implode(" ", $or_feeds).")";
            $select = $select !== "*:*" ? "($select) OR $or_feed_clause" : $or_feed_clause;
        endif;
        
        /*$result = [
            "boost_query"   => $boost_query ? array_values(array_unique(array_filter($boost_query))) : [],
            "select"        => ["query" => $select],
            "fq"            => $filter_query_str,
            "group"         => $group,
        ];*/
        
        $result = [
            "boost_query"   => $boost_query ? array_values(array_unique(array_filter($boost_query))) : [],
            "select"        => ["query" => $select],
            "fq"            => $filter_query_str,
            "group"         => $group,
            "main_query"    => $main_query,
            "group_params"  => $group_params,
        ];
        
        if($payload["debug"] && ($payload["debug"] == 2 || $payload["debug"] == 3)):  
            echo '<pre>';
            if($payload["debug"] == 3):
                print_r($payload);
            endif;            
            print_r($result);die;
        endif;
        
        return $result;
	}
	
	public function set_fields() {
	    $arr = ["feed_id", "feed_name", "feed_desc", "feed_image_url", "feed_url", "created", "feed_domain", "site_url"];
	    $arr[] = "facebook_url";
	    $arr[] = "twitter_url";
	    $arr[] = "instagram_url";
	    $arr[] = "linkedin_url";
	    $arr[] = "youtube_url";
	    $arr[] = "followers";
	    $arr[] = "da";
	    $arr[] = "twitter_location";
	    $arr[] = "facebook_followers";
	    $arr[] = "twitter_followers";
	    $arr[] = "instagram_followers";
	    $arr[] = "youtube_view_count";
	    $arr[] = "youtube_follower_count";
	    $arr[] = "youtube_video_count";
	    $arr[] = "country";
	    $arr[] = "region";
	    $arr[] = "city";
	    $arr[] = "language";
	    $arr[] = "article_freq";
	    $arr[] = "email_json";
	    $arr[] = "notes";
	    $arr[] = "phone";
	    $arr[] = "mastodon_url";
	    $arr[] = "social_handles";
	    $arr[] = "video_podcast_url";
	    $arr[] = "rating_value";
	    $arr[] = "tiktok_url";
	    $arr[] = "categories";
	    $arr[] = "email_count";
	    $arr[] = "entry_count";
	    $arr[] = "latest_entry_timestamp";
	    $arr[] = "last_original_article_creation_date";
	    $arr[] = "designation_name";
	    $arr[] = "designation";
	    $arr[] = "rss_site_url";
	    $arr[] = "itune_contact_json";
	    $arr[] = "lang_detected";
	    
	    return $arr;
	}
	
	private function format_data($rows) {
	    $data = [];	    
	    foreach($rows as $document):
	        $row = [];
	        foreach ($document as $field => $value):
	        
	            if(in_array($field, SANITIZE_FIELDS)):
                    $value = sanitize_solr_text($value);
	            endif;
	            
	            if(in_array($field, HANDLE_SPECIAL_CHAR_FIELDS)):
                    $value = handleSpecialChar($value);
	            endif;
	            
	            if(in_array($field, DATE_FIELDS)):
                    $value = solr_timestamp_to_rssdate($value);
	            endif;	   
	            
	            if(in_array($field, UNESCAPE_FIELDS)):
                    $value = un_escape_solr_special_chars($value);
	            endif;
	            
	            $row[$field] = $value;	            
	        endforeach;
	        $data[] = $row;
	    endforeach;	 
	    
	    return $data;
	}	
}
