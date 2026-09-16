<?
namespace App\Controllers;

use App\Controllers\SolrPodcastService;
use App\Controllers\Episodes;
use App\Controllers\BrightData;
use App\Controllers\Log;
use App\Controllers\EpisodeLength;
use App\Controllers\UserEngagement;
use App\Controllers\Feedspot;
use App\Controllers\Beats;
use App\Controllers\Audience;
use App\Controllers\Claude;

//use App\Models\PredisModel;

use App\Enums\ResponseStatusEnum;
use App\Enums\LogEventEnum;
use App\Enums\RestrictTypeEnum;
use App\Enums\RateLimitEnum;
use App\Enums\PreferenceEnum;

class Search extends Controller {  
    
    protected $solr_ps;
    protected $log_ct;
    protected $el_ct;
    protected $ue_ct;
    protected $fs;
    protected $brightdata_ct;
    protected $audience_ct;
    protected $beats_ct;
    protected $episodes_ct;
    protected $claude;
    
    public function __construct($vars = []) {
        parent::__construct($vars);
        
        $middlewares = [
            $this->auth_user_key => ["except" => [], "class" => __CLASS__],
        ];
        $this->middleware($middlewares);    
        $this->info(); 
        
        $this->solr_ps = new SolrPodcastService();
        $this->log_ct = new Log();
        $this->el_ct = new EpisodeLength();
        $this->ue_ct = new UserEngagement();
        $this->fs = new Feedspot();
        $this->beats_ct = new Beats();
        $this->audience_ct = new Audience();
        $this->episodes_ct = new Episodes();
        $this->claude = new Claude();
    }    
    public function brightdata_ctr() {
        $this->brightdata_ct = new BrightData();
    }
    public function sort($sort = "") {
        $columns = [
            "recency"           => "last_original_article_creation_date",
            "no_of_episodes"    => "entry_count",
            "apple_rating"      => "rating_value",
            "apple_review"      => "max_review_count", //max_review_count //review_count
            "facebook_follower" => "facebook_followers",
            "instagram_follower"=> "instagram_followers",
            "twitter_follower"  => "twitter_followers",
            "youtube_follower"  => "youtube_follower_count",
            "relevancy"         => "relevancy",
            "feed_id"           => "feed_id",
        ];
        $columns = isset($columns[$sort]) ? $columns[$sort] : DEFAULT_SEARCH_SORT;        
        return $columns;
    }
    public function is_restricted_query($query) {
        $arr = ["podcast", "podcasts", "an", "and", "are", "as", "at", "be", "but", "by", "for", "if", "in", "into", "is", "it", "no", "not", "of", "on", "or", "such", "that", "the", "their", "then", "there", "these","they", "this", "to", "was", "will", "with", "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z", "0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "all", "bit", "can", "do", "dont", "en", "have", "http", "ly", "on", "so", "some", "we", "you", "your"];
        $query = trim(strtolower($query));
        
        if(in_array($query, $arr)):
            $this->sendJson(ResponseStatusEnum::INVALID_SEARCH_QUERY);
        endif;
    }
    
    public function manage_exact_match_feed($mode, $query, $feed_id = "") {
        //$predis = new PredisModel(); 
        $client = $this->predis->redis_client();
        
        $query = preg_replace('/\s+/', '', strtolower($query));
        $key = $this->predis->get_key("search_exact_feed");
        $key = $key.md5($query).$this->auto_id;
        
        if($mode == "get"):
            $obj = $client->get($key);
        elseif($mode == "set"):
            $obj = $client->set($key, $feed_id, "EX", RED_3_HOUR_CACHE_EXPIRE);
        endif;
        
        return $obj;
    }
    
    public function search($payload = []) {        
        $this->payload = !empty($payload) ? $payload : $this->payload;    
        
        if(!empty($this->payload["search_type"]) && $this->payload["search_type"] == "episode"):
            $this->episodes_ct->search(0);
        endif;
        
        if(strlen($this->payload["query"]) > MAX_QUERY_LENGTH):
            $this->payload["query"] = substr($this->payload["query"], 0, MAX_QUERY_LENGTH);
        endif;
        
        $response_id = isset($this->payload["response_id"]) ? $this->payload["response_id"] : 0;
        $user_query = $vector_query = $actual_query = $query = $this->payload["query"];
        $query = $actual_query = $vector_query = $user_query = str_replace(["’", "‘"], "'", $user_query);
        $listener_age = $listener_income = "";
        
        /*if(!empty($response_id)):
            $data = []; $total = 0;
            $res = $this->get_apple_id_from_brightdata($user_query, [], [], [], $response_id);            
            if(!empty($res)):
                $data = $res["data"];
                $total = $res["total"];
            endif; 
            
            if(isset($this->payload["count"]) && $this->payload["count"] == 1):
                return $total;
            endif;
            
            if($this->auto_id != MP_TOOLS_AUTO_ID):        
                $this->log_ct->add(["user_auto_id" => $this->auto_id, "event_type" => LogEventEnum::SEARCH, "data" => jsonEncode($this->payload)]);
            endif;
            
            $this->paginate($total, $data, 1);
        endif;*/
        
        timer_mark("search_init");
        $include_feeds = [];
        $locked_filters = $this->unset_filter_based_on_plan();
        
        if(!empty($this->payload["feed_ids"])):
            $orig_payload = $this->payload;
            $this->payload = [];             
            $this->payload["feed_ids"] = $orig_payload["feed_ids"];
            $this->payload["page"] = $orig_payload["page"] ?? 1;
            $this->payload["limit"] = $orig_payload["limit"];
            $this->payload["offset"] = $orig_payload["offset"] ?? 0;            
            $this->payload["type"] = $orig_payload["type"];
            $this->payload["mp_charts"] = $orig_payload["mp_charts"];
            $this->payload["fields"] = $orig_payload["fields"];        
            $this->payload["qa"] = $orig_payload["qa"] ?? 0;
            $include_feeds = array_filter(explode(",", preg_replace("/\s+/u", "", $this->payload["feed_ids"])));
        endif;
        
        $validate_request = 1;
        if(!empty($payload) && !empty($payload["user_auto_id"])):
            $this->auto_id = $payload["user_auto_id"];
            $validate_request = 0;
        endif;
        
        //$this->input_char_limit(["query" => $this->payload["query"]]);
        $this->rateLimitMe(RateLimitEnum::SEARCH);
        
        //single char, reserved char validation
        $this->is_restricted_query($query);
        
        $current_page = $this->payload["page"];
        $core = isset($this->payload["core"]) ? $this->payload["core"] : 0;
        //$is_first = isset($this->payload["first"]) ? $this->payload["first"] : 0;
        $is_count = isset($this->payload["count"]) && $this->payload["count"] == 1 ? 1 : 0;
        $from_list = isset($this->payload["from_list"]) ? $this->payload["from_list"] : 0;
        $retry_search = isset($this->payload["retry_search"]) ? $this->payload["retry_search"] : 0; 
        $community = $this->payload["community"] ?? "";
        
        //admin test
        $mp_feed = isset($this->payload["mp_feed"]) ? $this->payload["mp_feed"] : "";
        $mp_permalink = isset($this->payload["mp_permalink"]) ? $this->payload["mp_permalink"] : "";
        
        $reg_list_url = $pin_feed_id = 0;        
        /*if($is_first && !empty($user_query)):
            $pin_feed_id = isset($this->user_preferences[PreferenceEnum::REGISTERED_FEED_ID]) ? $this->user_preferences[PreferenceEnum::REGISTERED_FEED_ID]["pvalue"] : 0;
            $reg_list_url = isset($this->user_preferences[PreferenceEnum::LIST_URL]) ? $this->user_preferences[PreferenceEnum::LIST_URL]["pvalue"] : 0;
        endif;*/
        
        $mp_query = "";
        if(!empty($mp_feed) && !empty($mp_permalink)):
            $pin_feed_id = $mp_feed;
            $reg_list_url = $mp_url;
            
            $blog = $this->fs->get_permalink_feed($mp_permalink, 0, 1);
            $t_search_query = !empty($blog["search_query"]) ? $blog["search_query"] : "";
            $mp_query = $user_query = $vector_query = $actual_query = $query = $this->payload["query"] = $t_search_query;
        endif;
        
        if($locked_filters["LOCATION"]): $this->payload["location"] = ""; endif;
        if($locked_filters["MONTHLY_LISTENERS"]): $this->payload["user_engagement"] = ""; endif;
        if($locked_filters["LANGUAGE"]): $this->payload["language"] = "";endif;
        if($locked_filters["REVIEW"]): $this->payload["apple_review"] = ""; endif;
        if($locked_filters["RATING"]): $this->payload["apple_rating"] = ""; endif;
        if(!empty($locked_filters["SORT"])): $this->payload["sort_by"] = ""; endif;        
        if($locked_filters["LISTENER_GENDER"]): $this->payload["listener_gender"] = "";endif;
        if($locked_filters["LISTENER_INCOME"]): $this->payload["listener_income"] = "";endif;
        if($locked_filters["LISTENER_AGE"]): $this->payload["listener_age"] = "";endif;
        if($locked_filters["LISTENER_TYPE"]): $this->payload["audience_type"] = "";endif;
        
        $search_match = trim($this->payload["search_match"]);        
        $search_match = !$core && empty($search_match) ? DEFAULT_SEARCH_MATCH : $search_match;
        $sm_filter = empty($search_match) ? DEFAULT_SEARCH_MATCH : $search_match;        
        
        //no of searches
        if(!$is_count && $validate_request && $current_page == 1 && $this->auto_id != MP_TOOLS_AUTO_ID):
            $this->restrict($this->account_mdl, RestrictTypeEnum::SEARCH);
        endif;
        
        timer_mark("no of searches chk");
        $call_claude = 1;
        
        if(preg_match('/^feed_id:(.+)$/i', trim($query), $matches)):
            $feed_ids = preg_replace('/\s+/', '', $matches[1]);        
            $include_feeds = array_filter(explode(',', $feed_ids)); 
            $user_query = $vector_query = $actual_query = $query = "";            
        endif;
        
        if(!empty($query)):
            if(!is_url($query)):
                $query = str_replace(["-podcasts", "-podcast"], "", $query);
                $query = remove_btn_text_stop_word($query, $sm_filter);
                $query = preg_replace('/\s+/', " ", $query);
                
                /*if(!is_exact_match($sm_filter, $query)):
                    $lq = query_location($query);
                    if(!empty($lq["query"])):
                        $query = $lq["query"];                    
                    endif;
                    
                    $query = str_ireplace("best ", "", $query);
                    $query = remove_in_year($query);
                    $query = str_replace(" + ", ",", $query);
                    
                    if(!empty($lq["location"]) && empty($this->payload["location"])):
                        $new_larr = $lq["location"];                         
                        $this->payload["location"] = implode(",", $new_larr);
                        $this->payload["auto_location"] = 1;
                        $call_claude = 0;
                    endif;                      
                endif;*/  
                
                /*if(!is_exact_match($sm_filter, $query)) {
                    $topic_arr = get_static_data("topic_location");
                    if(!empty($topic_arr)):
                        $topic_arr = array_column($topic_arr, null, 0);
                        $lower_query = trim(strtolower($query));
                        if(isset($topic_arr[$lower_query])):
                            $query = !empty($topic_arr[$lower_query][1]) ? $topic_arr[$lower_query][1] : "";
                            $top_loc =  !empty($topic_arr[$lower_query][2]) ? $topic_arr[$lower_query][2] : "";
                            if(!empty($top_loc) && empty($this->payload["location"])):
                                $this->payload["location"] = $top_loc;
                                $this->payload["auto_location"] = 1;
                                $call_claude = 0;
                            endif;                    
                        endif;                    
                    endif; 
                }*/
                
            endif;
        endif;
        
        timer_mark("processing query");
        
        $location = !empty($this->payload["location"]) ? split_location($this->payload["location"], "", 1) : "";
        $us_region = !empty($this->payload["us_region"]) ? split_location($this->payload["us_region"], "", 1) : "";
        
        //$location = !empty($this->payload["location"]) ? location_package($this->payload["location"]) : [];
        //$us_region = !empty($this->payload["us_region"]) ? location_package($this->payload["us_region"]) : [];
        //echo "<pre>"; print_r($location); print_r($us_region); die;
        
        //no of search results based on plan        
        if($current_page > 1 && !$is_count && $validate_request):
            $this->is_search_results_allowed();  
        endif;
        
        timer_mark("search results chk");
        
        $apple_review = $apple_rating = $apple_id_arr = $qry_arr = $audience_type = $match_feed = $episode_length = $negated = $exclude_feeds = $other_attributes = $user_engagement = $episode_length = [];
        $social_handle = $social_type = $feed_domain = $site_url = "";
        $exact_query = $parse_query = [];
        $dec_limit = 0;
        
        $query = only_special_char($query);    
        
        $urlobj = is_apple_domain_site_url($query);
        if(!empty($urlobj)):
            if(!empty($urlobj["apple_id"])):
                $apple_id_arr[] = $urlobj["apple_id"];
            elseif(!empty($urlobj["site_url"])):
                $site_url = $urlobj["site_url"];
            elseif(!empty($urlobj["feed_domain"])):
                $feed_domain = $urlobj["feed_domain"];
            endif;   
            if(!empty($apple_id_arr) || !empty($site_url) || !empty($feed_domain)):
                $vector_query = $query = "";
            endif;            
        endif;
        
        $negate_rem = negative_words_text($query); 
        if(!empty($negate_rem["extracted"])):
            $negated = $negate_rem["extracted"];
            $query = $negate_rem["remaining"];
        else:
            $query_operator = detect_query_type($query);
        endif;      
                
        if(!empty($query_operator) && in_array("-", $query_operator)):
            $negated_words = get_negated_terms($query);
            if(!empty($negated_words["matched"])):
                $query = rtrim(str_replace($negated_words["matched"], "", $query));
                $negated = $negated_words["negated"];
            endif;
            $query_operator = detect_query_type($query);
        endif;
        
        $ignore_default_sort = isset($payload["bulk_list"]) && $payload["bulk_list"] ? 1 : 0;
        $claude_cache = 0;
        $claude_obj = [];
        $call_claude = $core ? 0 : $call_claude;
        $can_call_claude_api = 0;
                
        if(!empty($query)) {            
            if($call_claude && !is_exact_match($sm_filter, $query)) {
                timer_mark("before claude expand query");
                $can_call_claude_api = 1;
        
                $add_to_queue = (!$retry_search && (!empty($this->auto_id) || !empty($this->guest_uuid))) ? 1 : 0;
                $claude_obj = $this->claude->parse($query, $this->payload["count"], $add_to_queue);
                                
                if(!empty($claude_obj)) {
                    $claude_cache = 1; $queries_arr = [];
                    $act_query_type = $query_type = $claude_obj["query_type"] ?? "";            
                    $term_weights = $claude_obj["term_weights"] ?? [];
                    $pn_variants = $claude_obj["podcast_name_variants"] ?? [];
                    
                    $topic_key = trim(strtolower($query));                    
                    //$restatement_terms = get_restatement_terms($topic_key);
                    $restatement_overrides = [];
                    
                    
                    
                    $topic_term_types = get_topic_term_types($topic_key);
                    $alias_terms = $topic_term_types["alias"];
                    $restatement_terms = $topic_term_types["restatement"];
                    $broader_terms = $topic_term_types["broader"];
                    
                    /*echo "$query - $topic_key <pre>";
                    print_r($restatement_terms);die;*/
                    
                    $call_pm = 1;
                    if($query_type == "topic"):
                        $json = file_get_contents(DOCUMENT_ROOT."Static/queries.json");
                        $ignore_query_arr = !empty($json) ? json_decode($json, true) : []; 
                        if(in_array(strtolower($user_query), $ignore_query_arr)):
                            $call_pm = 0;
                        endif;
                    endif;
                    
                    if(!empty($pn_variants) && $call_pm) {                        
                        $pn_query_arr = [];
                        foreach($pn_variants as $pn_variant) {
                            $pn_variant = str_replace(["-podcasts", "-podcast"], "", $pn_variant);
                            $pn_variant = remove_btn_text_stop_word($pn_variant, $sm_filter);
                            $pn_variant = preg_replace('/\s+/', " ", $pn_variant);
                            $pn_query_arr[] = $pn_variant;
                        }
                        $pn_query = implode(",", $pn_query_arr);                            
                        if(preg_match('/^[^,]+(,[^,]+)*$/', $pn_query) && !is_exact_match($sm_filter, $pn_query)):
                            $pn_query = str_ireplace("top ", "", $pn_query);
                            $pn_query = manage_comma_query($pn_query, $sm_filter); 
                        endif;                            
                        $pn_parse_query = parse_query($pn_query);                            
                        $pn_parse_query = extract_exact_terms($pn_parse_query, $sm_filter);
                        
                        $pm_exact_query = [];
                        if(isset($pn_parse_query["EXACT"])):
                            $pm_exact_query = $pn_parse_query["EXACT"];
                            unset($pn_parse_query["EXACT"]);
                        endif; 
                        $query_type = "topic";
                    }
                    
                    /*if($query_type == "topic" || $query_type == "podcast_name"):
                        foreach($term_weights as $topic => $weight):
                            if($weight >= 0.9):
                                $queries_arr[str_replace("-", " ", $topic)] = $weight;
                            endif;
                        endforeach;
                    endif;*/
                    
                    /*if($query_type == "topic" || $query_type == "podcast_name"):
                        foreach($term_weights as $topic => $weight):
                            $norm_topic = str_replace("-", " ", $topic);
                            $is_restatement = isset($restatement_terms[trim(strtolower($norm_topic))]);
                            
                            if($weight >= 0.9 || $is_restatement):
                                $queries_arr[$norm_topic] = $is_restatement ? 1 : $weight;
                                if($is_restatement && $weight != 1):
                                    $restatement_overrides[] = [
                                        "query"             => $query,
                                        "restatement_query" => trim(strtolower($norm_topic)), //$restatement_terms[],
                                        "original_weight"   => $weight,
                                        "applied_weight"    => 1,
                                    ];
                                endif;                                
                            endif;
                        endforeach;
                    endif;*/
                    
                    $boost_terms = [];
                    
                    if($query_type == "topic" || $query_type == "podcast_name") {
                        foreach($term_weights as $topic => $weight) {
                            $norm_topic = str_replace("-", " ", $topic);
                            $lookup_key = trim(strtolower($norm_topic));
                            
                            if(isset($alias_terms[$lookup_key])) {
                                $queries_arr[$norm_topic] = 1;
                                if($weight != 1) {
                                    $restatement_overrides[] = [
                                        "query"             => $query,
                                        "restatement_query" => $lookup_key,
                                        "original_weight"   => $weight,
                                        "applied_weight"    => 1,
                                        "rule"              => "alias",
                                    ];
                                }
                            } elseif(isset($restatement_terms[$lookup_key])) {
                                $queries_arr[$norm_topic] = min($weight, 0.5);
                                if($weight >= 0.9) {
                                    $restatement_overrides[] = [
                                        "query"             => $query,
                                        "restatement_query" => $lookup_key,
                                        "original_weight"   => $weight,
                                        "applied_weight"    => 0.5,
                                        "rule"              => "restatement",
                                    ];
                                }
                            } elseif(isset($broader_terms[$lookup_key])) {
                                $boost_terms[] = $norm_topic;
                            }
                            // narrower / adjacent / not in table → excluded entirely
                        }
                    }
                    
                    if(!empty($queries_arr)):
                        $qname_arr = [];
                        $seen_signatures = [];
                        $primary_terms = [];
                        
                        $primary_term = preg_replace('/\s+/', ' ', trim($query));
                        if(!empty($primary_term)) {
                            $qname_arr[] = $primary_term;
                            $primary_terms[] = $primary_term;
                            $words = explode(" ", strtolower($primary_term));
                            sort($words);
                            $seen_signatures[] = implode(" ", $words);
                        }
                        
                        foreach($queries_arr as $qname => $q_weight):
                            $qname = str_replace(["-podcasts", "-podcast"], "", $qname);
                            $qname = remove_btn_text_stop_word($qname, $sm_filter);
                            $qname = str_replace("-", "", $qname);
                            $qname = preg_replace('/\s+/', " ", $qname);
                            
                            $words = explode(" ", strtolower(trim($qname)));
                            sort($words);
                            $signature = implode(" ", $words);
                            if (in_array($signature, $seen_signatures)) {
                                continue;
                            }
                            $seen_signatures[] = $signature;
                            
                            $qname_arr[] = $qname;
                            if($q_weight > 0.9):
                                $primary_terms[] = $qname;
                            endif;
                        endforeach;
                        $actual_query = $query = implode(",", $qname_arr);
                        //$vars["primary_query"] = $primary_term;
                    endif;
                    
                                      
                }
            }            
            timer_mark("after claude expand query");
            
            if(preg_match('/^[^,]+(,[^,]+)*$/', $query) && !is_exact_match($sm_filter, $query)):
                $query = str_ireplace("top ", "", $query);
                $query = manage_comma_query($query, $sm_filter); 
            endif;
            
            $parse_query = parse_query($query); 
            $parse_query = extract_exact_terms($parse_query, $sm_filter);
            
            $exact_query = [];
            if(isset($parse_query["EXACT"])):
                $exact_query = $parse_query["EXACT"];
                unset($parse_query["EXACT"]);
            endif;             
        }  
        
        $social = extract_social_info($user_query);        
        if(!empty($social)):
            $social_type = $social["type"];
            $social_handle = $social["handle"];
            $vector_query = $feed_domain = $apple_id = $site_url = "";
            $exact_query = $parse_query = [];
        endif;
        
        if(!empty($this->payload["apple_ids"])):
            $vector_query = $feed_domain = $apple_id = $site_url = "";
            $exact_query = $parse_query = [];
        endif;        
        
        $beats = !empty($this->payload["beats"]) ? $this->payload["beats"] : "";
        $language = !empty($this->payload["language"]) ? $this->payload["language"] : "";
        $podcast_network = !empty($this->payload["podcast_network"]) ? $this->payload["podcast_network"] : "";
        $audience_type = !empty($this->payload["audience_type"]) ? $this->payload["audience_type"] : "";
        
        $lis_age = !empty($this->payload["listener_age"]) ? $this->payload["listener_age"] : "";
        $lis_income = !empty($this->payload["listener_income"]) ? $this->payload["listener_income"] : "";
        $listener_gender = !empty($this->payload["listener_gender"]) ? $this->payload["listener_gender"] : "";
        
        if(!empty($lis_income)):
            $inc_arr = explode(",", $lis_income);        
            $inc_val_arr = [];
            foreach($inc_arr as $inc_key):
                $inc_item = $this->ue_ct->get_income($inc_key);
                if(!empty($inc_item)):
                    $inc_val_arr[] = $inc_item["solr"];
                endif;
            endforeach;        
            $listener_income = implode(",", $inc_val_arr);
        endif;
        
        if(!empty($lis_age)):
            $age_arr = explode(",", $lis_age);        
            $age_val_arr = [];
            foreach($age_arr as $age_key):
                $age_item = $this->ue_ct->get_age($age_key);
                if(!empty($age_item)):
                    $age_val_arr[] = $age_item["solr"];
                endif;
            endforeach;        
            $listener_age = implode(",", $age_val_arr);
        endif;
        
        $mp_charts = isset($this->payload["mp_charts"]) ? (int)$this->payload["mp_charts"] : 0;
        $qa = isset($this->payload["qa"]) ? (int)$this->payload["qa"] : 0;
        $gender = isset($this->payload["gender"]) ? $this->payload["gender"] : "";
        
        if(isset($this->payload["user_engagement"]) && !empty($this->payload["user_engagement"])):
            $length_arr = explode(",", $this->payload["user_engagement"]);
            foreach($length_arr as $key):
                $user_engagement[] = $this->ue_ct->get($key);
            endforeach;
        endif;
        
        if(isset($this->payload["episode_length"]) && !empty($this->payload["episode_length"])):
            $length_arr = explode(",", $this->payload["episode_length"]);
            foreach($length_arr as $key):
                $episode_length[] = $this->el_ct->get($key);
            endforeach;
        endif;
        
        if(isset($this->payload["apple_review"]) && !empty($this->payload["apple_review"])):
            $length_arr = explode(",", $this->payload["apple_review"]);
            foreach($length_arr as $key):
                $apple_review[] = $this->ue_ct->get_review($key);
            endforeach;
        endif;
        
        if(isset($this->payload["apple_rating"]) && !empty($this->payload["apple_rating"])):
            $length_arr = explode(",", $this->payload["apple_rating"]);
            foreach($length_arr as $key):
                $apple_rating[] = $this->ue_ct->get_rating($key);
            endforeach;
        endif;
        
        if(is_mp_list_url($user_query) || is_podcast_list($user_query)):
            $feeds = $this->fs->get_permalink_feed($user_query, 0);            
            $include_feeds = !empty($feeds) ? array_column($feeds, "feed_id") : [];
            $include_feeds = array_values(array_unique(array_filter($include_feeds)));            
            $site_url = !empty($include_feeds) ? "" : $site_url;
            $vector_query = !empty($include_feeds) ? "" : $vector_query;
        endif;
        
        if(!empty($reg_list_url)):
            $list_feeds = $this->fs->get_permalink_feed($reg_list_url, 0); 
            $or_feeds = !empty($list_feeds) ? array_column($list_feeds, "feed_id") : [];
            $or_feeds[] = $pin_feed_id;
            $or_feeds = array_values(array_unique(array_filter($or_feeds))); 
        endif;
        
        if(!empty($this->payload["other_attributes"])):
            $other_attributes = explode(",", $this->payload["other_attributes"]);
            $unset_arr = $locked_filters["OTHER_ATTRIBUTES"];
            if(!empty($unset_arr)):
                $other_attributes = array_flip($other_attributes);
                foreach($unset_arr as $unset_item):
                    unset($other_attributes[$unset_item]);
                endforeach;
                $other_attributes = array_values(array_flip($other_attributes));
            endif;
        endif; 
        
        $start_date = !empty($this->payload["start_date"]) ? $this->payload["start_date"] : "";
        $end_date = !empty($this->payload["end_date"]) ? $this->payload["end_date"] : "";
        
        $mand_filters = [
            $gender,
            $episode_length,
            $user_engagement,
            $include_feeds,
            $us_region,
            $exact_query,
            $parse_query,
            $audience_type,
            $social,
            $podcast_network,
            $language,
            $beats,
            $location,
            $apple_id_arr,
            $site_url,
            $feed_domain,
            $other_attributes,
            $start_date,
            $end_date,
            $listener_age,
            $listener_income,
            $listener_gender,
            $apple_rating,
            $apple_review,
            $community
        ];
        
        if(!array_filter($mand_filters)):
            $this->sendJson(ResponseStatusEnum::BAD_REQUEST);
        endif;        
        
        if(isset($this->payload["exclude_permalink"]) && !empty($this->payload["exclude_permalink"])):
            $feeds = $this->fs->get_permalink_feed($this->payload["exclude_permalink"]);
            $exclude_feeds = !empty($feeds) ? array_column($feeds, "feed_id") : [];   
            $exclude_feeds = array_values(array_unique(array_filter($exclude_feeds)));
        endif;        
        
        $sort_by = !empty($this->payload["sort_by"]) ? $this->sort($this->payload["sort_by"]) : DEFAULT_SEARCH_SORT;
        if(empty($user_query) && !empty($location) && $sort_by == DEFAULT_SEARCH_SORT):
            $sort_by = "review_count";
        endif;     
        
        timer_mark("format_filters");
        
        $matchobj = self::merge_exact_match_feed($user_query, $current_page);
        $is_exact_match_found = 0;
        if(!empty($matchobj)):
            if(!empty($matchobj["feed_id"])):
                $exclude_feeds[] = $matchobj["feed_id"];
            endif;
            
            if(!empty($matchobj["feed"])):
                $match_feed = $matchobj["feed"];
                $is_exact_match_found = 1;
                $dec_limit = 1;
            endif;            
        endif;
        
        timer_mark("get_exact_match_feed");
        
        if(!empty($audience_type)):
            $au_id_arr = explode(",", $audience_type);
            $au_vars = [
                "ids"       => $au_id_arr,
                "offset"    => 0,
                "limit"     => 9999,
            ];
            $au_obj = $this->audience_ct->get_audience_by_alias($au_vars);            
            if(!empty($au_obj["data"]["results"])):
                $id_arr = array_merge($au_id_arr, array_column($au_obj["data"]["results"], "id"));
                $audience_type = implode(",", $id_arr);
            endif;
        endif;
        
        if(!empty($beats)):        
            $beat_id_arr = explode(",", $beats);
            $beat_vars = [
                "ids"       => $beat_id_arr,
                "offset"    => 0,
                "limit"     => 99999,
                "debug"     => 0,
            ];
            $beat_obj = $this->beats_ct->get_beats_by_alias($beat_vars);            
            if(!empty($beat_obj["data"]["results"])):
                $id_arr = array_merge($beat_id_arr, array_column($beat_obj["data"]["results"], "id_auto"));
                $beats = implode(",", $id_arr);
            endif;
        endif;
        
        $is_email = 0;
        if(is_valid_email($user_query)):
            $is_email = 1;
            $this->payload["search_in"] = "email_json_txt";
            $vector_query = "";
        endif;
        
        $search_in = trim($this->payload["search_in"]);
        $search_in = !$core && empty($search_in) ? DEFAULT_SEARCH_IN : $search_in;   
        
        if($core && !empty($search_in) && empty($search_match)):
            $search_match = DEFAULT_SEARCH_MATCH;
        endif;
        
        if($core && !empty($search_match) && empty($search_in)):
            $search_in = DEFAULT_SEARCH_IN;
        endif;        
        
        $include_keyword = !empty($this->payload["include_keyword"]) ? explode(",", $this->payload["include_keyword"]) : [];
        $include_type = !empty($this->payload["include_type"]) ? $this->payload["include_type"] : "";
        $exclude_keyword = !empty($this->payload["exclude_keyword"]) ? explode(",", $this->payload["exclude_keyword"]) : [];
        
        $vars = [
            "query"                     => $qry_arr,
            "vector_query"              => $vector_query,
            "apple_id"                  => !empty($apple_id_arr) ? $apple_id_arr : [],
            "site_url"                  => !empty($site_url) ? strtolower($site_url) : "",
            "feed_domain"               => !empty($feed_domain) ? strtolower($feed_domain) : "",
            "negated"                   => $negated,
            "parse_query"               => $parse_query,
            "exact_query"               => $exact_query,
            "search_in"                 => $search_in,
            "search_match"              => $search_match,
            "endpoint"                  => "million-podcasts/search",
            "sort_by"                   => $sort_by,
            "sort_dir"                  => !empty($this->payload["sort_dir"]) ? $this->payload["sort_dir"] : DEFAULT_SEARCH_SORT_DIR,
            "locations"                 => $location,
            "debug"                     => isset($this->payload["debug"]) ? $this->payload["debug"] : 0,
            "beats"                     => $beats,
            "podcast_network"           => $podcast_network,
            "episode_length"            => $episode_length,
            "user_engagement"           => $user_engagement,
            "episode_language"          => $language,
            "type"                      => $this->get_type(),
            "start_date"                => $start_date,
            "end_date"                  => $end_date,
            "exclude_location"          => $this->payload["exclude_location"] == "yes" ? 1 : 0,
            "exclude_language"          => $this->payload["exclude_language"] == "yes" ? 1 : 0,
            "exclude_podcast_network"   => $this->payload["exclude_podcast_network"] == "yes" ? 1 : 0,
            "exclude_beats"             => $this->payload["exclude_beats"] == "yes" ? 1 : 0,
            "exclude_audience_type"     => $this->payload["exclude_audience_type"] == "yes" ? 1 : 0,
            "exclude_us_region"         => $this->payload["exclude_us_region"] == "yes" ? 1 : 0,
            "exclude_episode_length"    => $this->payload["exclude_episode_length"] == "yes" ? 1 : 0,
            "exclude_user_engagement"   => $this->payload["exclude_user_engagement"] == "yes" ? 1 : 0,
            "other_attributes"          => $other_attributes,
            "exclude_feeds"             => $exclude_feeds,
            "include_feeds"             => $include_feeds,
            "social_type"               => $social_type,
            "social_handle"             => $social_handle,
            "social_query"              => !empty($social_type) ? clean_url($user_query) : "",
            "actual_query"              => $actual_query,
            "audience_type"             => $audience_type,
            "us_region"                 => $us_region,
            "fields"                    => isset($this->payload["fields"]) ? $this->payload["fields"] : "",
            "bulk_list"                 => isset($this->payload["bulk_list"]) ? $this->payload["bulk_list"] : "",
            "gender"                    => $gender,           
            "count"                     => isset($this->payload["count"]) ? $this->payload["count"] : 0,
            
            "apple_review"              => $apple_review,
            "exclude_apple_review"      => $this->payload["exclude_apple_review"] == "yes" ? 1 : 0,
            
            "community"                 => $community,
            "exclude_community"         => $this->payload["exclude_community"] == "yes" ? 1 : 0,
            
            "apple_rating"              => $apple_rating,
            "exclude_apple_rating"      => $this->payload["exclude_apple_rating"] == "yes" ? 1 : 0,
            
            "listener_age"              => $listener_age,
            "listener_income"           => $listener_income,
            "listener_gender"           => $listener_gender,
            
            "exclude_listener_age"      => $this->payload["exclude_listener_age"] == "yes" ? 1 : 0,
            "exclude_listener_income"   => $this->payload["exclude_listener_income"] == "yes" ? 1 : 0,
            "exclude_listener_gender"   => $this->payload["exclude_listener_gender"] == "yes" ? 1 : 0,  
            
            "include_keyword"           => $include_keyword,
            "exlude_keyword"            => $exclude_keyword,
            "include_type"              => $include_type,
            
            "or_feeds"                  => $or_feeds,
            "pin_feed_id"               => $pin_feed_id
        ];
        
        if(!empty($primary_terms)):
            $vars["primary_query"] = $primary_terms;
        endif;
        
        if(!empty($boost_terms)):
            $vars["boost_terms"] = $boost_terms;
        endif;
        
        if($vars["sort_by"] == DEFAULT_SEARCH_SORT):
            unset($vars["sort_by"]);
            /*$weights = [
                "feed_name_ws"      => 10,
                //"review_count"      => 15,
                "total_apple_review_count"    => 15,
                //"folder_names"      => 8,
                "twitter_location"  => 3,                
                "designation_name"  => 2,                
                "feed_desc"         => 1,
            ];            
            if($ignore_default_sort):
                unset($weights["total_apple_review_count"]);
            endif;*/
            
            $weights = [
                "feed_name_stem"                    => 10,
                "feed_name"                         => 10,   // ← new: exact-mode equivalent
                "feed_name_apos"                    => 10,
                "total_apple_review_count"          => 15,
                "folder_names"                      => 5,
                "twitter_location_text_general"     => 3,
                "twitter_location"                  => 3,   // ← new: exact-mode equivalent
                "designation_name"                  => 3,
                "feed_desc_stem"                    => 2,
                "feed_desc"                         => 2,   // ← new: exact-mode equivalent
                "country_text_general"              => 1,
                "country"                           => 1,   // ← new: exact-mode equivalent
                "region_text_general"               => 1,
                "region"                            => 1,   // ← new
                "city_text_general"                 => 1,
                "city"                              => 1,   // ← new
            ];
            
            if($ignore_default_sort):
                unset($weights["total_apple_review_count"]);
            endif;            
            $vars["weights"] = $weights;
        endif;
        
        if(!$ignore_default_sort):
            $vars["deboost"] = ["last_original_article_creation_date"];  
        endif;
        
        if(($validate_request && $this->auto_id != MP_TOOLS_AUTO_ID && !$this->isPaid() && !$mp_charts)):        
            $this->payload["limit"] = FREE_USER_LIMIT;
        endif;
        
        if(is_any_c1_applied($this->payload) && (!$this->isPaid() || $this->is_trial_without_card)):
            $this->payload["limit"] = LOCK_SEARCH_RESULT_COUNT;
        endif;
        
        if(isset($payload["bulk_list"]) && $payload["bulk_list"]):
            $vars["limit"] = $payload["limit"] - $dec_limit;
            $vars["offset"] = $payload["offset"];
        else:
            $this->setPagination();
            $vars["limit"] = $this->pagination_limit - $dec_limit;
            $vars["offset"] = $this->pagination_offset;
        endif;    
        
        $vars["build_mode"] = 1;
        $vars["core"] = $core;
        $vars["auto_id"] = $this->auto_id;
        
        if(($is_exact_match_found && $act_query_type != "podcast_name") || $core):
            $pn_parse_query = [];
        endif;
        
        $pn_feeds = [];
        if(!empty($pn_parse_query)) {
            timer_mark("inside_podcast_match");
            if($current_page == 1):
                $pn_vars = $vars;   
                $pn_vars["parse_query"]  = $pn_parse_query;  
                $pn_vars["exact_query"]  = $pm_exact_query ?? [];
                $pn_vars["vector_query"] = "";
                $pn_vars["search_in"]    = "title";
                $pn_vars["limit"]        = 2;
                $pn_vars["offset"]       = 0;    
                $pn_vars["debug"]        = 0;   
                $pn_vars["count"]        = 0;   
                
                unset($pn_vars["weights"]);
                unset($pn_vars["deboost"]);
                
                $pn_result = $this->solr_ps->search($pn_vars);
                $pn_feeds  = $pn_result["data"]["contacts"] ?? [];
                
                timer_mark("solr_podcast_match"); 
                
                $pm_count = 0;
                if(!empty($pn_feeds)):
                    $pn_feed_ids = [];
                    foreach($pn_feeds as &$pn_feed):
                        $pn_feed_id = $pn_feed["feed_id"] ?? 0;
                        if(!empty($pn_feed_id)):
                            $exclude_feeds[] = $pn_feed_id;
                            $pn_feed_ids[]   = $pn_feed_id;
                            $dec_limit++;  
                            $pm_count++;
                            
                            if(isAdmin($this->auto_id)):
                                $pn_feed["feed_name"] = "PM:".$pn_feed["feed_name"];
                            endif;                            
                        endif;
                    endforeach;
                    unset($pn_feed);                    
                    $this->manage_pn_match_feeds("set", $user_query, $pn_feed_ids);
                    $vars["exclude_feeds"] = $exclude_feeds;
                    $vars["limit"] = max(0, $vars["limit"] - $pm_count);
                endif;        
                timer_mark("solr_podcast_match_end");
            else:
                $pn_feed_ids = $this->manage_pn_match_feeds("get", $user_query);
                if(!empty($pn_feed_ids)):
                    foreach($pn_feed_ids as $pn_feed_id):
                        $exclude_feeds[] = $pn_feed_id;
                    endforeach;
                    $vars["exclude_feeds"] = $exclude_feeds;
                endif;
            endif;
        }
        
        $is_paid_user = !$this->is_trial_without_card && $this->isPaid() ? true : false; 
        $attempt = 1;
        
        /*if(!empty($response_id)):
            $data = []; $total = 0;
            $res = $this->get_apple_id_from_brightdata($user_query, $filters, [], [], $response_id);            
            if(!empty($res)):
                $attempt = 2;
                $data = $res["data"];
                $total = $res["total"];
            endif; 
        else:*/
        
            if($this->email_id == "vasagan@feedspotmail.com"):
                $vars["debug"] = 0;
                /*$url = 'http://10.0.0.111:8080/solr/apple_podcast_index/select?q=*:*&fq=total_review_count:[*%20TO%20*]&fl=id,total_review_count';
                 $response = file_get_contents($url);
                 $data = json_decode($response, true);
                 echo "sample data<pre>";
                 print_r($data); die;*/
                //echo "<pre>"; print_r($vars); die;
            endif;
        
            timer_mark("before_solr_search");     
            if($vars["limit"] > 0):
                $result = $this->solr_ps->search($vars);
                //echo "<pre>"; print_r($result); die;
            else:
                $result = [
                    "data" => [
                        "contacts" => [],
                        "totalRecords" => 0
                    ]
                ];
            endif;
            
            timer_mark("after_solr_search");
            $contacts = !empty($result["data"]["contacts"]) ? $result["data"]["contacts"] : [];  
            if(!empty($pn_feeds)):
                if(!empty($act_query_type) && $act_query_type == "podcast_name"):
                    foreach(array_reverse($pn_feeds) as $pn_feed):
                        array_unshift($contacts, $pn_feed);
                    endforeach;
                else:
                    $position = $is_paid_user ? 2 : 2;
                    array_splice($contacts, $position, 0, $pn_feeds);
                endif;
            endif;
            
            if(!empty($match_feed)):
                $match_feeds_arr = isset($match_feed["feed_id"]) ? [$match_feed] : $match_feed;
                foreach(array_reverse($match_feeds_arr) as $mf):
                    array_unshift($contacts, $mf);
                endforeach;
            endif;
            
            timer_mark("after_ex_pm_contact_merge");
            
            $total = (int)$result["data"]["totalRecords"];
            $total = $total + $dec_limit;
            
            /*if(!is_any_c1_applied($this->payload) && !$retry_search && !$claude_cache && $can_call_claude_api && $total < 1 && $current_page == 1):
                $this->payload["retry_search"] = 1;
                timer_mark("retry search with claude expanded query");            
                return $this->search();
            endif;*/
            
            //return data for making new list, format not required 
            if(isset($this->payload["return_data"]) && $this->payload["return_data"] == 1):
                return $contacts;
            endif;  
            
            $addl_vars = [
                "mp_charts" => $mp_charts,
                "from_list" => $from_list,
                "location"  => $location,
                "offset"    => $vars["offset"],
                "is_search" => $mp_charts || $qa || isAdmin($this->auto_id)  ? 0 : 1,
            ];
            $data = !empty($contacts) ? format_feed_data($contacts, 0, 0, 0, $is_paid_user, $addl_vars) : [];   
        //endif;   
        
        
        $filters = $vars;        
        unset($vars["debug"]);
        unset($vars["endpoint"]);
        unset($vars["type"]);
        
        if(!$mp_charts && $current_page == 1 && $total < BD_MIN_COUNT && !$is_email):
            timer_mark("before_bd_search");
            $res = $this->get_apple_id_from_brightdata($user_query, $filters, $contacts, $match_feed);            
            if(!empty($res)):
                $attempt = 2;
                $data = $res["data"];
                $total = $res["total"];
            endif;    
            timer_mark("after_bd_search");
        endif;
        
        $vars["query"] = addslashes($user_query);
        $vars["total_feeds"] = $total;
        $vars["attempt"] = $attempt;
        
        if(isset($this->payload["count"]) && $this->payload["count"] == 1):
            return $total;
        endif;
        
        $addl = [];
        /*if(SHOW_WIZARD && $this->tooltip_search):
            $addl["preferences"] = [PreferenceEnum::getConstantName(PreferenceEnum::TOOLTIP_SEARCH) => PreferenceEnum::OFF];
            $this->account_mdl->preference(["pkey" => PreferenceEnum::TOOLTIP_SEARCH, "pvalue" => PreferenceEnum::OFF, "user_auto_id" => $this->auto_id]); 
        endif;*/
        
        $lang_arr = !empty($language) ? explode(",", $language) : [];
        $has_non_en = !empty(array_diff($lang_arr, ["en"]));
        
        if((empty($language) || $has_non_en) && !isset($this->user_preferences[PreferenceEnum::LANG_FILTER_SET])):
            $addl["preferences"] = [PreferenceEnum::getConstantName(PreferenceEnum::LANG_FILTER_SET) => PreferenceEnum::ON];
            $this->account_mdl->preference(["pkey" => PreferenceEnum::LANG_FILTER_SET, "pvalue" => PreferenceEnum::ON, "user_auto_id" => $this->auto_id]);
        endif;
        
        if(!$mp_charts && $current_page == 1):
            $addl["list_options"] = $this->generate_top_feeds($total);
        endif;
        
        if(isset($result["error"])):
            $addl["error"] = $result;
        endif;
        
        //ignore mp tools request
        if($this->auto_id != MP_TOOLS_AUTO_ID):        
            $log_id = $this->log_ct->add([
                "user_auto_id"  => $this->auto_id, 
                "event_type"    => LogEventEnum::SEARCH, 
                "data"          => jsonEncode($vars),
                "uuid"          => !empty($this->guest_uuid) ? $this->guest_uuid : "",               
            ]);
            
            if(!empty($log_id)):
                $client = $this->predis->redis_client();
                $client->rpush($this->predis->get_key("search_timer_queue"), jsonEncode(["log_id" => $log_id, "data" => timer_get()]));
            endif;
            
        endif;
        
        timer_mark("paginate");
        
        if(isAdmin($this->auto_id)):
            $addl["timer"] = timer_get();
            $addl["user_query"] = $user_query;
            $addl["solr_url"] = $result["data"]["solr_url"] ?? "";
            $addl["restatement_overrides"] = $restatement_overrides;
        endif;
        
        if(!$core && isAdmin($this->auto_id)):
            $addl["claude_response"] = $claude_obj;
            $addl["claude_cache"] = $claude_cache ? "HIT" : "MISS";
            $addl["query"] = $mp_query;
        endif;
        
        $this->paginate($total, $data, 1, "", $review_min_max, $addl);              
    }  
    
    public function manage_pn_match_feeds($mode, $query, $feed_ids = []) {
        //$predis = new PredisModel(); 
        $client = $this->predis->redis_client();
        
        $query = preg_replace('/\s+/', '', strtolower($query));
        $key = $this->predis->get_key("search_pn_feeds");
        $key = $key.md5($query).$this->auto_id;
        
        if($mode == "get"):
            $obj = $client->get($key);
            return !empty($obj) ? json_decode($obj, true) : [];
        elseif($mode == "set"):
            $client->set($key, json_encode($feed_ids), "EX", RED_3_HOUR_CACHE_EXPIRE);
        endif;
    }
    
    public function get_apple_id_from_brightdata($query, $vars = [], $existing_feeds = [], $match_feed = [], $response_id = null) {
        $limit = empty($existing_feeds) ? BD_GOOGLE_LIMIT : BD_GOOGLE_LIMIT_ALT;
        $this->brightdata_ctr();        
        $obj = $this->brightdata_ct->get($query, $limit, $response_id);
        
        //$bd_apple = $apple = $obj["apple_ids"];
        $apple = $sv_apple = !empty($obj["apple_ids"]) ? array_values($obj["apple_ids"]) : [];
        $save = $obj["save_data"];
        
        if(empty($apple)):
            return false;
        endif;
        
        $ex_apple = !empty($existing_feeds) ? array_column($existing_feeds, "apple_id") : [];
        $apple = !empty($ex_apple) ? array_merge($apple, $ex_apple) : $apple;
        $apple = array_values(array_unique(array_filter($apple)));
        $ex_apple = array_values(array_unique(array_filter($ex_apple)));
        
        $vars["apple_id"] = $apple;
        $vars["limit"] = count($apple);
        $vars["offset"] = $vars["offset"] ?? 0;
        $vars["bright_data"] = 1;
        $vars["parse_query"] = [];
        $vars["exact_query"] = [];
        $vars["actual_query"] = "";
        $vars["vector_query"] = "";
        $apply_sort = isset($vars["sort_by"]) ? 1 : 0;
        $result = $this->solr_ps->search($vars);
        
        $is_paid_user = !$this->is_trial_without_card && $this->isPaid() ? true : false;
        $data = isset($result["data"]["contacts"]) ? format_feed_data($result["data"]["contacts"], 0, 0, 1, $is_paid_user, ["location" => $vars["locations"]]) : [];
        $total = (int)$result["data"]["totalRecords"];
        $review = $result["data"]["review_min_max"];
        
        if($save):
            $found_arr = !empty($data) ? array_column($data, "apple_id") : [];
            $missing_arr = array_diff($sv_apple, $found_arr);
            $missing_arr = array_values($missing_arr);
            $found_arr = array_map("strval", $found_arr);
            $missing_arr = array_map("strval", $missing_arr);
            $sv_apple = array_map("strval", $sv_apple);
            
            $vars = [
                "query"             => addslashes($query),
                "slug"              => remove_special_chars($query),
                "bd_apple_ids"      => jsonEncode($sv_apple),
                "found_apple_ids"   => jsonEncode($found_arr),
                "missing_apple_ids" => jsonEncode($missing_arr),
                "response_id"       => $response_id ?? ""
            ];            
            $this->brightdata_ct->add($vars);
        endif;
        
        if($total > BD_MAX_RESULTS):        
            $total = BD_MAX_RESULTS;
            $data = array_slice($data, 0, BD_MAX_RESULTS);        
        endif;
        
        if($apply_sort):
            $merged_data = [];  
            foreach($data as $rdata):
                $apple_id = $rdata["apple_id"];            
                if(in_array($apple_id, $ex_apple)):
                    $rdata["feed_name"] = str_replace("BD:", "", $rdata["feed_name"]);
                endif;
                $merged_data[] = $rdata;
            endforeach;
        else:        
            $processed = $google = $solr = $blurred = $inactive = [];
            $six_months_ago = time() - (6 * 30 * 24 * 60 * 60);
            $data = array_column($data, null, "apple_id");
            $index_no = 1;
            
            foreach($sv_apple as $index => $apple_id):            
                $obj = isset($data[$apple_id]) ? $data[$apple_id] : [];            
                if(!empty($obj)):
                    $entry_date = strtotime($obj["last_original_article_creation_date"]);
                    $processed[] = $apple_id;
                    
                    if(in_array($apple_id, $ex_apple)):
                        $obj["feed_name"] = str_replace("BD:", "", $obj["feed_name"]);
                    endif;
                    
                    if(!empty($obj["blur"])):
                        $index_no++;
                        $blurred[$index_no] = $obj;
                        continue;
                    endif;
                    
                    if($entry_date > $six_months_ago):
                        $google[] = $obj;
                    else:
                        $inactive[] = $obj;
                    endif;            
                endif;
            endforeach; 
        
            foreach($ex_apple as $apple_id):
                if(in_array($apple_id, $processed)):
                    continue;
                endif;
                
                $obj = isset($data[$apple_id]) ? $data[$apple_id] : [];
                if(!empty($obj)):
                    $obj["feed_name"] = str_replace("BD:", "", $obj["feed_name"]);
                    $solr[] = $obj;
                endif;            
            endforeach;
            
            $merged_data = array_merge($google, $solr, $inactive);    
            
            ksort($blurred);
            foreach($blurred as $index => $obj):
                $index = min($index, count($merged_data));
                array_splice($merged_data, $index, 0, [$obj]);
            endforeach;
            
        endif;
        
        if(!empty($match_feed)):
            $is_paid_user = !$this->is_trial_without_card && $this->isPaid() ? true : false;
            $format_match = format_feed_data([$match_feed], 0, 0, 0, $is_paid_user, ["location" => $vars["locations"]]);
            if(isset($format_match[0]) && is_array($format_match[0])):
                array_unshift($merged_data, $format_match[0]);  
            endif;
            $total = $total + 1;
        endif;
        
        return [
            "data"      => $merged_data,
            "total"     => $total,
            "review"    => $review
        ];        
    }
    
    public function review() {
        $min_count = $max_count = 0;
        $vars = ["st_name" => "max_review_count", "debug" => isset($this->payload["debug"]) ? $this->payload["debug"] : 0];
        $result = $this->solr_ps->review($vars)["data"] ?? []; 
        
        if(!empty($result)):
            $min_count = (int)$result["min_count"];
            $max_count = (int)$result["max_count"];
        endif;
        $this->sendJson(ResponseStatusEnum::SUCCESS, "", ["min_count" => $min_count, "max_count" => $max_count]);
    }
    
    public function count() {
        $this->payload["count"] = 1;
        $type = "";
        
        if(isset($this->payload["core"]) && $this->payload["core"] == 1):
            $count = $this->search();
            $type = "ai";            
        elseif($this->payload["search_type"] == "podcast"):
            $this->payload["core"] = 0;   
            $type = "podcast";
            $count = $this->search();            
        elseif($this->payload["search_type"] == "episode"):
            $count = $this->episodes_ct->search(1);
            $type = "episode";
        endif;        
        $this->sendJson(ResponseStatusEnum::SUCCESS, "", ["count" => $count, "type" => $type]);
    }
    
    public function merge_exact_match_feed($user_query, $current_page) {
        
        //$word_count = count(explode(" ", trim($actual_query))); 
        if(!MERGE_EXACT_MATCH_FEED || is_any_c1_applied($this->payload)): //|| $word_count < 2
            return false;
        endif;
        
        $file_name = DOCUMENT_ROOT."Static/queries.json";
        if(file_exists($file_name)):
            $json = file_get_contents($file_name);
            $ignore_query_arr = !empty($json) ? json_decode($json, true) : []; 
            if(in_array(strtolower($user_query), $ignore_query_arr)):
                return false;
            endif;
        endif;        
        
        $feed = [];
        $feed_id = 0;
        
        if($current_page == 1):
            
            $query = double_escape($user_query);
            if(contains_spcial_char($user_query)):
                $query = sanitize_query($query, 1);
                $query = sanitize_query($query, 2);   
                
                if($this->email_id == "vasagan@feedspotmail.com"):
                    //echo $query; die;
                endif;
                
            endif;   
            $query = str_replace("–", "&ndash;", $query);
            
            $vars = [
                "query"     => $query,
                "debug"     => 0,
            ];
            
            if($this->email_id == "vasagan@feedspotmail.com"):
                $vars["debug"] = 0;
            endif;
            
            //$feeds = $this->solr_ct->callApi($vars, $vars["debug"])["data"]["contacts"] ?? [];
            $feeds = $this->solr_ps->search_inactive($vars)["data"]["contacts"] ?? [];
            if(!empty($feeds)):
                $feed = sort_name_data($feeds, $user_query);
                $feed_id = !empty($feed) && isset($feed["feed_id"]) ? $feed["feed_id"] : 0;
                if(!empty($feed_id)):                    
                    if(isAdmin($this->auto_id)):
                        $feed["feed_name"] = "EM:".$feed["feed_name"];
                    endif;
                    self::manage_exact_match_feed("set", $user_query, $feed_id);
                endif;
            endif;
        else:
            $feed_id = $this->manage_exact_match_feed("get", $user_query);
        endif;
        
        return ["feed" => $feed, "feed_id" => $feed_id];
    }
    
    public function list_range() {
        $rows = $this->review_count_range();
        $data = [];
        foreach($rows as $key => $item):
            $data[] = ["key" => $key, "title" => $item["title"]];
        endforeach;
        $this->sendJson(ResponseStatusEnum::SUCCESS, "", $data);
    }
    
    public function option_range() {
        $this->validateInput(["ids" => "required"]);
        $id_arr = explode(",", $this->payload["ids"]);
        $data = [];
        
        foreach($id_arr as $key):
            $item = $this->review_count_range($key);
            if(!empty($item)):
                $data[] = ["key" => (int)$key, "title" => $item["title"]];
            endif;
        endforeach;
        $this->sendJson(ResponseStatusEnum::SUCCESS, "", $data);
    } 
    
    public function review_count_range($id = 0) {
        $rows = [
            [
                "key"   => 1,
                "title" => "Up to 100",
                "start" => "*",
                "end"   => 100
            ], [
                "key"   => 2,
                "title" => "100 - 1000",
                "start" => 100,
                "end"   => 1000
            ], [
                "key"   => 3,
                "title" => "1,000 – 10,000",
                "start" => 1000,
                "end"   => 10000
            ], [
                "key"   => 4,
                "title" => "10,000 – 50,000",
                "start" => 10000,
                "end"   => 50000
            ], [
                "key"   => 5,
                "title" => "50,000 – 250,000",
                "start" => 50000,
                "end"   => 250000
            ], [
                "key"   => 6,
                "title" => "250,000+",
                "start" => 250000,
                "end"   => "*"
            ]           
        ];
        
        $rows = array_column($rows, null, "key");
        if(!empty($id)):
            $value = isset($rows[$id]) ? $rows[$id] : [];
            return $value;
        endif;
        
        return $rows;
    }
}
?>
